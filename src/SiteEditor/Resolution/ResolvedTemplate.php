<?php

/**
 * Resolved template value object.
 *
 * Carries the merged authoritative content for a single template slug, in
 * the shape H6's WP-style REST adapters consume. Constructed lazily by
 * {@see TemplateResolver} from raw filter arrays.
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

class ResolvedTemplate
{
	/**
	 * The filter slug that produced this entry — used in exceptions to point
	 * back at the misconfigured contributor.
	 *
	 * @since 1.0.0
	 */
	protected const FILTER_NAME = 'ap.visual-editor.templates';

	/**
	 * @since 1.0.0
	 *
	 * @param  string  $slug          Stable identifier within the active theme.
	 * @param  string  $theme         Active theme slug.
	 * @param  string  $title         Display title.
	 * @param  string  $description   Display description.
	 * @param  string  $status        WP status (`'publish'` etc).
	 * @param  string  $source        `'db'` or `'theme'`.
	 * @param  string  $rawContent    The serialized block-markup string.
	 *                                Empty for DB-stored entities (per the
	 *                                cms-framework convention) and populated
	 *                                for theme files.
	 * @param  array<int, array<string, mixed>>  $blocks  The parsed block
	 *                                tree. Populated for DB-stored entities;
	 *                                empty for theme files.
	 * @param  bool    $hasThemeFile  True when a theme file backs this slug.
	 * @param  bool    $isCustom      True when the entity has no theme-file backing.
	 * @param  int|null  $wpId        DB row id, or null when only a theme file backs.
	 * @param  int|null  $authorId    Author user id, or null.
	 * @param  string|null  $modifiedAt  ISO-8601 last-modified timestamp.
	 */
	public function __construct(
		public readonly string $slug,
		public readonly string $theme,
		public readonly string $title,
		public readonly string $description,
		public readonly string $status,
		public readonly string $source,
		public readonly string $rawContent,
		public readonly array $blocks,
		public readonly bool $hasThemeFile,
		public readonly bool $isCustom,
		public readonly ?int $wpId,
		public readonly ?int $authorId,
		public readonly ?string $modifiedAt,
	) {
	}

	/**
	 * Build from a raw filter-array entry. Throws lazily on missing required
	 * fields or invalid types so misconfigured filter contributors surface a
	 * clear error on the editor's first request.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data
	 */
	public static function fromArray( array $data ): self
	{
		$slug = self::requireString( $data, 'slug' );

		return new self(
			slug         : $slug,
			theme        : self::requireString( $data, 'theme', $slug ),
			title        : self::optionalString( $data, 'title', '' ),
			description  : self::optionalString( $data, 'description', '' ),
			status       : self::optionalString( $data, 'status', 'publish' ),
			source       : self::requireSourceEnum( $data, $slug ),
			rawContent   : self::optionalString( $data, 'raw_content', $data['raw_content'] ?? $data['content']['raw'] ?? '' ),
			blocks       : self::optionalArray( $data, 'blocks', $data['content']['blocks'] ?? [] ),
			hasThemeFile : (bool) ( $data['has_theme_file'] ?? false ),
			isCustom     : (bool) ( $data['is_custom'] ?? false ),
			wpId         : isset( $data['wp_id'] ) ? (int) $data['wp_id'] : null,
			authorId     : isset( $data['author_id'] ) ? (int) $data['author_id'] : null,
			modifiedAt   : isset( $data['modified_at'] ) ? (string) $data['modified_at'] : null,
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
			throw SiteEditorRegistrationException::missingRequiredField( static::FILTER_NAME, $entryKey, $field );
		}

		if ( ! is_string( $data[ $field ] ) ) {
			throw SiteEditorRegistrationException::invalidField( static::FILTER_NAME, $entryKey, $field, 'a string' );
		}

		return $data[ $field ];
	}

	/**
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data
	 */
	protected static function optionalString( array $data, string $field, string $default ): string
	{
		if ( ! array_key_exists( $field, $data ) ) {
			return $default;
		}

		if ( null === $data[ $field ] ) {
			return $default;
		}

		return is_string( $data[ $field ] ) ? $data[ $field ] : $default;
	}

	/**
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected static function optionalArray( array $data, string $field, mixed $fallback ): array
	{
		$value = $data[ $field ] ?? $fallback;

		return is_array( $value ) ? $value : [];
	}

	/**
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data
	 */
	protected static function requireSourceEnum( array $data, string $entryKey ): string
	{
		$source = $data['source'] ?? 'theme';

		if ( ! in_array( $source, [ 'db', 'theme' ], true ) ) {
			throw SiteEditorRegistrationException::invalidField(
				static::FILTER_NAME,
				$entryKey,
				'source',
				"'db' or 'theme'",
			);
		}

		return (string) $source;
	}
}
