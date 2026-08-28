<?php

/**
 * Font bundle stylesheet enqueuer.
 *
 * Adds the generated `fonts.css` bundle to cms-framework's theme stylesheet
 * lists so installed fonts load on the public site. The service provider hooks
 * {@see appendTo()} onto the `ap.themes.frontendStyles` filter; the bundle is
 * appended as a `{src, ver}` entry the theme asset pipeline renders as a
 * `<link rel="stylesheet">`, cache-busted by the bundle's last-modified time.
 *
 * The editor canvas iframe receives the same `@font-face` rules through the
 * `/global-styles/css` endpoint instead, so both surfaces render installed
 * fonts. When no bundle has been generated yet the entry list is returned
 * unchanged.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Fonts\Support;

use ArtisanPackUI\VisualEditor\Fonts\Services\FontsCssGenerator;

class FontStylesheetEnqueuer
{
	/**
	 * The handle the bundle is registered under in the theme asset pipeline.
	 */
	public const HANDLE = 'visual-editor-fonts';

	public function __construct(
		protected FontsCssGenerator $generator,
	) {
	}

	/**
	 * Append the generated bundle to a theme stylesheet-entry list.
	 *
	 * Non-array input (a misbehaving upstream filter) is treated as an empty
	 * list; a bundle that has not been generated, or whose disk exposes no URL,
	 * is skipped so the list is never polluted with a broken `<link>`.
	 *
	 * @since 1.7.0
	 *
	 * @param  mixed  $entries  The current stylesheet-entry list.
	 *
	 * @return array<int|string, mixed> The list with the bundle appended when available.
	 */
	public function appendTo( mixed $entries ): array
	{
		$entries = is_array( $entries ) ? $entries : [];

		$url = $this->generator->url();

		if ( null === $url ) {
			return $entries;
		}

		$entries[ self::HANDLE ] = [
			'src' => $url,
			'ver' => $this->generator->version(),
		];

		return $entries;
	}
}
