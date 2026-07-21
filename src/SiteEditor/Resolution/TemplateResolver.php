<?php

/**
 * Site-editor template resolver.
 *
 * Consumes the merged `ap.visualEditor.templates` filter result and exposes
 * a stable read surface to H6's WP-style REST adapters. Has no awareness of
 * where the data came from — that's cms-framework's H1 resolution job.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 *
 * @extends AbstractMapResolver<ResolvedTemplate>
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SiteEditor\Resolution;

class TemplateResolver extends AbstractMapResolver
{
	/**
	 * @since 1.0.0
	 */
	protected static function filterName(): string
	{
		return 'ap.visualEditor.templates';
	}

	/**
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $entry
	 */
	protected static function normalizeEntry( string $key, array $entry ): ResolvedTemplate
	{
		// Default `slug` to the map key if the contributor omitted it.
		$entry['slug'] = $entry['slug'] ?? $key;

		return ResolvedTemplate::fromArray( $entry );
	}

	/**
	 * @since 1.5.0
	 *
	 * @param  ResolvedTemplate  $entry
	 */
	protected static function identifierOf( object $entry ): string
	{
		return $entry->slug;
	}
}
