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
	 * @param  array<int, string>|null  $postTypes  Post-type slug whitelist (Gutenberg
	 *                                convention). `null` means the pattern is available
	 *                                to every post-type context. An empty array means
	 *                                the pattern was explicitly registered with a scope
	 *                                that matches nothing — treated the same as an
	 *                                unmatchable filter, not as "available everywhere",
	 *                                so a misregistered scope surfaces as "no matches"
	 *                                instead of leaking into every context.
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
		public readonly ?array $postTypes = null,
	) {
	}

	/**
	 * True when this pattern is available in the given post-type context.
	 *
	 * A pattern with a null `postTypes` matches every context (unscoped —
	 * the default). A pattern with an array `postTypes` matches only when
	 * the requested slug is present.
	 *
	 * Normalizes the input the same way {@see normalizePostTypes()}
	 * normalizes stored slugs (lower-case, trimmed) so callers that
	 * pass a mixed-case or padded slug — e.g. `matchesPostType('Page')`
	 * — don't silently miss a match. The `PatternController` already
	 * lower-cases the query param, but this keeps the method safe for
	 * any other consumer that reaches for it directly.
	 *
	 * @since 1.4.0
	 */
	public function matchesPostType( string $postType ): bool
	{
		if ( null === $this->postTypes ) {
			return true;
		}

		return in_array( strtolower( trim( $postType ) ), $this->postTypes, true );
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
		$content = is_array( $data['content'] ?? null ) ? $data['content'] : [];

		if ( is_array( $data['blocks'] ?? null ) ) {
			$blocks = $data['blocks'];
		} elseif ( is_array( $content['blocks'] ?? null ) ) {
			$blocks = $content['blocks'];
		} else {
			$blocks = [];
		}

		// Mirror the same defensive pattern for `content.raw`. A string-shaped
		// `$data['content']` would let `$data['content']['raw']` index into
		// the string's offsets (PHP coerces 'raw' → int 0 → returns the first
		// character), silently corrupting `rawContent` to a single byte. The
		// is_array guard above prevents that; coerceScalarString below
		// additionally guards against a non-scalar `content.raw` (e.g. an
		// array) stringifying to the literal `"Array"`.
		$rawFromContent = self::coerceScalarString( $content['raw'] ?? null ) ?? '';

		// Top-level `raw_content` may also arrive non-scalar from a
		// misconfigured contributor; only adopt it when it cleanly coerces
		// to a string, otherwise fall through to the nested fallback.
		$rawContent = self::coerceScalarString( $data['raw_content'] ?? null ) ?? $rawFromContent;

		return new self(
			slug       : $slug,
			// Patterns require a title — WP REST `wp_block` enforces it and
			// titleless patterns are unfindable in the inserter UI. Mirrors
			// the slug check above so misconfigured contributors surface a
			// clear error on first read instead of silently shipping empty
			// titles.
			title      : self::requireString( $data, 'title', $slug ),
			rawContent : $rawContent,
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
			postTypes  : self::normalizePostTypes( $data['post_types'] ?? null ),
		);
	}

	/**
	 * Normalize the raw `post_types` field into either `null` (omitted /
	 * unrecognized) or a de-duplicated list of non-empty string slugs.
	 *
	 * Any non-array value collapses to `null` so a malformed contributor
	 * entry defaults to "available everywhere" rather than crashing the
	 * resolver. Non-string members are filtered out; the remaining slugs
	 * are lower-cased for stable comparison against the requested
	 * post-type in {@see matchesPostType()}.
	 *
	 * @since 1.4.0
	 *
	 * @return array<int, string>|null
	 */
	protected static function normalizePostTypes( mixed $raw ): ?array
	{
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$slugs = [];

		foreach ( $raw as $candidate ) {
			if ( ! is_string( $candidate ) ) {
				continue;
			}

			$slug = strtolower( trim( $candidate ) );

			if ( '' === $slug || in_array( $slug, $slugs, true ) ) {
				continue;
			}

			$slugs[] = $slug;
		}

		return $slugs;
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

	/**
	 * Mirror of {@see ResolvedTemplate::coerceScalarString()}. Returns the
	 * value unchanged if it's already a string, casts other scalars
	 * (int/float/bool) to string, and returns null for arrays / objects /
	 * null. Without this guard, a non-scalar `content.raw` (e.g. an array)
	 * would stringify to the literal `"Array"` and ship as `rawContent`.
	 *
	 * @since 1.0.0
	 */
	protected static function coerceScalarString( mixed $value ): ?string
	{
		if ( is_string( $value ) ) {
			return $value;
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return null;
	}
}
