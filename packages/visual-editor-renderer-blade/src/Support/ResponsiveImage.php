<?php

/**
 * What the renderer prints for one `<img>`, beyond its `src` (#1031).
 *
 * Built by {@see \ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveImages}.
 * An image with no media record still carries its loading hints; the other
 * fields stay empty.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.19.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

class ResponsiveImage
{
	/**
	 * @param  string                 $loading  One of the `ResponsiveImages::LOADING_*` values.
	 * @param  ?int                   $width    Pixel width of the `src` file.
	 * @param  ?int                   $height   Pixel height of the `src` file.
	 * @param  string                 $srcset   The `srcset` attribute, or ''.
	 * @param  string                 $sizes    The `sizes` attribute, or ''.
	 * @param  array<string, string>  $sources  MIME type => `srcset` for `<picture>` sources.
	 */
	public function __construct(
		public readonly string $loading,
		public readonly ?int $width = null,
		public readonly ?int $height = null,
		public readonly string $srcset = '',
		public readonly string $sizes = '',
		public readonly array $sources = [],
	) {
	}

	/**
	 * The `srcset`, `sizes` and loading attributes, with a leading space.
	 *
	 * @since 1.19.0
	 */
	public function attributes(): string
	{
		$attributes = '';

		if ( '' !== $this->srcset ) {
			$attributes .= sprintf( ' srcset="%s"', e( $this->srcset ) );
		}

		if ( '' !== $this->sizes ) {
			$attributes .= sprintf( ' sizes="%s"', e( $this->sizes ) );
		}

		return match ( $this->loading ) {
			'lazy'     => $attributes . ' loading="lazy"',
			'priority' => $attributes . ' fetchpriority="high"',
			default    => $attributes,
		};
	}

	/**
	 * Wrap the `<img>` in a `<picture>` when modern formats exist.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $img  The complete `<img>` tag.
	 */
	public function wrap( string $img ): string
	{
		if ( [] === $this->sources ) {
			return $img;
		}

		$sources = '';

		foreach ( $this->sources as $type => $srcset ) {
			$sources .= sprintf( '<source type="%s" srcset="%s"', e( $type ), e( $srcset ) );
			$sources .= '' !== $this->sizes ? sprintf( ' sizes="%s"/>', e( $this->sizes ) ) : '/>';
		}

		return '<picture>' . $sources . $img . '</picture>';
	}
}
