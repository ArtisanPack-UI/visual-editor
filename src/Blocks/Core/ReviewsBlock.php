<?php

/**
 * Server-rendered `artisanpack/reviews` block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.9.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Core;

use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

/**
 * Renders a visual reviews block from payloads supplied by the host at
 * render time via the `ap.visualEditor.reviews.collectReviews` filter.
 *
 * The block ships zero reviews of its own. Every contributor — the host
 * application, another ArtisanPack UI package, or any cms-framework
 * plugin / theme active at runtime — participates by hooking the filter
 * and returning review payloads keyed to the shape documented below.
 * Firing the filter at render time (not at boot) is deliberate: cms-
 * framework plugins and themes are activated at runtime, so a boot-time
 * collection would miss any contributor that activates after the app
 * has finished booting.
 *
 * Emits **no** `Review` or `AggregateRating` JSON-LD. Self-serving
 * review markup on the reviewed entity itself is ineligible for
 * Google's review-snippet rich result (per Google's structured-data
 * guidelines), so the block deliberately keeps to visual display only.
 * Hosts that surface reviews through a different entity (a physical
 * location, a third-party aggregator page) can emit their own
 * structured data alongside the block; nothing in the block's markup
 * competes with or duplicates it.
 *
 * ## Payload shape
 *
 * Each entry the filter returns is normalized to:
 *
 * ```
 * [
 *   'reviewer'   => string,   // required, display name of the reviewer
 *   'quote'      => string,   // required, review text
 *   'rating'     => int,      // optional, 1..5 (out of 5); clamped
 *   'source'     => string,   // optional, e.g. "Google", "Yelp" — badge label
 *   'url'        => string,   // optional, canonical URL to the review
 *   'date'       => string,   // optional, ISO 8601 or human-readable date
 *   'avatar_url' => string,   // optional, reviewer avatar
 * ]
 * ```
 *
 * Entries missing both `reviewer` and `quote` are dropped: an empty
 * card carries no signal and would visually degrade the surrounding
 * layout. Entries with malformed field types (e.g. `rating` as a
 * string) are coerced where safe and dropped where not.
 */
class ReviewsBlock extends DynamicBlock
{
	public const FILTER_COLLECT_REVIEWS = 'ap.visualEditor.reviews.collectReviews';

	private const DEFAULT_LIMIT = 3;

	private const MAX_LIMIT = 24;

	private const MAX_COLUMNS = 4;

	private const MAX_RATING = 5;

	public function name(): string
	{
		return 'artisanpack/reviews';
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return array<string, mixed>
	 */
	public function validateAttrs( array $attrs ): array
	{
		$limit   = (int) ( $attrs['limit'] ?? self::DEFAULT_LIMIT );
		$columns = (int) ( $attrs['columns'] ?? 3 );

		$normalized = [
			'source'     => isset( $attrs['source'] ) && is_string( $attrs['source'] ) ? trim( $attrs['source'] ) : '',
			'limit'      => max( 1, min( self::MAX_LIMIT, $limit ) ),
			'columns'    => max( 1, min( self::MAX_COLUMNS, $columns ) ),
			'layout'     => 'list' === ( $attrs['layout'] ?? 'grid' ) ? 'list' : 'grid',
			'showStars'  => $this->normalizeBool( $attrs['showStars'] ?? null, true ),
			'showSource' => $this->normalizeBool( $attrs['showSource'] ?? null, true ),
			'showDate'   => $this->normalizeBool( $attrs['showDate'] ?? null, true ),
		];

		foreach ( [ 'className', 'anchor', 'align', 'textAlign', 'backgroundColor', 'textColor', 'gradient', 'borderColor', 'fontSize', 'fontFamily' ] as $key ) {
			if ( isset( $attrs[ $key ] ) && is_string( $attrs[ $key ] ) ) {
				$normalized[ $key ] = $attrs[ $key ];
			}
		}

		if ( isset( $attrs['style'] ) && is_array( $attrs['style'] ) ) {
			$normalized['style'] = $attrs['style'];
		}

		return $normalized;
	}

	public function render( array $attrs ): string
	{
		$reviews = $this->collectReviews( $attrs );

		if ( [] === $reviews ) {
			return $this->renderEmptyState( $attrs );
		}

		$cards = '';

		foreach ( $reviews as $review ) {
			$cards .= $this->renderCard( $review, $attrs );
		}

		return sprintf(
			'<div%s><div class="wp-block-artisanpack-reviews__list">%s</div></div>',
			BlockSupports::wrapperAttrs( $attrs, $this->wrapperClasses( $attrs ) ),
			$cards
		);
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 */
	public function searchableText( array $attrs ): string
	{
		$reviews = $this->collectReviews( $this->validateAttrs( $attrs ) );

		$parts = [];

		foreach ( $reviews as $review ) {
			$parts[] = trim( $review['reviewer'] . ' ' . $review['quote'] );
		}

		return implode( ' ', array_filter( $parts ) );
	}

	/**
	 * Fire the collect-reviews filter and normalize the returned payloads.
	 *
	 * Wrapped in a protected method so tests can swap the collection
	 * source without depending on the `artisanpack-ui/hooks` global
	 * function surface. The filter is gated on `applyFilters()` being
	 * defined so visual-editor stays bootable when the hooks package is
	 * absent (the block simply renders the empty state in that case).
	 *
	 * @param  array<string, mixed>  $attrs  Validated block attributes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function collectReviews( array $attrs ): array
	{
		if ( ! function_exists( 'applyFilters' ) ) {
			return [];
		}

		$raw = applyFilters( self::FILTER_COLLECT_REVIEWS, [], $attrs );

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$normalized = [];

		foreach ( $raw as $entry ) {
			$review = $this->normalizeReview( $entry );

			if ( null === $review ) {
				continue;
			}

			if ( '' !== $attrs['source'] && strcasecmp( $review['source'], $attrs['source'] ) !== 0 ) {
				continue;
			}

			$normalized[] = $review;

			if ( count( $normalized ) >= $attrs['limit'] ) {
				break;
			}
		}

		return $normalized;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	protected function normalizeReview( mixed $entry ): ?array
	{
		if ( ! is_array( $entry ) ) {
			return null;
		}

		$reviewer = isset( $entry['reviewer'] ) && is_string( $entry['reviewer'] ) ? trim( $entry['reviewer'] ) : '';
		$quote    = isset( $entry['quote'] ) && is_string( $entry['quote'] ) ? trim( $entry['quote'] ) : '';

		if ( '' === $reviewer && '' === $quote ) {
			return null;
		}

		$rating = 0;

		if ( isset( $entry['rating'] ) && is_numeric( $entry['rating'] ) ) {
			$rating = max( 0, min( self::MAX_RATING, (int) $entry['rating'] ) );
		}

		return [
			'reviewer'   => $reviewer,
			'quote'      => $quote,
			'rating'     => $rating,
			'source'     => isset( $entry['source'] ) && is_string( $entry['source'] ) ? trim( $entry['source'] ) : '',
			'url'        => $this->sanitizeUrl( $entry['url'] ?? null ),
			'date'       => isset( $entry['date'] ) && is_string( $entry['date'] ) ? trim( $entry['date'] ) : '',
			'avatar_url' => $this->sanitizeUrl( $entry['avatar_url'] ?? null ),
		];
	}

	/**
	 * Return a URL only when its scheme is safe to embed in `href` /
	 * `src`, otherwise the empty string.
	 *
	 * `e()` neutralizes tag injection but does nothing about
	 * `javascript:`, `data:`, or `vbscript:` schemes, any of which
	 * would produce an executable link when dropped into an anchor
	 * or image attribute. Contributor payloads reach this block
	 * from untrusted sources (third-party review APIs, user-editable
	 * plugin config), so the scheme is allowlisted here rather than
	 * trusted end-to-end. `mailto:` is included because a source
	 * badge may reasonably link a support address.
	 *
	 * @since 1.9.0
	 */
	protected function sanitizeUrl( mixed $value ): string
	{
		if ( ! is_string( $value ) ) {
			return '';
		}

		$trimmed = trim( $value );

		if ( '' === $trimmed ) {
			return '';
		}

		$scheme = strtolower( (string) parse_url( $trimmed, PHP_URL_SCHEME ) );

		return in_array( $scheme, [ 'http', 'https', 'mailto' ], true ) ? $trimmed : '';
	}

	/**
	 * Coerce a mixed attribute into a strict boolean, honoring the
	 * documented default when the value is missing or ambiguous.
	 *
	 * `(bool)` casts the string `"false"` to `true` because it is a
	 * non-empty string — a persisted attribute round-tripped through
	 * JSON or a URL query can arrive as the literal string `"false"`,
	 * which would silently flip a "hide stars" toggle into "show stars"
	 * (CodeRabbit PR #781). This helper treats `"true"`/`"false"`,
	 * `"1"`/`"0"`, `"yes"`/`"no"`, and `"on"`/`"off"` explicitly, and
	 * falls back to `$default` for anything else — so a malformed
	 * value produces the documented behavior, not the inverse of it.
	 *
	 * @since 1.9.0
	 */
	protected function normalizeBool( mixed $value, bool $default ): bool
	{
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) ) {
			return 0 !== $value;
		}

		if ( is_string( $value ) ) {
			$normalized = strtolower( trim( $value ) );

			if ( in_array( $normalized, [ 'true', '1', 'yes', 'on' ], true ) ) {
				return true;
			}

			if ( in_array( $normalized, [ 'false', '0', 'no', 'off', '' ], true ) ) {
				return false;
			}
		}

		return $default;
	}

	/**
	 * @param  array<string, mixed>  $review
	 * @param  array<string, mixed>  $attrs
	 */
	protected function renderCard( array $review, array $attrs ): string
	{
		$parts = [];

		if ( $attrs['showStars'] && $review['rating'] > 0 ) {
			$parts[] = $this->renderStars( $review['rating'] );
		}

		$parts[] = sprintf(
			'<blockquote class="wp-block-artisanpack-reviews__quote">%s</blockquote>',
			e( $review['quote'] )
		);

		$byline = $this->renderByline( $review, $attrs );

		if ( '' !== $byline ) {
			$parts[] = $byline;
		}

		return sprintf(
			'<article class="wp-block-artisanpack-reviews__card">%s</article>',
			implode( '', $parts )
		);
	}

	protected function renderStars( int $rating ): string
	{
		$stars = '';

		for ( $i = 1; $i <= self::MAX_RATING; $i++ ) {
			$stars .= sprintf(
				'<span class="wp-block-artisanpack-reviews__star%s" aria-hidden="true">%s</span>',
				$i <= $rating ? ' is-filled' : '',
				$i <= $rating ? '★' : '☆'
			);
		}

		$label = sprintf(
			/* translators: 1: rating value, 2: rating scale maximum. */
			__( 'Rated %1$d out of %2$d' ),
			$rating,
			self::MAX_RATING
		);

		return sprintf(
			'<div class="wp-block-artisanpack-reviews__rating" role="img" aria-label="%s">%s</div>',
			e( $label ),
			$stars
		);
	}

	/**
	 * @param  array<string, mixed>  $review
	 * @param  array<string, mixed>  $attrs
	 */
	protected function renderByline( array $review, array $attrs ): string
	{
		$bits = [];

		if ( '' !== $review['reviewer'] ) {
			$avatar = '' !== $review['avatar_url']
				? sprintf(
					'<img class="wp-block-artisanpack-reviews__avatar" src="%s" alt=""/>',
					e( $review['avatar_url'] )
				)
				: '';

			$bits[] = sprintf(
				'<span class="wp-block-artisanpack-reviews__reviewer">%s%s</span>',
				$avatar,
				e( $review['reviewer'] )
			);
		}

		if ( $attrs['showDate'] && '' !== $review['date'] ) {
			$bits[] = sprintf(
				'<time class="wp-block-artisanpack-reviews__date" datetime="%s">%s</time>',
				e( $review['date'] ),
				e( $review['date'] )
			);
		}

		if ( $attrs['showSource'] && '' !== $review['source'] ) {
			$bits[] = '' !== $review['url']
				? sprintf(
					'<a class="wp-block-artisanpack-reviews__source" href="%s" rel="noopener nofollow" target="_blank">%s</a>',
					e( $review['url'] ),
					e( $review['source'] )
				)
				: sprintf(
					'<span class="wp-block-artisanpack-reviews__source">%s</span>',
					e( $review['source'] )
				);
		}

		if ( [] === $bits ) {
			return '';
		}

		return sprintf( '<footer class="wp-block-artisanpack-reviews__byline">%s</footer>', implode( '', $bits ) );
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 */
	protected function renderEmptyState( array $attrs ): string
	{
		return sprintf(
			'<div%s><p class="wp-block-artisanpack-reviews__empty">%s</p></div>',
			BlockSupports::wrapperAttrs( $attrs, array_merge( $this->wrapperClasses( $attrs ), [ 'wp-block-artisanpack-reviews--empty' ] ) ),
			e( __( 'Connect a review source to display customer reviews here.' ) )
		);
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return array<int, string>
	 */
	protected function wrapperClasses( array $attrs ): array
	{
		$classes = [ 'wp-block-artisanpack-reviews' ];

		if ( 'grid' === $attrs['layout'] ) {
			$classes[] = 'is-layout-grid';
			$classes[] = 'columns-' . $attrs['columns'];
		} else {
			$classes[] = 'is-layout-list';
		}

		return $classes;
	}
}
