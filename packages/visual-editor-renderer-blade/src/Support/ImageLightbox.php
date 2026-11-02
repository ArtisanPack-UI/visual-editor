<?php

/**
 * Whether an Image block opens in the lightbox, and what it prints when it
 * does (#1046).
 *
 * A block's own `lightbox.enabled` wins. Without one the theme decides, through
 * `settings.blocks.{artisanpack,core}/image.lightbox.enabled` in theme.json. An
 * image with a link keeps the link, so it never opens the lightbox.
 *
 * The React and Vue renderers mirror this in `support/lightbox.ts`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

use ArtisanPackUI\VisualEditorRendererBlade\Services\ActiveThemeSettings;

class ImageLightbox
{
	/**
	 * The trigger's corner-brackets icon. Decorative: the button has its own
	 * label.
	 *
	 * @since 1.20.0
	 *
	 * @var string
	 */
	public const TRIGGER_ICON = '<svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path fill="currentColor" d="M2 0a2 2 0 0 0-2 2v2h1.5V2a.5.5 0 0 1 .5-.5h2V0H2Zm2 10.5H2a.5.5 0 0 1-.5-.5V8H0v2a2 2 0 0 0 2 2h2v-1.5ZM8 12v-1.5h2a.5.5 0 0 0 .5-.5V8H12v2a2 2 0 0 1-2 2H8Zm2-12a2 2 0 0 1 2 2v2h-1.5V2a.5.5 0 0 0-.5-.5H8V0h2Z"/></svg>';

	/**
	 * Theme setting keys, most specific first.
	 *
	 * @since 1.20.0
	 *
	 * @var array<int, string>
	 */
	protected const SETTING_BLOCKS = [ 'artisanpack/image', 'core/image' ];

	/**
	 * Gallery navigation button types that need telling to the overlay. It
	 * shows icons unless told otherwise.
	 *
	 * @since 1.20.0
	 *
	 * @var array<int, string>
	 */
	protected const NAVIGATION_TYPES = [ 'text', 'both' ];

	/**
	 * Whether this image opens in the lightbox.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<string, mixed>  $attributes  The block's attributes.
	 * @param  string                $url         The image's sanitized URL.
	 * @param  string                $href        The image's sanitized link.
	 */
	public static function enabled( array $attributes, string $url, string $href ): bool
	{
		if ( '' === $url || '' !== $href ) {
			return false;
		}

		$own = $attributes['lightbox']['enabled'] ?? null;

		return is_bool( $own ) ? $own : self::themeDefault();
	}

	/**
	 * The theme.json default.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<string, mixed>|null  $settings  Theme settings, or null for the active theme's.
	 */
	public static function themeDefault( ?array $settings = null ): bool
	{
		$settings ??= app( ActiveThemeSettings::class )->get();

		foreach ( self::SETTING_BLOCKS as $block ) {
			$enabled = $settings['blocks'][ $block ]['lightbox']['enabled'] ?? null;

			if ( is_bool( $enabled ) ) {
				return $enabled;
			}
		}

		return false;
	}

	/**
	 * The trigger's accessible name.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $alt  The image's alt text.
	 */
	public static function triggerLabel( string $alt ): string
	{
		return '' === trim( $alt )
			? __( 'Enlarge image' )
			: __( 'Enlarge image: :alt', [ 'alt' => $alt ] );
	}

	/**
	 * The overlay's labels, for the figure's `data-ap-lightbox-labels`.
	 *
	 * @since 1.20.0
	 */
	public static function labels(): string
	{
		return (string) json_encode(
			[
				'dialog'   => __( 'Enlarged image' ),
				'close'    => __( 'Close' ),
				'previous' => __( 'Previous image' ),
				'next'     => __( 'Next image' ),
			],
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
		);
	}

	/**
	 * The Gallery's `data-ap-lightbox-navigation` attribute, with a leading
	 * space, or `''` for the default.
	 *
	 * @since 1.20.0
	 *
	 * @param  mixed  $type  The block's `navigationButtonType`.
	 */
	public static function navigationAttribute( mixed $type ): string
	{
		return in_array( $type, self::NAVIGATION_TYPES, true )
			? sprintf( ' data-ap-lightbox-navigation="%s"', e( $type ) )
			: '';
	}
}
