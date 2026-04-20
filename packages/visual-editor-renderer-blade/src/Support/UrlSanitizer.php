<?php

/**
 * URL sanitization helpers for rendered block attributes.
 *
 * Gutenberg editors can persist arbitrary strings in `url`/`href`-shaped
 * attributes. Before those strings end up in `href` / `src`, we drop
 * dangerous schemes (`javascript:`, `data:`, `vbscript:`) so a stored block
 * tree can't smuggle script execution into the rendered page.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

class UrlSanitizer
{
	public const SAFE_SCHEMES = [ 'http', 'https', 'mailto', 'tel', 'ftp', 'sms' ];

	/**
	 * Return $url if it's relative or uses a safe scheme, otherwise an empty string.
	 *
	 * @since 1.0.0
	 */
	public static function safe( string $url ): string
	{
		$trimmed = trim( $url );

		if ( '' === $trimmed ) {
			return '';
		}

		$scheme = parse_url( $trimmed, PHP_URL_SCHEME );

		if ( null === $scheme || false === $scheme ) {
			return $trimmed;
		}

		return in_array( strtolower( (string) $scheme ), self::SAFE_SCHEMES, true ) ? $trimmed : '';
	}
}
