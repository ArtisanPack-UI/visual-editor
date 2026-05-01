<?php

/**
 * Resolved template-part value object.
 *
 * Adds an `area` field to the {@see ResolvedTemplate} shape. Areas are
 * the closed list `header | footer | sidebar | general` for V1 — open-ended
 * user-defined areas are deferred to V1.1 per plan 14 §8.
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

class ResolvedTemplatePart extends ResolvedTemplate
{
	/**
	 * @since 1.0.0
	 */
	protected const FILTER_NAME = 'ap.visual-editor.template-parts';

	/**
	 * V1 closed list of valid template-part areas. Mirrored from
	 * cms-framework's `TemplatePart::AREAS`.
	 *
	 * @since 1.0.0
	 */
	public const AREAS = [ 'header', 'footer', 'sidebar', 'general' ];

	/**
	 * @since 1.0.0
	 *
	 * @param  string  $area  Template-part area; must be one of {@see self::AREAS}.
	 */
	public function __construct(
		string $slug,
		string $theme,
		string $title,
		string $description,
		string $status,
		string $source,
		string $rawContent,
		array $blocks,
		bool $hasThemeFile,
		bool $isCustom,
		?int $wpId,
		?int $authorId,
		?string $modifiedAt,
		public readonly string $area,
	) {
		parent::__construct(
			slug         : $slug,
			theme        : $theme,
			title        : $title,
			description  : $description,
			status       : $status,
			source       : $source,
			rawContent   : $rawContent,
			blocks       : $blocks,
			hasThemeFile : $hasThemeFile,
			isCustom     : $isCustom,
			wpId         : $wpId,
			authorId     : $authorId,
			modifiedAt   : $modifiedAt,
		);
	}

	/**
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data
	 */
	public static function fromArray( array $data ): self
	{
		$slug = self::requireString( $data, 'slug' );
		$area = self::requireString( $data, 'area', $slug );

		if ( ! in_array( $area, self::AREAS, true ) ) {
			throw SiteEditorRegistrationException::invalidField(
				static::FILTER_NAME,
				$slug,
				'area',
				'one of: ' . implode( ', ', self::AREAS ),
			);
		}

		// Same defensive `content.*` guards as ResolvedTemplate — see the
		// comments there for the rationale (string-offset coercion silently
		// corrupting `rawContent` to a single character if `content` is a
		// string; `(string) $array` corrupting it to `"Array"` if a value
		// is non-scalar).
		$content = is_array( $data['content'] ?? null ) ? $data['content'] : [];

		$rawFallback    = self::coerceScalarString( $content['raw'] ?? null ) ?? '';
		$blocksFallback = is_array( $content['blocks'] ?? null ) ? $content['blocks'] : [];

		return new self(
			slug         : $slug,
			theme        : self::requireString( $data, 'theme', $slug ),
			title        : self::optionalString( $data, 'title', '' ),
			description  : self::optionalString( $data, 'description', '' ),
			status       : self::optionalString( $data, 'status', 'publish' ),
			source       : self::requireSourceEnum( $data, $slug ),
			rawContent   : self::optionalString( $data, 'raw_content', $rawFallback ),
			blocks       : self::optionalArray( $data, 'blocks', $blocksFallback ),
			hasThemeFile : (bool) ( $data['has_theme_file'] ?? false ),
			isCustom     : (bool) ( $data['is_custom'] ?? false ),
			wpId         : isset( $data['wp_id'] ) ? (int) $data['wp_id'] : null,
			authorId     : isset( $data['author_id'] ) ? (int) $data['author_id'] : null,
			modifiedAt   : isset( $data['modified_at'] ) ? (string) $data['modified_at'] : null,
			area         : $area,
		);
	}
}
