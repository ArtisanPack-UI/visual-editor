<?php

/**
 * Reads WordPress-compatible block style variations from theme data (#876).
 *
 * Two sources, matching theme.json v3:
 *
 *  - `styles.blocks.{block}.variations.{slug}` — a style node per
 *    variation, declared inline in `theme.json`. An optional `title`
 *    key on the node becomes the picker label.
 *  - `styles/*.json` partials that declare `blockTypes` — one variation
 *    (`slug`, `title`, `styles`) shared by every listed block. Partials
 *    without `blockTypes` are theme style variations and are skipped
 *    ({@see self::isBlockScoped()}).
 *
 * Each variation becomes a `{block, slug, label, style, isDefault}`
 * entry, the same shape {@see BlockStyleRegistry} stores.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\BlockStyles;

use JsonException;

class BlockStyleVariationParser
{
	/**
	 * Prefix of the per-directory cache key {@see self::fromDirectory()} uses.
	 *
	 * @since 1.15.0
	 */
	public const CACHE_PREFIX = 'artisanpack.visual-editor.block-styles.';

	/**
	 * Variations declared under `styles.blocks.*.variations`.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $styles  The theme.json `styles` node.
	 *
	 * @return list<array{block: string, slug: string, label: string, style: array<string, mixed>, isDefault: bool}>
	 */
	public function fromStyles( array $styles ): array
	{
		$blocks  = is_array( $styles['blocks'] ?? null ) ? $styles['blocks'] : [];
		$entries = [];

		foreach ( $blocks as $blockName => $node ) {
			$block      = is_string( $blockName ) ? self::normaliseBlockName( $blockName ) : null;
			$variations = is_array( $node ) && is_array( $node['variations'] ?? null ) ? $node['variations'] : [];

			if ( null === $block ) {
				continue;
			}

			foreach ( $variations as $variationSlug => $style ) {
				$slug = is_string( $variationSlug ) ? self::normaliseSlug( $variationSlug ) : null;

				if ( null === $slug || ! is_array( $style ) ) {
					continue;
				}

				$title = is_string( $style['title'] ?? null ) ? trim( $style['title'] ) : '';
				unset( $style['title'] );

				$entries[] = $this->entry( $block, $slug, $title, $style );
			}
		}

		return $entries;
	}

	/**
	 * Variations declared by one decoded `styles/*.json` partial.
	 * Returns nothing for a partial without `blockTypes` (a theme style
	 * variation), nor for one whose `blockTypes` isn't a list of valid
	 * block names: that partial is still block-scoped, so it's never a
	 * theme style variation, but it has no block to apply to.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $partial   Decoded partial.
	 * @param  string                $fallback  Slug to use when the partial omits one (its filename).
	 *
	 * @return list<array{block: string, slug: string, label: string, style: array<string, mixed>, isDefault: bool}>
	 */
	public function fromPartial( array $partial, string $fallback = '' ): array
	{
		if ( ! self::isBlockScoped( $partial ) || ! is_array( $partial['blockTypes'] ) ) {
			return [];
		}

		$blockTypes = $partial['blockTypes'];

		$slug = self::normaliseSlug( is_string( $partial['slug'] ?? null ) ? $partial['slug'] : $fallback );

		if ( null === $slug ) {
			return [];
		}

		$title   = is_string( $partial['title'] ?? null ) ? trim( $partial['title'] ) : '';
		$style   = is_array( $partial['styles'] ?? null ) ? $partial['styles'] : [];
		$entries = [];

		foreach ( $blockTypes as $blockName ) {
			$block = is_string( $blockName ) ? self::normaliseBlockName( $blockName ) : null;

			if ( null !== $block ) {
				$entries[] = $this->entry( $block, $slug, $title, $style );
			}
		}

		return $entries;
	}

	/**
	 * Variations from every `*.json` partial in a directory. Unreadable
	 * or malformed files are skipped. The parsed result is cached until
	 * a file is added, removed or its contents change.
	 *
	 * The cache holds one entry per directory, storing the partials'
	 * signature next to the parsed variations, so editing a partial
	 * replaces the entry instead of leaving the old one behind.
	 *
	 * @since 1.15.0
	 *
	 * @return list<array{block: string, slug: string, label: string, style: array<string, mixed>, isDefault: bool}>
	 */
	public function fromDirectory( string $directory ): array
	{
		if ( '' === $directory || ! is_dir( $directory ) ) {
			return [];
		}

		$files = glob( self::escapeGlob( rtrim( $directory, '/' ) ) . '/*.json' );

		if ( false === $files ) {
			return [];
		}

		sort( $files );

		if ( ! function_exists( 'app' ) || ! app()->bound( 'cache' ) ) {
			return $this->parseFiles( $files );
		}

		// Partials are re-parsed only when one changes: the signature
		// covers every file's path and a hash of its contents, so an edit
		// inside the filesystem's mtime resolution still busts it.
		$signature = [];

		foreach ( $files as $file ) {
			$signature[ $file ] = is_readable( $file ) ? hash_file( 'xxh3', $file ) : false;
		}

		$signature = hash( 'xxh3', serialize( $signature ) );
		$key       = self::CACHE_PREFIX . hash( 'xxh3', $directory );
		$cache     = app( 'cache' );
		$cached    = $cache->get( $key );

		if ( is_array( $cached ) && ( $cached['signature'] ?? null ) === $signature && is_array( $cached['entries'] ?? null ) ) {
			return $cached['entries'];
		}

		$entries = $this->parseFiles( $files );

		$cache->forever( $key, [ 'signature' => $signature, 'entries' => $entries ] );

		return $entries;
	}

	/**
	 * Escape the `glob()` wildcards (`*`, `?`, `[`) in a literal path, so
	 * a directory whose name contains them is matched as written. Each is
	 * wrapped in a one-character class (`[*]`) rather than backslashed,
	 * which `glob()` doesn't honour on Windows.
	 *
	 * @since 1.15.0
	 */
	public static function escapeGlob( string $path ): string
	{
		return strtr( $path, [ '[' => '[[]', '*' => '[*]', '?' => '[?]' ] );
	}

	/**
	 * Whether a decoded `styles/*.json` partial is a block style variation
	 * rather than a theme style variation: it declares `blockTypes`,
	 * whatever the value (`[]` and a bare string count; `null` doesn't).
	 * Matches WordPress's `WP_Theme_JSON_Resolver::style_variation_has_scope()`
	 * and cms-framework's `GlobalStylesResolver::isBlockStyleVariation()`,
	 * which keeps these partials out of the theme style variation list.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $partial  Decoded partial.
	 */
	public static function isBlockScoped( array $partial ): bool
	{
		return isset( $partial['blockTypes'] );
	}

	/**
	 * `namespace/name` in lowercase, or `null` when malformed.
	 *
	 * @since 1.15.0
	 */
	public static function normaliseBlockName( string $block ): ?string
	{
		$block = strtolower( trim( $block ) );

		return 1 === preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $block ) ? $block : null;
	}

	/**
	 * A slug safe for an `is-style-{slug}` class, or `null` when empty.
	 * Mirrors the kebab-casing the preset slugs get.
	 *
	 * @since 1.15.0
	 */
	public static function normaliseSlug( string $slug ): ?string
	{
		$slug = (string) preg_replace( '/([a-z])([A-Z])/', '$1-$2', trim( $slug ) );
		$slug = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $slug ) ), '-' );

		return '' !== $slug ? $slug : null;
	}

	/**
	 * `hollow-card` → `Hollow card`, for variations without a title.
	 *
	 * @since 1.15.0
	 */
	public static function labelFromSlug( string $slug ): string
	{
		return ucfirst( str_replace( '-', ' ', $slug ) );
	}

	/**
	 * Variations from a list of partial files.
	 *
	 * @since 1.15.0
	 *
	 * @param  list<string>  $files
	 *
	 * @return list<array{block: string, slug: string, label: string, style: array<string, mixed>, isDefault: bool}>
	 */
	protected function parseFiles( array $files ): array
	{
		$entries = [];

		foreach ( $files as $file ) {
			$contents = is_readable( $file ) ? file_get_contents( $file ) : false;

			if ( false === $contents ) {
				continue;
			}

			try {
				$decoded = json_decode( $contents, true, 512, JSON_THROW_ON_ERROR );
			} catch ( JsonException ) {
				continue;
			}

			if ( is_array( $decoded ) ) {
				array_push( $entries, ...$this->fromPartial( $decoded, basename( $file, '.json' ) ) );
			}
		}

		return $entries;
	}

	/**
	 * One normalised variation entry.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $style
	 *
	 * @return array{block: string, slug: string, label: string, style: array<string, mixed>, isDefault: bool}
	 */
	protected function entry( string $block, string $slug, string $title, array $style ): array
	{
		return [
			'block'     => $block,
			'slug'      => $slug,
			'label'     => '' !== $title ? $title : self::labelFromSlug( $slug ),
			'style'     => $style,
			'isDefault' => false,
		];
	}
}
