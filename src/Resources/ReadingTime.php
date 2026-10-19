<?php

/**
 * Estimated reading time for a post (#943).
 *
 * Counts the words in a post's body and divides by a words-per-minute
 * rate. Used by `PostResolver` to stamp the `artisanpack/post-reading-time`
 * block and by the editor preview envelope, so the canvas and the front end
 * agree.
 *
 * The text comes from a pre-rendered `content` string, or else from the
 * text attributes of the post's saved block tree, so no blocks are rendered
 * to count it. Hosts change the rate with the
 * `ap.visualEditor.readingTime.wordsPerMinute` filter, or supply their own
 * minutes (e.g. a stored value) with `ap.visualEditor.readingTime.minutes`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.2
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Resources;

use Throwable;

class ReadingTime
{
	/**
	 * Default reading speed, in words per minute.
	 *
	 * @since 1.15.2
	 */
	public const WORDS_PER_MINUTE = 200;

	/**
	 * Block attributes whose strings are read as body text.
	 *
	 * @var array<int, string>
	 */
	protected const TEXT_ATTRIBUTES = [ 'content', 'value', 'citation', 'caption', 'text' ];

	/**
	 * Whole minutes to read the post: its words over the rate, rounded up,
	 * at least one for any text at all, zero for none.
	 *
	 * @since 1.15.2
	 *
	 * @param  int|null  $words  A word count already taken with {@see self::words()}.
	 */
	public function minutes( object $post, ?int $words = null ): int
	{
		$words ??= $this->words( $post );
		$rate    = function_exists( 'applyFilters' )
			? (int) applyFilters( 'ap.visualEditor.readingTime.wordsPerMinute', self::WORDS_PER_MINUTE, $post )
			: self::WORDS_PER_MINUTE;
		$rate    = $rate > 0 ? $rate : self::WORDS_PER_MINUTE;
		$minutes = 0 === $words ? 0 : max( 1, (int) ceil( $words / $rate ) );

		if ( function_exists( 'applyFilters' ) ) {
			$minutes = max( 0, (int) applyFilters( 'ap.visualEditor.readingTime.minutes', $minutes, $post, $words ) );
		}

		return $minutes;
	}

	/**
	 * Number of words in the post's body.
	 *
	 * @since 1.15.2
	 */
	public function words( object $post ): int
	{
		$plain = html_entity_decode(
			strip_tags( str_replace( '<', ' <', $this->text( $post ) ) ),
			ENT_QUOTES | ENT_HTML5,
			'UTF-8',
		);

		return (int) preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}’\'-]*/u', $plain );
	}

	/**
	 * The post body as text (HTML allowed).
	 */
	protected function text( object $post ): string
	{
		$content = $post->content ?? null;

		if ( is_string( $content ) && '' !== trim( $content ) && ! str_starts_with( ltrim( $content ), '[' ) ) {
			return $content;
		}

		$tree = null;

		if ( method_exists( $post, 'getBlockContent' ) ) {
			try {
				$tree = $post->getBlockContent();
			} catch ( Throwable ) {
				$tree = null;
			}
		}

		if ( ! is_array( $tree ) || [] === $tree ) {
			$tree = $post->block_content ?? ( is_array( $content ) ? $content : null );
		}

		if ( is_string( $tree ) ) {
			$decoded = json_decode( $tree, true );
			$tree    = is_array( $decoded ) ? $decoded : null;
		}

		return is_array( $tree ) ? implode( ' ', $this->blockText( $tree ) ) : '';
	}

	/**
	 * Gather the text attributes of a block tree, depth first.
	 *
	 * @param  array<int|string, mixed>  $blocks
	 *
	 * @return array<int, string>
	 */
	protected function blockText( array $blocks ): array
	{
		$text = [];

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];

			foreach ( self::TEXT_ATTRIBUTES as $key ) {
				if ( isset( $attributes[ $key ] ) && is_string( $attributes[ $key ] ) ) {
					$text[] = $attributes[ $key ];
				}
			}

			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				array_push( $text, ...$this->blockText( $block['innerBlocks'] ) );
			}
		}

		return $text;
	}
}
