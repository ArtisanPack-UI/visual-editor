<?php

/**
 * Resolved pattern value object.
 *
 * Site-editor patterns can be theme-shipped (`source: 'theme'`) or
 * user-authored (`source: 'user'`). When `synced` is true, the pattern
 * tracks edits — equivalent to `wp_block` in WP REST. When false, the
 * pattern is a snapshot — equivalent to `wp_block_pattern`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SiteEditor\Resolution;

use ArtisanPackUI\VisualEditor\SiteEditor\Exceptions\SiteEditorRegistrationException;

class ResolvedPattern
{
	/**
	 * @since 1.0.0
	 */
	protected const FILTER_NAME = 'ap.visual-editor.patterns';

	/**
	 * @since 1.0.0
	 *
	 * @param  string  $slug          Stable identifier; for user patterns this should
	 *                                carry a `user/` prefix per plan 14 §5.6.
	 * @param  string  $title         Display title.
	 * @param  string  $rawContent    Serialized block markup.
	 * @param  array<int, array<string, mixed>>  $blocks  Parsed block tree.
	 * @param  string  $source        `'theme'` or `'user'`.
	 * @param  bool    $synced        True for synced (live-edited) patterns.
	 * @param  array<int, string>  $categories  Pattern category slugs.
	 * @param  array<int, string>  $blockTypes  WP `blockTypes` hint for the inserter.
	 * @param  int|null  $wpId        DB row id for user-source patterns; null otherwise.
	 */
	public function __construct(
		public readonly string $slug,
		public readonly string $title,
		public readonly string $rawContent,
		public readonly array $blocks,
		public readonly string $source,
		public readonly bool $synced,
		public readonly array $categories,
		public readonly array $blockTypes,
		public readonly ?int $wpId,
	) {
	}

	/**
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data
	 */
	public static function fromArray( array $data ): self
	{
		$slug   = self::requireString( $data, 'slug' );
		$source = $data['source'] ?? 'theme';

		if ( ! in_array( $source, [ 'theme', 'user' ], true ) ) {
			throw SiteEditorRegistrationException::invalidField(
				self::FILTER_NAME,
				$slug,
				'source',
				"'theme' or 'user'",
			);
		}

		// Normalize the blocks fallback through an explicit is_array check on
		// each candidate so a non-array value at any layer of the WP-shape
		// envelope can't reach the typed `array $blocks` constructor param
		// and raise a TypeError under strict_types.
		if ( is_array( $data['blocks'] ?? null ) ) {
			$blocks = $data['blocks'];
		} elseif ( is_array( $data['content']['blocks'] ?? null ) ) {
			$blocks = $data['content']['blocks'];
		} else {
			$blocks = [];
		}

		return new self(
			slug       : $slug,
			// Patterns require a title — WP REST `wp_block` enforces it and
			// titleless patterns are unfindable in the inserter UI. Mirrors
			// the slug check above so misconfigured contributors surface a
			// clear error on first read instead of silently shipping empty
			// titles.
			title      : self::requireString( $data, 'title', $slug ),
			rawContent : (string) ( $data['raw_content'] ?? $data['content']['raw'] ?? '' ),
			blocks     : $blocks,
			source     : (string) $source,
			synced     : (bool) ( $data['synced'] ?? false ),
			categories : array_values( array_filter(
				is_array( $data['categories'] ?? null ) ? $data['categories'] : [],
				'is_string',
			) ),
			blockTypes : array_values( array_filter(
				is_array( $data['block_types'] ?? null ) ? $data['block_types'] : [],
				'is_string',
			) ),
			wpId       : isset( $data['wp_id'] ) ? (int) $data['wp_id'] : null,
		);
	}

	/**
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data
	 */
	protected static function requireString( array $data, string $field, string $entryKey = '(unknown)' ): string
	{
		if ( ! isset( $data[ $field ] ) ) {
			throw SiteEditorRegistrationException::missingRequiredField( self::FILTER_NAME, $entryKey, $field );
		}

		if ( ! is_string( $data[ $field ] ) ) {
			throw SiteEditorRegistrationException::invalidField( self::FILTER_NAME, $entryKey, $field, 'a string' );
		}

		return $data[ $field ];
	}
}
