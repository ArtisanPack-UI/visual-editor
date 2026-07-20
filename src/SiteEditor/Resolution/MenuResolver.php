<?php

/**
 * Site-editor menu (navigation) resolver.
 *
 * Consumes the merged `ap.visualEditor.navigation` filter result, keyed by
 * theme-declared menu location.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 *
 * @extends AbstractMapResolver<ResolvedMenu>
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SiteEditor\Resolution;

class MenuResolver extends AbstractMapResolver
{
	/**
	 * @since 1.0.0
	 */
	protected static function filterName(): string
	{
		return 'ap.visualEditor.navigation';
	}

	/**
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $entry
	 */
	protected static function normalizeEntry( string $key, array $entry ): ResolvedMenu
	{
		// Default `location` to the map key if the contributor omitted it.
		$entry['location'] = $entry['location'] ?? $key;

		return ResolvedMenu::fromArray( $entry );
	}

	/**
	 * @since 1.5.0
	 *
	 * @param  ResolvedMenu  $entry
	 */
	protected static function identifierOf( object $entry ): string
	{
		return $entry->location;
	}
}
