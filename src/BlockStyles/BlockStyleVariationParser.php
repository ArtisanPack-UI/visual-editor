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
 *    without `blockTypes` are theme style variations and are skipped.
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
	 * Returns nothing for a partial without `blockTypes`.
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
		$blockTypes = $partial['blockTypes'] ?? null;

		if ( ! is_array( $blockTypes ) || [] === $blockTypes ) {
			return [];
		}

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
	 * a file is added, removed or modified.
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

		$files = glob( rtrim( $directory, '/' ) . '/*.json' );

		if ( false === $files ) {
			return [];
		}

		sort( $files );

		// Partials are re-read only when one changes: the cache key
		// covers every file's path and modification time.
		$signature = [];

		foreach ( $files as $file ) {
			$signature[ $file ] = @filemtime( $file );
		}

		$key = 'artisanpack.visual-editor.block-styles.' . hash( 'xxh3', $directory . '|' . serialize( $signature ) );

		if ( function_exists( 'app' ) && app()->bound( 'cache' ) ) {
			return app( 'cache' )->rememberForever( $key, fn (): array => $this->parseFiles( $files ) );
		}

		return $this->parseFiles( $files );
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
