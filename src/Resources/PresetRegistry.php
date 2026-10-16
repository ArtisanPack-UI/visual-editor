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

use ArtisanPackUI\VisualEditor\Support\CssValue;
use Throwable;

class PresetRegistry
{

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
	 * Package default font sizes. Mirrors `DEFAULT_FONT_SIZES` in
	 * `resources/js/visual-editor/editor-settings.ts` (slug + size only)
	 * so the front end declares the `--wp--preset--font-size--*`
	 * properties and `.has-{slug}-font-size` classes the editor's picker
	 * offers when a theme ships no `fontSizes` (#821). Kept in sync by
	 * `editor/__tests__/default-preset-styles.test.ts`.
	 *
	 * @since 1.12.1
	 */
	public const DEFAULT_FONT_SIZES = [
		[ 'slug' => 'small', 'size' => '13px' ],
		[ 'slug' => 'regular', 'size' => '16px' ],
		[ 'slug' => 'medium', 'size' => '20px' ],
		[ 'slug' => 'large', 'size' => '28px' ],
		[ 'slug' => 'huge', 'size' => '36px' ],
	];

	/**
	 * Package default color palette. Mirrors `DEFAULT_PALETTE` in
	 * `editor-settings.ts` (slug + color only) for the same reason as
	 * {@see self::DEFAULT_FONT_SIZES} (#821).
	 *
	 * @since 1.12.1
	 */
	public const DEFAULT_PALETTE = [
		[ 'slug' => 'base-content', 'color' => '#1f2937' ],
		[ 'slug' => 'base-muted', 'color' => '#6b7280' ],
		[ 'slug' => 'primary', 'color' => '#2563eb' ],
		[ 'slug' => 'secondary', 'color' => '#64748b' ],
		[ 'slug' => 'accent', 'color' => '#9333ea' ],
		[ 'slug' => 'success', 'color' => '#16a34a' ],
		[ 'slug' => 'warning', 'color' => '#d97706' ],
		[ 'slug' => 'error', 'color' => '#dc2626' ],
	];

	/**
	 * Package default font families. Mirrors `DEFAULT_FONT_FAMILIES` in
	 * `editor-settings.ts` (slug + fontFamily only) so the front end
	 * declares the `--wp--preset--font-family--*` properties and
	 * `.has-{slug}-font-family` classes the editor's picker offers when
	 * a theme ships no `fontFamilies` (#872).
	 *
	 * @since 1.15.0
	 */
	public const DEFAULT_FONT_FAMILIES = [
		[ 'slug' => 'system', 'fontFamily' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif' ],
		[ 'slug' => 'serif', 'fontFamily' => 'Georgia, Cambria, "Times New Roman", Times, serif' ],
		[ 'slug' => 'mono', 'fontFamily' => 'ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace' ],
	];

	/**
	 * WordPress's default shadow presets, as core's `theme.json` ships
	 * them. Declared unless a theme sets `settings.shadow.defaultPresets`
	 * to `false`, so `var:preset|shadow|natural` and friends resolve on a
	 * theme with no presets of its own (#900).
	 *
	 * @since 1.15.1
	 */
	public const DEFAULT_SHADOW_PRESETS = [
		[ 'slug' => 'natural', 'name' => 'Natural', 'shadow' => '6px 6px 9px rgba(0, 0, 0, 0.2)' ],
		[ 'slug' => 'deep', 'name' => 'Deep', 'shadow' => '12px 12px 50px rgba(0, 0, 0, 0.4)' ],
		[ 'slug' => 'sharp', 'name' => 'Sharp', 'shadow' => '6px 6px 0px rgba(0, 0, 0, 0.2)' ],
		[ 'slug' => 'outlined', 'name' => 'Outlined', 'shadow' => '6px 6px 0px -3px rgb(255, 255, 255), 6px 6px rgb(0, 0, 0)' ],
		[ 'slug' => 'crisp', 'name' => 'Crisp', 'shadow' => '6px 6px 0px rgb(0, 0, 0)' ],
	];

	/**
	 * Palette slugs whose `.has-{slug}-color` utility shares its name with
	 * a block-support marker class. See {@see self::colorUtilitySelector()}.
	 * Listed in emission order: `text` comes last so its fallback rule
	 * wins the cases markup alone can't disambiguate.
	 *
	 * @since 1.14.1
	 */
	public const MARKER_COLOR_SLUGS = [ 'link', 'border', 'text' ];

	/**
	 * Attribute selectors matching an inline `color:` declaration in the
	 * forms the renderers (`color: #fff; …`) and the block editor
	 * (`color:#fff;…`) serialize it, without matching `background-color`
	 * or `border-color`. See {@see self::colorUtilitySelector()}.
	 *
	 * @since 1.14.1
	 */
	public const INLINE_COLOR_ATTRIBUTE_SELECTORS = '[style^="color:"], [style*=" color:"], [style*=";color:"]';

	/**
	 * Package default layout tokens (#821). `contentSize` / `wideSize`
	 * match the editor's `__experimentalFeatures.layout`; `blockGap`
	 * matches the layout baseline's `var(--wp--style--block-gap, 24px)`
	 * fallback. Declared at zero specificity so any theme value wins.
	 *
	 * @since 1.12.1
	 */
	public const DEFAULT_LAYOUT = [
		'contentSize' => '720px',
		'wideSize'    => '1080px',
		'blockGap'    => '24px',
		'rootPadding' => '1.5rem',
	];
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
	 * Resolve every font size the front end must declare (#821, #852).
	 *
	 * Same layering as {@see self::effectivePalette()}: the package
	 * defaults stay declared under a theme's own `fontSizes` (theme
	 * entries win on slug), with host presets merged on top.
	 *
	 * @since 1.12.1
	 * @since 1.14.0 Keeps the package defaults when the theme ships its own sizes.
	 *
	 * @param  mixed  $themeFontSizes  The theme's `settings.typography.fontSizes`, if any.
	 *
	 * @return array<int, array{slug: string, size: string}>
	 */
	public static function effectiveFontSizes( mixed $themeFontSizes ): array
	{
		return self::layerPresetList(
			self::DEFAULT_FONT_SIZES,
			self::normaliseThemeList( $themeFontSizes, 'size' ),
			self::fromConfig()['fontSizes'],
			'size',
		);
	}

	/**
	 * Resolve every palette color the front end must declare (#821, #852).
	 *
	 * The editor offers the package defaults whenever it has no theme
	 * palette of its own to show, and saved content keeps referencing
	 * those slugs (`has-base-content-color`, `var:preset|color|primary`)
	 * after a host later passes a theme palette to the renderer. So the
	 * defaults always stay declared underneath the theme palette — theme
	 * entries win on a colliding slug — and host presets merge on top.
	 * A host `replace` list is the one exception: the editor never offers
	 * the defaults then, so neither layer below it is kept.
	 *
	 * @since 1.12.1
	 * @since 1.14.0 Keeps the package defaults when the theme ships its own palette.
	 *
	 * @param  mixed  $themePalette  The theme's `settings.color.palette`, if any.
	 *
	 * @return array<int, array{slug: string, color: string}>
	 */
	public static function effectivePalette( mixed $themePalette ): array
	{
		return self::layerPresetList(
			self::DEFAULT_PALETTE,
			self::normaliseThemeList( $themePalette, 'color' ),
			self::fromConfig()['palette'],
			'color',
		);
	}

	/**
	 * Resolve every font family the front end must declare (#872).
	 *
	 * Same layering as {@see self::effectivePalette()}: the package
	 * defaults stay declared under a theme's own `fontFamilies` (theme
	 * entries win on slug), with host presets merged on top.
	 *
	 * @since 1.15.0
	 *
	 * @param  mixed  $themeFontFamilies  The theme's `settings.typography.fontFamilies`, if any.
	 *
	 * @return array<int, array{slug: string, fontFamily: string}>
	 */
	public static function effectiveFontFamilies( mixed $themeFontFamilies ): array
	{
		return self::layerPresetList(
			self::DEFAULT_FONT_FAMILIES,
			self::normaliseThemeList( $themeFontFamilies, 'fontFamily' ),
			self::fromConfig()['fontFamilies'],
			'fontFamily',
		);
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
				fn ( array $entry ): ?array => self::normalisePaletteEntry( $entry ),
			),
			'fontSizes'    => self::normaliseList(
				$config['font_sizes'] ?? null,
				'size',
				fn ( array $entry ): ?array => self::normaliseSizedEntry( $entry, 'size' ),
			),
			'fontFamilies' => self::normaliseList(
				$config['font_families'] ?? null,
				'fontFamily',
				fn ( array $entry ): ?array => self::normaliseFontFamilyEntry( $entry ),
			),
			'spacingSizes' => self::normaliseList(
				$config['spacing_sizes'] ?? null,
				'size',
				fn ( array $entry ): ?array => self::normaliseSizedEntry( $entry, 'size' ),
			),
		];
	}

	/**
	 * Build the `:root { --wp--preset--spacing--*: …; }` block for every
	 * spacing size the editor's pickers offer (#814). `var:preset|spacing|*`
	 * picks reference these properties, which previously existed only when
	 * the theme shipped `spacingSizes` — on any other theme padding, margin
	 * and Block spacing presets resolved to nothing. Slugs go through
	 * {@see self::presetSlug()}, the one rule every preset declaration and
	 * reference site shares. Returns `''` when the list is empty (e.g. host
	 * `replace` mode with no entries).
	 *
	 * The block is a *fallback*: callers that also emit the resolved
	 * global-styles tree (theme.json + style variation + user row) must
	 * place it before that output so the resolved values win the cascade.
	 *
	 * @since 1.12.0
	 *
	 * @param  mixed  $themeSpacingSizes  The theme's `settings.spacing.spacingSizes`, if any.
	 */
	public static function spacingPresetsCss( mixed $themeSpacingSizes ): string
	{
		$declarations = [];

		foreach ( self::effectiveSpacingSizes( $themeSpacingSizes ) as $entry ) {
			$slug = self::presetSlug( $entry['slug'] );
			$size = trim( $entry['size'] );

			if ( '' === $slug || ! self::isSafeCssValue( $size ) ) {
				continue;
			}

			$declarations[] = sprintf( '--wp--preset--spacing--%s: %s;', $slug, $size );
		}

		return [] === $declarations ? '' : ":root {\n\t" . implode( "\n\t", $declarations ) . "\n}";
	}

	/**
	 * Declare WordPress's default shadow presets (#900).
	 *
	 * Mirrors WordPress: the five {@see self::DEFAULT_SHADOW_PRESETS} are
	 * declared unless `settings.shadow.defaultPresets` is `false`. A slug
	 * the theme declares itself is left out, so the theme's value wins
	 * wherever this block lands in the cascade.
	 *
	 * @since 1.15.1
	 *
	 * @param  mixed  $themeShadowSettings  The theme's `settings.shadow`, if any.
	 */
	public static function shadowPresetsCss( mixed $themeShadowSettings ): string
	{
		$shadow = is_array( $themeShadowSettings ) ? $themeShadowSettings : [];

		if ( false === ( $shadow['defaultPresets'] ?? true ) ) {
			return '';
		}

		$themeSlugs = [];

		foreach ( is_array( $shadow['presets'] ?? null ) ? $shadow['presets'] : [] as $entry ) {
			if ( is_array( $entry ) && is_string( $entry['slug'] ?? null ) ) {
				$themeSlugs[] = self::presetSlug( trim( $entry['slug'] ) );
			}
		}

		$declarations = [];

		foreach ( self::DEFAULT_SHADOW_PRESETS as $entry ) {
			if ( in_array( $entry['slug'], $themeSlugs, true ) ) {
				continue;
			}

			$declarations[] = sprintf( '--wp--preset--shadow--%s: %s;', $entry['slug'], $entry['shadow'] );
		}

		return [] === $declarations ? '' : ":root {\n\t" . implode( "\n\t", $declarations ) . "\n}";
	}

	/**
	 * Declare every font-size and palette preset the editor's pickers
	 * offer, plus the `.has-{slug}-*` classes that point at them (#821).
	 *
	 * The theme compiler only emits the presets a theme ships, so on a
	 * theme-less install a "Large" pick saved `has-large-font-size`
	 * with nothing behind it. Built from {@see self::effectiveFontSizes()}
	 * and {@see self::effectivePalette()}: the package defaults stay
	 * declared underneath a theme's own lists (theme entries win on a
	 * shared slug), with host presets merged on top.
	 *
	 * @since 1.12.1
	 * @since 1.14.0 Declares the package defaults under a theme's own lists (#852).
	 * @since 1.15.0 Declares font families and their `.has-{slug}-font-family` classes (#872).
	 * @since 1.15.1 Declares WordPress's default shadow presets (#900).
	 *
	 * @param  array<string, mixed>|null  $themeSettings  The theme's `settings`, if any.
	 */
	public static function presetDefaultsCss( ?array $themeSettings ): string
	{
		$declarations = [];
		$utilities    = [];
		$colorSlugs   = [];

		foreach ( self::effectiveFontSizes( $themeSettings['typography']['fontSizes'] ?? null ) as $entry ) {
			$slug = self::presetSlug( $entry['slug'] );

			if ( '' === $slug || ! self::isSafeCssValue( trim( $entry['size'] ) ) ) {
				continue;
			}

			$declarations[] = sprintf( '--wp--preset--font-size--%s: %s;', $slug, trim( $entry['size'] ) );
			$utilities[]    = sprintf( '.has-%1$s-font-size { font-size: var(--wp--preset--font-size--%1$s) !important; }', $slug );
		}

		foreach ( self::effectivePalette( $themeSettings['color']['palette'] ?? null ) as $entry ) {
			$slug = self::presetSlug( $entry['slug'] );

			if ( '' === $slug || ! self::isSafeCssValue( trim( $entry['color'] ) ) ) {
				continue;
			}

			$declarations[] = sprintf( '--wp--preset--color--%s: %s;', $slug, trim( $entry['color'] ) );
			$colorSlugs[]   = $slug;
			$utilities[]    = sprintf( '.has-%1$s-background-color { background-color: var(--wp--preset--color--%1$s) !important; }', $slug );
			$utilities[]    = sprintf( '.has-%1$s-border-color { border-color: var(--wp--preset--color--%1$s) !important; }', $slug );
		}

		array_push( $utilities, ...self::colorUtilityRules( $colorSlugs ) );

		foreach ( self::effectiveFontFamilies( $themeSettings['typography']['fontFamilies'] ?? null ) as $entry ) {
			$slug   = self::presetSlug( $entry['slug'] );
			$family = trim( $entry['fontFamily'] );

			if ( '' === $slug || ! self::isSafeFontFamilyValue( $family ) ) {
				continue;
			}

			$declarations[] = sprintf( '--wp--preset--font-family--%s: %s;', $slug, $family );
			$utilities[]    = sprintf( '.has-%1$s-font-family { font-family: var(--wp--preset--font-family--%1$s) !important; }', $slug );
		}

		$shadows = self::shadowPresetsCss( $themeSettings['shadow'] ?? null );

		if ( [] === $declarations ) {
			return $shadows;
		}

		$css = ":root {\n\t" . implode( "\n\t", $declarations ) . "\n}\n\n" . implode( "\n", $utilities );

		return '' === $shadows ? $css : $css . "\n\n" . $shadows;
	}

	/**
	 * Zero-specificity defaults for the layout custom properties block
	 * CSS reads: content / wide size, block gap and root padding (#821).
	 * `:where(:root)` means any theme `:root` declaration wins whatever
	 * the source order.
	 *
	 * @since 1.12.1
	 */
	public static function layoutDefaultsCss(): string
	{
		$layout  = self::DEFAULT_LAYOUT;
		$padding = $layout['rootPadding'];

		return ":where(:root) {\n"
			. "\t--wp--style--global--content-size: {$layout['contentSize']};\n"
			. "\t--wp--style--global--wide-size: {$layout['wideSize']};\n"
			. "\t--wp--style--block-gap: {$layout['blockGap']};\n"
			. "\t--wp--style--root--padding-top: {$padding};\n"
			. "\t--wp--style--root--padding-right: {$padding};\n"
			. "\t--wp--style--root--padding-bottom: {$padding};\n"
			. "\t--wp--style--root--padding-left: {$padding};\n"
			. '}';
	}

	/**
	 * The `.has-{slug}-color` rules for a palette, in emission order.
	 *
	 * Ordinary slugs get the plain WordPress utility. The `text`, `link`
	 * and `border` slugs produce a class that is also a block-support
	 * marker (`has-text-color`, `has-link-color`, `has-border-color`),
	 * which blocks carry for any custom text, link or border color, so a
	 * plain utility for them would force the slug's color onto blocks
	 * that never picked it. Each of those slugs gets two rules instead:
	 *
	 *  - `.has-{slug}-color-preset` — the exact pick. The Blade renderer
	 *    adds this class when a block picks the slug as its text color
	 *    (see {@see self::presetPickClass()}).
	 *  - a zero-specificity fallback ({@see self::colorUtilitySelector()})
	 *    for markup the renderer doesn't build, such as the editor canvas
	 *    and saved Gutenberg HTML, where the class set alone is the only
	 *    signal. The fallbacks come last, `text` after `link` / `border`,
	 *    so a block that picked `text` and also has a link or border color
	 *    keeps its text color.
	 *
	 * Shared by {@see self::presetDefaultsCss()} and the Blade
	 * `ThemeJsonTokensCompiler`; mirrored by cms-framework's
	 * `GlobalStylesEmitter` and the React / Vue renderers.
	 *
	 * @since 1.14.1
	 *
	 * @param  array<int, string>  $slugs  Normalised palette slugs, in palette order.
	 *
	 * @return array<int, string>
	 */
	public static function colorUtilityRules( array $slugs ): array
	{
		$rules = [];

		foreach ( array_unique( $slugs ) as $slug ) {
			if ( ! in_array( $slug, self::MARKER_COLOR_SLUGS, true ) ) {
				$rules[] = sprintf( '.has-%1$s-color { color: var(--wp--preset--color--%1$s) !important; }', $slug );
			}
		}

		foreach ( self::MARKER_COLOR_SLUGS as $slug ) {
			if ( in_array( $slug, $slugs, true ) ) {
				$rules[] = sprintf( '.%s { color: var(--wp--preset--color--%s) !important; }', self::presetPickClass( $slug ), $slug );
			}
		}

		foreach ( self::MARKER_COLOR_SLUGS as $slug ) {
			if ( in_array( $slug, $slugs, true ) ) {
				$rules[] = sprintf( '%s { color: var(--wp--preset--color--%s) !important; }', self::colorUtilitySelector( $slug ), $slug );
			}
		}

		return $rules;
	}

	/**
	 * The extra class a block carries when it picks `$slug` as its text
	 * color, or null when the slug's own `.has-{slug}-color` class is
	 * unambiguous. Only the marker slugs (`text`, `link`, `border`) need
	 * one; see {@see self::colorUtilityRules()}.
	 *
	 * @since 1.14.1
	 *
	 * @param  string  $slug  A normalised palette slug.
	 */
	public static function presetPickClass( string $slug ): ?string
	{
		return in_array( $slug, self::MARKER_COLOR_SLUGS, true ) ? 'has-' . $slug . '-color-preset' : null;
	}

	/**
	 * Selector for a palette slug's `.has-{slug}-color` utility.
	 *
	 * Ordinary slugs keep the plain class. For the marker slugs this is
	 * the fallback rule's selector (see {@see self::colorUtilityRules()}):
	 * it only matches a likely text-color pick of that slug — `link` /
	 * `border` also require `has-text-color` (every text pick adds it),
	 * elements with an inline `color:` (a custom color) are skipped — and
	 * `:where()` keeps it at zero specificity, so another palette slug's
	 * class or the exact `.has-{slug}-color-preset` rule wins.
	 *
	 * @since 1.14.1
	 *
	 * @param  string  $slug  A normalised palette slug.
	 */
	public static function colorUtilitySelector( string $slug ): string
	{
		$selector = '.has-' . $slug . '-color';

		if ( ! in_array( $slug, self::MARKER_COLOR_SLUGS, true ) ) {
			return $selector;
		}

		if ( 'text' !== $slug ) {
			$selector .= '.has-text-color';
		}

		return ':where(' . $selector . ':not(' . self::INLINE_COLOR_ATTRIBUTE_SELECTORS . '))';
	}

	/**
	 * Normalise a preset slug (or any `var:preset|…` segment) into its
	 * custom-property form — the one rule every preset declaration and
	 * reference site shares: split lower→upper camelCase boundaries with
	 * `-` (`brandPrimary` → `brand-primary`, as cms-framework's emitter
	 * and Gutenberg do), lowercase, then every character outside
	 * `[a-z0-9-]` becomes `-` (`big_gap` → `big-gap`). Runs of `-` are
	 * kept and nothing is trimmed (`a--b` stays `a--b`, `a__b` becomes
	 * `a--b`), matching how the declarations have always been written.
	 * Mirrored by the Blade `BlockSupports` / `ElementsSupport` reference
	 * expanders, the editor's `spacingPresetSlug()` and the React / Vue
	 * renderers' `presetSlug()`.
	 *
	 * @since 1.12.0
	 */
	public static function presetSlug( string $value ): string
	{
		$value = (string) preg_replace( '/([a-z])([A-Z])/', '$1-$2', $value );

		return (string) preg_replace( '/[^a-z0-9\-]/', '-', strtolower( $value ) );
	}

	/**
	 * Whether a preset value is safe to write inside a `<style>` element.
	 * Rejects anything that could close the declaration, rule, or tag
	 * (`; { } < >`), open or close a comment that swallows the following
	 * rules, smuggle a CSS escape (`\`), open a string (`"` / `'`), break
	 * the line (newlines / control characters), or leave a parenthesis or
	 * bracket unbalanced or crossed (`calc(1rem` or `x [` would swallow
	 * every later rule; see {@see CssValue::isBalanced()}). Mirrors
	 * `isSafeSpacingValue()` in the editor's `spacing-preset-styles.tsx`.
	 *
	 * @since 1.12.0
	 * @since 1.15.0 Rejects unbalanced or crossed `[ ]`.
	 */
	public static function isSafeCssValue( string $value ): bool
	{
		if ( '' === $value || 1 === preg_match( '#[;{}<>\\\\"\'\x00-\x1F\x7F]#', $value ) ) {
			return false;
		}

		return CssValue::isBalanced( $value );
	}

	/**
	 * Whether a font-family stack is safe to write inside a `<style>`
	 * element (#872). Family names are routinely quoted
	 * (`"Segoe UI", sans-serif`), which {@see self::isSafeCssValue()}
	 * rejects, so quoted strings are allowed here as long as each one
	 * closes — an unterminated string would swallow the rules after it.
	 * Parentheses are rejected, and brackets outside strings must
	 * balance ({@see CssValue::isPrintable()}). Everything else that
	 * could close the declaration, rule, or tag is still rejected.
	 *
	 * @since 1.15.0
	 */
	public static function isSafeFontFamilyValue( string $value ): bool
	{
		if ( '' === $value || 1 === preg_match( '#[()]#', $value ) ) {
			return false;
		}

		return CssValue::isPrintable( $value );
	}

	/**
	 * The active theme's *resolved* `settings.spacing.spacingSizes` —
	 * theme.json merged with the active style variation and the user's
	 * Global Styles row via cms-framework's `GlobalStylesResolver` — so
	 * the fallback block is built from the same values the emitter
	 * declares. Falls back to the raw theme manifest when the resolver
	 * isn't bound, and to `null` when cms-framework isn't installed / no
	 * theme is active.
	 *
	 * @since 1.12.0
	 */
	public static function activeThemeSpacingSizes(): mixed
	{
		return self::activeThemeSettings()['spacing']['spacingSizes'] ?? null;
	}

	/**
	 * The active theme's *resolved* `settings` (see
	 * {@see self::activeThemeSpacingSizes()} for the resolution order),
	 * or `null` when cms-framework isn't installed / no theme is active.
	 *
	 * @since 1.12.1
	 *
	 * @return array<string, mixed>|null
	 */
	public static function activeThemeSettings(): ?array
	{
		$resolver = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\GlobalStylesResolver';

		if ( class_exists( $resolver ) && app()->bound( $resolver ) ) {
			try {
				$resolved = app( $resolver )->resolve();

				if ( null === $resolved ) {
					return null;
				}

				return is_array( $resolved->settings ?? null ) ? $resolved->settings : [];
			} catch ( Throwable ) {
				// Resolver unusable (e.g. Global Styles table not migrated
				// yet) — fall through to the raw theme manifest.
			}
		}

		$themeManager = 'ArtisanPackUI\\CMSFramework\\Modules\\Themes\\Managers\\ThemeManager';

		if ( ! class_exists( $themeManager ) || ! app()->bound( $themeManager ) ) {
			return null;
		}

		$theme = app( $themeManager )->getActiveTheme();

		if ( ! is_array( $theme ) ) {
			return null;
		}

		return is_array( $theme['settings'] ?? null ) ? $theme['settings'] : [];
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
	 *
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
	 *
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
	 *
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
		return self::normaliseThemeList( $sizes, 'size' );
	}

	/**
	 * Normalise a theme preset list keyed by `$valueKey` (`size`,
	 * `color`) the way {@see self::normaliseThemeSpacingSizes()} does.
	 *
	 * @since 1.12.1
	 *
	 * @return array<int, array<string, string>>|null
	 */
	protected static function normaliseThemeList( mixed $list, string $valueKey ): ?array
	{
		if ( ! is_array( $list ) || [] === $list ) {
			return null;
		}

		$out  = [];
		$seen = [];

		foreach ( $list as $entry ) {
			if ( ! is_array( $entry ) || ! is_string( $entry['slug'] ?? null ) || ! is_string( $entry[ $valueKey ] ?? null ) ) {
				continue;
			}

			$slug = strtolower( trim( $entry['slug'] ) );

			if ( '' === $slug || isset( $seen[ $slug ] ) ) {
				continue;
			}

			$seen[ $slug ] = true;
			$out[]         = [ 'slug' => $slug, $valueKey => $entry[ $valueKey ] ];
		}

		return [] === $out ? null : $out;
	}

	/**
	 * Layer package defaults, a theme list, and a host list (#852): the
	 * theme overrides colliding default slugs in place and appends the
	 * rest, then the host list merges on top per its mode. A host
	 * `replace` list drops the defaults (the editor never offers them
	 * then) but, as before, an empty one leaves the theme list intact.
	 *
	 * @since 1.14.0
	 *
	 * @param  array<int, array<string, string>>  $defaults
	 * @param  array<int, array<string, string>>|null  $theme
	 * @param  array{mode: string, entries: array<int, array<string, string>>}  $host
	 * @param  string  $valueKey  Value field (`size`, `color`).
	 *
	 * @return array<int, array<string, string>>
	 */
	protected static function layerPresetList( array $defaults, ?array $theme, array $host, string $valueKey ): array
	{
		if ( null === $theme ) {
			return self::mergeSizedList( $defaults, $host, $valueKey );
		}

		if ( 'replace' === $host['mode'] ) {
			return [] === $host['entries'] ? $theme : self::mergeSizedList( $theme, $host, $valueKey );
		}

		$base = self::mergeSizedList( $defaults, [ 'mode' => 'append', 'entries' => $theme ], $valueKey );

		return [] === $host['entries'] ? $base : self::mergeSizedList( $base, $host, $valueKey );
	}

	/**
	 * Merge a host list into a base list per its mode. Mirrors the JS
	 * `mergePresetList()`: `replace` returns the host entries outright;
	 * `append` overrides colliding slugs in place and appends the rest.
	 *
	 * @since 1.12.0
	 *
	 * @param  array<int, array<string, string>>  $base
	 * @param  array{mode: string, entries: array<int, array<string, string>>}  $host
	 * @param  string  $valueKey  Value field (`size`, `color`).
	 *
	 * @return array<int, array<string, string>>
	 */
	protected static function mergeSizedList( array $base, array $host, string $valueKey = 'size' ): array
	{
		$hostEntries = array_map(
			static fn ( array $entry ): array => [ 'slug' => $entry['slug'], $valueKey => $entry[ $valueKey ] ],
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
