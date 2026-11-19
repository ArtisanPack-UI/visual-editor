<?php

/**
 * Word-trims a post excerpt to the Post Excerpt block's Excerpt Length.
 *
 * Mirrors WordPress core's `wp_trim_words()`, which `core/post-excerpt`
 * applies to every excerpt, manual or generated: tags are stripped
 * (script and style blocks with their contents), whitespace collapses,
 * and the text is cut to the word count with a trailing ellipsis only
 * when something was cut. Trimming twice is a no-op, so an excerpt a
 * resolver already trimmed keeps its text (#1139).
 *
 * The React and Vue renderers mirror this in `support/excerpt.ts`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.22.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

class ExcerptTrimmer
{
	/**
	 * The block's default Excerpt Length (core's `excerpt_length`).
	 *
	 * @since 1.22.0
	 *
	 * @var int
	 */
	public const DEFAULT_LENGTH = 55;

	/**
	 * Trim $html to $words words of plain text.
	 *
	 * @since 1.22.0
	 *
	 * @param string $html  The resolved excerpt (may hold markup).
	 * @param mixed  $words The block's `excerptLength`; anything that isn't a positive number falls back to 55.
	 *
	 * @return string The trimmed plain text, `…`-terminated when cut.
	 */
	public static function trim( string $html, mixed $words ): string
	{
		$length = is_numeric( $words ) && (int) $words > 0 ? (int) $words : self::DEFAULT_LENGTH;

		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $html ) ?? '';
		$text = strip_tags( $text );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? '' );

		if ( '' === $text ) {
			return '';
		}

		$pieces = explode( ' ', $text );

		if ( count( $pieces ) <= $length ) {
			return $text;
		}

		return implode( ' ', array_slice( $pieces, 0, $length ) ) . '…';
	}
}
