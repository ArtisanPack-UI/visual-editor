<?php

/**
 * Site-editor template-part resolver.
 *
 * Consumes the merged `ap.visual-editor.template-parts` filter result.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 *
 * @extends AbstractMapResolver<ResolvedTemplatePart>
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SiteEditor\Resolution;

class TemplatePartResolver extends AbstractMapResolver
{
	/**
	 * @since 1.0.0
	 */
	protected static function filterName(): string
	{
		return 'ap.visual-editor.template-parts';
	}

	/**
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $entry
	 */
	protected static function normalizeEntry( string $key, array $entry ): ResolvedTemplatePart
	{
		$entry['slug'] = $entry['slug'] ?? $key;

		return ResolvedTemplatePart::fromArray( $entry );
	}

	/**
	 * @since 1.5.0
	 *
	 * @param  ResolvedTemplatePart  $entry
	 */
	protected static function identifierOf( object $entry ): string
	{
		return $entry->slug;
	}
}
