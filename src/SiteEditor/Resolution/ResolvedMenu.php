<?php

/**
 * Resolved menu value object.
 *
 * Maps a theme-declared location (`primary`, `footer`, etc.) to a menu
 * record + its menu items. cms-framework's `MenuResolver` (H4) populates
 * this through the `ap.visualEditor.navigation` filter; H6's
 * `wp_navigation` REST adapter reads it.
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

class ResolvedMenu
{
	/**
	 * @since 1.0.0
	 */
	protected const FILTER_NAME = 'ap.visualEditor.navigation';

	/**
	 * @since 1.0.0
	 *
	 * @param  string  $location  Theme-declared menu location key.
	 * @param  string  $name      Display name of the menu assigned to this location.
	 * @param  array<int, array<string, mixed>>  $items  Menu items. Items follow
	 *                                                   the upstream `core/navigation-link`
	 *                                                   shape (label, url, type, ...) so
	 *                                                   the navigation block consumes them
	 *                                                   without translation.
	 * @param  int|null  $wpId     DB row id of the menu record, or null when the
	 *                             menu is theme-declared but has no user assignment yet.
	 */
	public function __construct(
		public readonly string $location,
		public readonly string $name,
		public readonly array $items,
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
		if ( ! isset( $data['location'] ) || ! is_string( $data['location'] ) ) {
			throw SiteEditorRegistrationException::missingRequiredField(
				self::FILTER_NAME,
				$data['location'] ?? '(unknown)',
				'location',
			);
		}

		return new self(
			location : $data['location'],
			name     : (string) ( $data['name'] ?? '' ),
			items    : is_array( $data['items'] ?? null ) ? $data['items'] : [],
			wpId     : isset( $data['wp_id'] ) ? (int) $data['wp_id'] : null,
		);
	}
}
