<?php

/**
 * Shared helper for deriving the host post's "relatedness signal" (the
 * `[ taxonomy, termIds ]` pair the related-posts query targets).
 *
 * Used by {@see \ArtisanPackUI\VisualEditor\Resources\QueryInliner} on
 * the public-render path and by
 * {@see \ArtisanPackUI\VisualEditor\Http\Controllers\QueryResolveController}
 * on the editor-canvas preview path so both routes share one source of
 * truth for "what makes two posts related".
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services;

use Throwable;

class HostRelatedTermsResolver
{
	public function __construct( protected QueryResolverContract $resolver ) {}

	/**
	 * Load a single host post by id via the bound resolver. Returns
	 * `null` when the resolver has no match (deleted / wrong post type
	 * / permission failure) so callers can short-circuit cleanly.
	 *
	 * @since 1.2.0
	 *
	 * @param  int     $postId    The post id to resolve.
	 * @param  string  $postType  Optional post-type slug to scope the lookup.
	 *
	 * @return object|null
	 */
	public function loadHostPost( int $postId, string $postType = 'post' ): ?object
	{
		if ( $postId < 1 ) {
			return null;
		}

		if ( '' === trim( $postType ) ) {
			$postType = 'post';
		}

		try {
			$paginator = $this->resolver->resolve( [
				'postType' => $postType,
				'include'  => [ $postId ],
				'perPage'  => 1,
			] );
		} catch ( Throwable $e ) {
			report( $e );

			return null;
		}

		foreach ( $paginator->items() as $candidate ) {
			if ( is_object( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Read the host post's post-type slug. Defaults to `'post'` so the
	 * downstream query always sees a valid slug.
	 *
	 * @since 1.2.0
	 *
	 * @param  object  $post  The host post.
	 *
	 * @return string
	 */
	public function hostPostType( object $post ): string
	{
		foreach ( [ 'post_type', 'type' ] as $key ) {
			$value = $post->{$key} ?? null;

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}

		return 'post';
	}

	/**
	 * Resolve the host post's relatedness signal as a
	 * `[ taxonomy, termIds ]` pair. Prefers categories, falls back to
	 * tags, then to a generic `terms` collection scoped to `category`
	 * so tag-only posts still exercise the right index.
	 *
	 * @since 1.2.0
	 *
	 * @param  object  $post  The host post.
	 *
	 * @return array{0: string, 1: array<int, int>}
	 */
	public function hostRelatedTerms( object $post ): array
	{
		$categoryIds = $this->termIdsFromRelation( $post, 'categories' );

		if ( [] !== $categoryIds ) {
			return [ 'category', $categoryIds ];
		}

		$tagIds = $this->termIdsFromRelation( $post, 'tags' );

		if ( [] !== $tagIds ) {
			return [ 'post_tag', $tagIds ];
		}

		$genericIds = $this->termIdsFromRelation( $post, 'terms' );

		return [ 'category', $genericIds ];
	}

	/**
	 * Pluck integer term ids from one named relation on the post.
	 *
	 * @since 1.2.0
	 *
	 * @param  object  $post      The host post.
	 * @param  string  $relation  The relation accessor name.
	 *
	 * @return array<int, int>
	 */
	public function termIdsFromRelation( object $post, string $relation ): array
	{
		$collection = $post->{$relation} ?? null;

		if ( ! is_iterable( $collection ) ) {
			return [];
		}

		$ids = [];

		foreach ( $collection as $term ) {
			if ( is_object( $term ) && isset( $term->id ) && is_numeric( $term->id ) ) {
				$termId = (int) $term->id;

				if ( $termId > 0 ) {
					$ids[] = $termId;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}
}
