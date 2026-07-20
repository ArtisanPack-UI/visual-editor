<?php

/**
 * Resolved global-styles value object.
 *
 * Singleton (one per active theme): the merged `settings` + `styles` object
 * H6's `__unstableBase` REST adapter consumes. Variations come from the
 * theme's `theme.json` `styles.variations`.
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

class ResolvedGlobalStyles
{
	/**
	 * @since 1.0.0
	 */
	protected const FILTER_NAME = 'ap.visualEditor.globalStyles';

	/**
	 * @since 1.0.0
	 *
	 * @param  string  $theme       Active theme slug.
	 * @param  array<string, mixed>  $settings    `theme.json`-shape settings object.
	 * @param  array<string, mixed>  $styles      `theme.json`-shape styles object.
	 * @param  array<int, array<string, mixed>>  $variations  Style variations from
	 *                                                        `theme.json` `styles.variations`.
	 * @param  int|null  $wpId       DB row id; null when the theme defaults are
	 *                               authoritative (no user customization yet).
	 */
	public function __construct(
		public readonly string $theme,
		public readonly array $settings,
		public readonly array $styles,
		public readonly array $variations,
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
		if ( ! isset( $data['theme'] ) || ! is_string( $data['theme'] ) ) {
			throw SiteEditorRegistrationException::missingRequiredField( self::FILTER_NAME, '(singleton)', 'theme' );
		}

		return new self(
			theme      : $data['theme'],
			settings   : is_array( $data['settings'] ?? null ) ? $data['settings'] : [],
			styles     : is_array( $data['styles'] ?? null ) ? $data['styles'] : [],
			variations : is_array( $data['variations'] ?? null ) ? $data['variations'] : [],
			wpId       : isset( $data['wp_id'] ) ? (int) $data['wp_id'] : null,
		);
	}
}
