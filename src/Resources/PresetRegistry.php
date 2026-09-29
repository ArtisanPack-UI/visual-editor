<?php

/**
 * Host preset registry (#773).
 *
 * Normalises the host application's `artisanpack.visual-editor.presets`
 * config into the descriptor lists the editor stamps onto its mount
 * element as the `data-presets` JSON attribute. This gives applications
 * that don't ship a `theme.json` and don't sit on top of cms-framework a
 * supported seam for extending or replacing the editor's default palette,
 * font sizes, font families, and spacing sizes without patching the JS
 * bundle.
 *
 * Order of precedence (documented in `config/visual-editor.php`):
 *   1. Package defaults baked into `editor-settings.ts` (`DEFAULT_PALETTE`
 *      and friends).
 *   2. Active theme's `theme.json` presets (when a theme ships them),
 *      applied through `useThemedEditorSettings`.
 *   3. Host `presets` config resolved by this registry — layered on top
 *      of whichever base layer exists. Under `append` mode, host slugs
 *      that collide with a theme/default slug replace that entry in
 *      place; under `replace` mode, the host list wins outright for
 *      that preset kind.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.9.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Resources;

class PresetRegistry
{
	/**
	 * Slug character set shared with the JS registry's guard. A slug
	 * lands in the editor's `theme` preset origin and eventually in
	 * saved block markup, so anything outside this set is dropped
	 * rather than risking malformed CSS class names or JSON.
	 */
	protected const SAFE_SLUG_PATTERN = '/^[a-z0-9_-]+$/';

	/**
	 * Reject-list of characters that must never appear inside a color
	 * value. `<>` prevents HTML-attribute breakout, `"'` prevents
	 * JSON-attribute escape, and backtick blocks template-string
	 * exploits when the value reaches JS. Internal whitespace is
	 * intentionally allowed so `rgb(0, 0, 0)`, `oklch(0.5 0.1 200)`,
	 * `hsl(210deg 100% 50%)` and their kin round-trip verbatim to the
	 * browser, which is the final arbiter of CSS validity.
	 */
	protected const REJECT_COLOR_PATTERN = '/[<>"\'`]/';

	/**
	 * Preset mode values accepted on the per-list `mode` key.
	 */
	protected const VALID_MODES = [ 'append', 'replace' ];

	/**
	 * Package default spacing sizes. Mirrors `DEFAULT_SPACING_SIZES` in
	 * `resources/js/visual-editor/editor-settings.ts` (slug + size only;
	 * the labels are editor-only) so the front end declares the same
	 * `--wp--preset--spacing--*` properties the editor's spacing pickers
	 * offer when a theme ships no `spacingSizes` (#814). Keep the two
	 * lists in sync — `editor/__tests__/spacing-preset-styles.test.ts` guards it.
	 *
	 * @since 1.12.0
	 */
	public const DEFAULT_SPACING_SIZES = [
		[ 'slug' => '20', 'size' => '0.5rem' ],
		[ 'slug' => '30', 'size' => '1rem' ],
		[ 'slug' => '40', 'size' => '1.5rem' ],
		[ 'slug' => '50', 'size' => '3rem' ],
		[ 'slug' => '60', 'size' => '5rem' ],
		[ 'slug' => '70', 'size' => '7rem' ],
	];

	/**
	 * Resolve the spacing sizes the editor's pickers offer, so the front
	 * end can declare a custom property for every one of them (#814).
	 *
	 * Mirrors the editor's precedence exactly:
	 *   - theme ships `spacingSizes` → the theme list, with host entries
	 *     merged on top only when the host registers any;
	 *   - otherwise → the package defaults merged with the host list
	 *     (where `replace` mode, even with no entries, clears them).
	 *
	 * @since 1.12.0
	 *
	 * @param  mixed  $themeSpacingSizes  The theme's `settings.spacing.spacingSizes`, if any.
	 *
	 * @return array<int, array{slug: string, size: string}>
	 */
	public static function effectiveSpacingSizes( mixed $themeSpacingSizes ): array
	{
		$host  = self::fromConfig()['spacingSizes'];
		$theme = self::normaliseThemeSpacingSizes( $themeSpacingSizes );

		if ( null !== $theme ) {
			return [] === $host['entries'] ? $theme : self::mergeSizedList( $theme, $host );
		}

		return self::mergeSizedList( self::DEFAULT_SPACING_SIZES, $host );
	}

	/**
	 * Resolve the configured presets into normalised descriptor lists.
	 *
	 * Returns a stable-shape record — every list key is always present,
	 * always with a `mode` and an `entries` array — so the JS-side
	 * consumer can walk the record without null-checks.
	 *
	 * @since 1.9.0
	 *
	 * @return array{
	 *     palette: array{mode: string, entries: array<int, array{slug: string, name: string, color: string}>},
	 *     fontSizes: array{mode: string, entries: array<int, array{slug: string, name: string, size: string}>},
	 *     fontFamilies: array{mode: string, entries: array<int, array{slug: string, name: string, fontFamily: string}>},
	 *     spacingSizes: array{mode: string, entries: array<int, array{slug: string, name: string, size: string}>}
	 * }
	 */
	public static function fromConfig(): array
	{
		$config = (array) config( 'artisanpack.visual-editor.presets', [] );

		return [
			'palette'      => self::normaliseList(
				$config['palette'] ?? null,
				'color',
				fn ( array $entry ): ?array => self::normalisePaletteEntry( $entry )
			),
			'fontSizes'    => self::normaliseList(
				$config['font_sizes'] ?? null,
				'size',
				fn ( array $entry ): ?array => self::normaliseSizedEntry( $entry, 'size' )
			),
			'fontFamilies' => self::normaliseList(
				$config['font_families'] ?? null,
				'fontFamily',
				fn ( array $entry ): ?array => self::normaliseFontFamilyEntry( $entry )
			),
			'spacingSizes' => self::normaliseList(
				$config['spacing_sizes'] ?? null,
				'size',
				fn ( array $entry ): ?array => self::normaliseSizedEntry( $entry, 'size' )
			),
		];
	}

	/**
	 * Normalise one preset list into `{ mode, entries }` shape.
	 *
	 * Accepts either a bare list of entries (implicit `append` mode) or
	 * a wrapper object with an explicit `mode` and `entries` key. An
	 * unknown `mode` value falls back to `append` so a typo can never
	 * silently wipe the package defaults.
	 *
	 * @since 1.9.0
	 *
	 * @param  mixed                              $raw            Raw config value for this list.
	 * @param  string                             $valueKey       Debug-only name of the value field (unused; kept for
	 *                                                             signature symmetry with the normaliser callables).
	 * @param  callable(array<mixed>): ?array<string, string> $normaliseEntry Per-entry normaliser returning the
	 *                                                             descriptor or `null` when the entry is invalid.
	 *
	 * @return array{mode: string, entries: array<int, array<string, string>>}
	 */
	protected static function normaliseList( mixed $raw, string $valueKey, callable $normaliseEntry ): array
	{
		if ( null === $raw || ! is_array( $raw ) ) {
			return [ 'mode' => 'append', 'entries' => [] ];
		}

		$mode        = 'append';
		$rawEntries  = $raw;

		// Wrapper form: `['mode' => 'append'|'replace', 'entries' => [...]]`.
		// Detected by the presence of the `entries` key; a bare list
		// keeps the whole array as its entries.
		if ( array_key_exists( 'entries', $raw ) ) {
			$candidateMode = $raw['mode'] ?? 'append';
			if ( is_string( $candidateMode ) && in_array( $candidateMode, self::VALID_MODES, true ) ) {
				$mode = $candidateMode;
			}
			$rawEntries = is_array( $raw['entries'] ) ? $raw['entries'] : [];
		}

		$entries = [];
		$seen    = [];

		foreach ( $rawEntries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$normalised = $normaliseEntry( $entry );
			if ( null === $normalised ) {
				continue;
			}

			$slug = $normalised['slug'];

			// A host that lists the same slug twice gets the first
			// definition — mirrors TaxonomyRegistry's behaviour and
			// keeps the theme origin free of duplicate-key warnings
			// from Gutenberg's picker components.
			if ( isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;

			$entries[] = $normalised;
		}

		return [ 'mode' => $mode, 'entries' => $entries ];
	}

	/**
	 * @param  array<mixed>  $entry
	 * @return array{slug: string, name: string, color: string}|null
	 */
	protected static function normalisePaletteEntry( array $entry ): ?array
	{
		$slug  = self::resolveSlug( $entry );
		$color = $entry['color'] ?? null;

		if ( null === $slug || ! is_string( $color ) ) {
			return null;
		}

		$color = trim( $color );
		if ( '' === $color || 1 === preg_match( self::REJECT_COLOR_PATTERN, $color ) ) {
			return null;
		}

		return [
			'slug'  => $slug,
			'name'  => self::resolveName( $entry, $slug ),
			'color' => $color,
		];
	}

	/**
	 * Normalise entries whose value key is a plain string (font size,
	 * spacing size). The value is only trimmed and non-empty-checked
	 * so hosts can pass units matching the picker (`13px`, `1rem`,
	 * `clamp(...)`) without a per-unit validator here.
	 *
	 * @param  array<mixed>  $entry
	 * @return array{slug: string, name: string, size: string}|null
	 */
	protected static function normaliseSizedEntry( array $entry, string $valueKey ): ?array
	{
		$slug  = self::resolveSlug( $entry );
		$value = $entry[ $valueKey ] ?? null;

		if ( null === $slug || ! is_string( $value ) ) {
			return null;
		}

		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}

		return [
			'slug'      => $slug,
			'name'      => self::resolveName( $entry, $slug ),
			$valueKey   => $value,
		];
	}

	/**
	 * @param  array<mixed>  $entry
	 * @return array{slug: string, name: string, fontFamily: string}|null
	 */
	protected static function normaliseFontFamilyEntry( array $entry ): ?array
	{
		$slug   = self::resolveSlug( $entry );
		$family = $entry['fontFamily'] ?? $entry['font_family'] ?? null;

		if ( null === $slug || ! is_string( $family ) ) {
			return null;
		}

		$family = trim( $family );
		if ( '' === $family ) {
			return null;
		}

		return [
			'slug'       => $slug,
			'name'       => self::resolveName( $entry, $slug ),
			'fontFamily' => $family,
		];
	}

	/**
	 * Read + validate an entry's `slug`, returning the normalised
	 * lowercase form or `null` when the value is missing, non-string,
	 * empty, or outside {@see self::SAFE_SLUG_PATTERN}.
	 *
	 * @param  array<mixed>  $entry
	 */
	protected static function resolveSlug( array $entry ): ?string
	{
		$slug = $entry['slug'] ?? null;
		if ( ! is_string( $slug ) ) {
			return null;
		}

		$slug = strtolower( trim( $slug ) );
		if ( '' === $slug || 1 !== preg_match( self::SAFE_SLUG_PATTERN, $slug ) ) {
			return null;
		}

		return $slug;
	}

	/**
	 * Read the entry's display name, falling back to a title-cased
	 * version of the slug. Preset labels are surfaced directly to
	 * authors, so an untranslated slug fallback still beats an empty
	 * picker row.
	 *
	 * @param  array<mixed>  $entry
	 */
	protected static function resolveName( array $entry, string $slug ): string
	{
		$name = $entry['name'] ?? null;
		if ( is_string( $name ) && '' !== trim( $name ) ) {
			return trim( $name );
		}

		return ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
	}

	/**
	 * Build the `:root { --wp--preset--spacing--*: …; }` block for every
	 * spacing size the editor's pickers offer (#814). `var:preset|spacing|*`
	 * picks reference these properties, which previously existed only when
	 * the theme shipped `spacingSizes` — on any other theme padding, margin
	 * and Block spacing presets resolved to nothing. Slugs follow the same
	 * rule as `ThemeJsonTokensCompiler::slug()`. Returns `''` when the list
	 * is empty (e.g. host `replace` mode with no entries).
	 *
	 * @since 1.12.0
	 *
	 * @param  mixed  $themeSpacingSizes  The theme's `settings.spacing.spacingSizes`, if any.
	 */
	public static function spacingPresetsCss( mixed $themeSpacingSizes ): string
	{
		$declarations = [];

		foreach ( self::effectiveSpacingSizes( $themeSpacingSizes ) as $entry ) {
			$slug = (string) preg_replace( '/[^a-z0-9\-]/', '-', strtolower( $entry['slug'] ) );
			$size = trim( $entry['size'] );

			// Values land inside a `<style>` element; skip anything that
			// could close the declaration, rule, or tag, open a comment
			// that swallows the following rules, or smuggle a CSS escape.
			if ( '' === $slug || '' === $size || 1 === preg_match( '#[;{}<>\\\\]|/\*|\*/#', $size ) ) {
				continue;
			}

			$declarations[] = sprintf( '--wp--preset--spacing--%s: %s;', $slug, $size );
		}

		return [] === $declarations ? '' : ":root {\n\t" . implode( "\n\t", $declarations ) . "\n}";
	}

	/**
	 * The active cms-framework theme's `settings.spacing.spacingSizes`, or
	 * `null` when cms-framework isn't installed / no theme is active.
	 *
	 * @since 1.12.0
	 */
	public static function activeThemeSpacingSizes(): mixed
	{
		$themeManager = 'ArtisanPackUI\\CMSFramework\\Modules\\Themes\\Managers\\ThemeManager';

		if ( ! class_exists( $themeManager ) || ! app()->bound( $themeManager ) ) {
			return null;
		}

		$theme = app( $themeManager )->getActiveTheme();

		return is_array( $theme ) ? ( $theme['settings']['spacing']['spacingSizes'] ?? null ) : null;
	}

	/**
	 * Normalise a theme's `spacingSizes` list the way the editor's
	 * `extractThemeSpacingSizes()` does: trimmed lowercase slugs, string
	 * sizes, first slug wins. Returns `null` when the theme declares no
	 * usable entry, so callers fall back to the package defaults.
	 *
	 * @since 1.12.0
	 *
	 * @return array<int, array{slug: string, size: string}>|null
	 */
	protected static function normaliseThemeSpacingSizes( mixed $sizes ): ?array
	{
		if ( ! is_array( $sizes ) || [] === $sizes ) {
			return null;
		}

		$out  = [];
		$seen = [];

		foreach ( $sizes as $entry ) {
			if ( ! is_array( $entry ) || ! is_string( $entry['slug'] ?? null ) || ! is_string( $entry['size'] ?? null ) ) {
				continue;
			}

			$slug = strtolower( trim( $entry['slug'] ) );

			if ( '' === $slug || isset( $seen[ $slug ] ) ) {
				continue;
			}

			$seen[ $slug ] = true;
			$out[]         = [ 'slug' => $slug, 'size' => $entry['size'] ];
		}

		return [] === $out ? null : $out;
	}

	/**
	 * Merge a host list into a base list per its mode. Mirrors the JS
	 * `mergePresetList()`: `replace` returns the host entries outright;
	 * `append` overrides colliding slugs in place and appends the rest.
	 *
	 * @since 1.12.0
	 *
	 * @param  array<int, array{slug: string, size: string}>  $base
	 * @param  array{mode: string, entries: array<int, array<string, string>>}  $host
	 *
	 * @return array<int, array{slug: string, size: string}>
	 */
	protected static function mergeSizedList( array $base, array $host ): array
	{
		$hostEntries = array_map(
			static fn ( array $entry ): array => [ 'slug' => $entry['slug'], 'size' => $entry['size'] ],
			$host['entries'],
		);

		if ( 'replace' === $host['mode'] ) {
			return $hostEntries;
		}

		$hostBySlug = [];

		foreach ( $hostEntries as $entry ) {
			$hostBySlug[ $entry['slug'] ] = $entry;
		}

		$merged     = [];
		$overridden = [];

		foreach ( $base as $entry ) {
			if ( isset( $hostBySlug[ $entry['slug'] ] ) ) {
				$merged[]                     = $hostBySlug[ $entry['slug'] ];
				$overridden[ $entry['slug'] ] = true;
			} else {
				$merged[] = $entry;
			}
		}

		foreach ( $hostEntries as $entry ) {
			if ( ! isset( $overridden[ $entry['slug'] ] ) ) {
				$merged[] = $entry;
			}
		}

		return $merged;
	}
}
