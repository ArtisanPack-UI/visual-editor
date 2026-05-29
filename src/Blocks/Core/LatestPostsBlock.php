<?php

/**
 * Server-rendered `artisanpack/latest-posts` block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Core;

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ContentStatus;
use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Renders cms-framework's most recent published posts, matching upstream
 * `core/latest-posts` markup under the `artisanpack/latest-posts`
 * namespace (Phase I4 widgets-cluster fork, #412).
 *
 * The post records read through the same `Post` model the rest of the
 * package's dynamic blocks query — no new API surface. The day-one fork
 * wires the practically server-renderable controls: post count, ordering,
 * date/author meta, excerpt vs. full content, the featured image, and the
 * list/grid layout, plus the category + author filters. The remaining
 * upstream attributes are carried in `block.json` for round-trip parity
 * and surface in a later customization pass.
 */
class LatestPostsBlock extends DynamicBlock
{
	private const MIN_EXCERPT_LENGTH = 10;

	private const MAX_EXCERPT_LENGTH = 100;

	private const MAX_POSTS = 100;

	private const MAX_COLUMNS = 6;

	public function name(): string
	{
		return 'artisanpack/latest-posts';
	}

	public function validateAttrs( array $attrs ): array
	{
		$postsToShow = (int) ( $attrs['postsToShow'] ?? 5 );
		$columns     = (int) ( $attrs['columns'] ?? 3 );
		$excerpt     = (int) ( $attrs['excerptLength'] ?? 55 );

		return [
			'postsToShow'             => max( 1, min( self::MAX_POSTS, $postsToShow ) ),
			'order'                   => 'asc' === ( $attrs['order'] ?? 'desc' ) ? 'asc' : 'desc',
			'orderBy'                 => 'title' === ( $attrs['orderBy'] ?? 'date' ) ? 'title' : 'date',
			'displayPostContent'      => (bool) ( $attrs['displayPostContent'] ?? false ),
			'displayPostContentRadio' => 'full_post' === ( $attrs['displayPostContentRadio'] ?? 'excerpt' ) ? 'full_post' : 'excerpt',
			'excerptLength'           => max( self::MIN_EXCERPT_LENGTH, min( self::MAX_EXCERPT_LENGTH, $excerpt ) ),
			'displayAuthor'           => (bool) ( $attrs['displayAuthor'] ?? false ),
			'displayPostDate'         => (bool) ( $attrs['displayPostDate'] ?? false ),
			'displayFeaturedImage'    => (bool) ( $attrs['displayFeaturedImage'] ?? false ),
			'addLinkToFeaturedImage'  => (bool) ( $attrs['addLinkToFeaturedImage'] ?? false ),
			'postLayout'              => 'grid' === ( $attrs['postLayout'] ?? 'list' ) ? 'grid' : 'list',
			'columns'                 => max( 1, min( self::MAX_COLUMNS, $columns ) ),
			'categories'              => $this->normalizeCategoryIds( $attrs['categories'] ?? null ),
			'selectedAuthor'          => isset( $attrs['selectedAuthor'] ) && is_numeric( $attrs['selectedAuthor'] )
				? (int) $attrs['selectedAuthor']
				: null,
			'className'               => isset( $attrs['className'] ) && is_string( $attrs['className'] ) ? $attrs['className'] : '',
		];
	}

	public function render( array $attrs ): string
	{
		$posts   = $this->fetchPosts( $attrs );
		$classes = $this->wrapperClasses( $attrs );

		if ( $posts->isEmpty() ) {
			return sprintf(
				'<ul class="%s"><li>%s</li></ul>',
				e( implode( ' ', $classes ) ),
				e( __( 'No posts to show.' ) )
			);
		}

		$items = $posts->map(
			fn ( object $post ): string => $this->renderItem( $post, $attrs )
		)->implode( '' );

		return sprintf(
			'<ul class="%s">%s</ul>',
			e( implode( ' ', $classes ) ),
			$items
		);
	}

	public function searchableText( array $attrs ): string
	{
		$validated = $this->validateAttrs( $attrs );

		return $this->fetchPosts( $validated )
			->map( static fn ( object $post ): string => (string) ( $post->title ?? '' ) )
			->filter()
			->implode( ' ' );
	}

	/**
	 * Returns the post records used by {@see render()}. Pulled out so tests
	 * can override the data source without exercising the database.
	 *
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return Collection<int, object>
	 */
	protected function fetchPosts( array $attrs ): Collection
	{
		$query = Post::query()
			->where( 'status', ContentStatus::Published->value );

		if ( [] !== $attrs['categories'] ) {
			$query->whereHas(
				'categories',
				static fn ( $relation ) => $relation->whereIn( 'id', $attrs['categories'] )
			);
		}

		if ( null !== $attrs['selectedAuthor'] ) {
			$query->whereHas(
				'author',
				static fn ( $relation ) => $relation->whereKey( $attrs['selectedAuthor'] )
			);
		}

		$column = 'title' === $attrs['orderBy'] ? 'title' : 'published_at';

		return $query
			->orderBy( $column, $attrs['order'] )
			->limit( $attrs['postsToShow'] )
			->get();
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 */
	protected function renderItem( object $post, array $attrs ): string
	{
		$permalink = isset( $post->permalink ) ? (string) $post->permalink : '#';
		$title     = isset( $post->title ) && '' !== trim( (string) $post->title )
			? (string) $post->title
			: __( '(no title)' );

		$parts = [];

		if ( $attrs['displayFeaturedImage'] ) {
			$parts[] = $this->renderFeaturedImage( $post, $permalink, $title, $attrs );
		}

		$parts[] = sprintf(
			'<a class="wp-block-latest-posts__post-title" href="%s">%s</a>',
			e( $permalink ),
			e( $title )
		);

		if ( $attrs['displayPostDate'] && isset( $post->published_at ) ) {
			$date    = $post->published_at instanceof Carbon
				? $post->published_at
				: Carbon::parse( (string) $post->published_at );
			$parts[] = sprintf(
				'<time datetime="%s" class="wp-block-latest-posts__post-date">%s</time>',
				e( $date->toIso8601String() ),
				e( $date->translatedFormat( 'F j, Y' ) )
			);
		}

		if ( $attrs['displayAuthor'] && isset( $post->author->name ) ) {
			$parts[] = sprintf(
				'<div class="wp-block-latest-posts__post-author">%s</div>',
				e( sprintf( /* translators: %s: author name. */ __( 'by %s' ), (string) $post->author->name ) )
			);
		}

		if ( $attrs['displayPostContent'] ) {
			$parts[] = $this->renderContent( $post, $attrs );
		}

		return sprintf( '<li>%s</li>', implode( '', array_filter( $parts ) ) );
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 */
	protected function renderFeaturedImage( object $post, string $permalink, string $title, array $attrs ): string
	{
		$url = $this->featuredImageUrl( $post );

		if ( null === $url ) {
			return '';
		}

		$image = sprintf(
			'<img src="%s" alt="%s" class="wp-block-latest-posts__featured-image"/>',
			e( $url ),
			e( $title )
		);

		if ( $attrs['addLinkToFeaturedImage'] ) {
			return sprintf(
				'<div class="wp-block-latest-posts__featured-image"><a href="%s" aria-label="%s">%s</a></div>',
				e( $permalink ),
				e( $title ),
				$image
			);
		}

		return sprintf( '<div class="wp-block-latest-posts__featured-image">%s</div>', $image );
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 */
	protected function renderContent( object $post, array $attrs ): string
	{
		if ( 'full_post' === $attrs['displayPostContentRadio'] && isset( $post->content ) ) {
			return sprintf(
				'<div class="wp-block-latest-posts__post-full-content">%s</div>',
				e( strip_tags( (string) $post->content ) )
			);
		}

		$source  = isset( $post->excerpt ) && '' !== trim( (string) $post->excerpt )
			? (string) $post->excerpt
			: ( isset( $post->content ) ? (string) $post->content : '' );
		$excerpt = $this->trimWords( strip_tags( $source ), $attrs['excerptLength'] );

		if ( '' === $excerpt ) {
			return '';
		}

		return sprintf(
			'<div class="wp-block-latest-posts__post-excerpt">%s</div>',
			e( $excerpt )
		);
	}

	protected function featuredImageUrl( object $post ): ?string
	{
		if ( isset( $post->featured_image_url ) ) {
			$value = (string) $post->featured_image_url;

			return '' !== $value ? $value : null;
		}

		$media = $post->featuredImageMedia ?? null;

		if ( null === $media ) {
			return null;
		}

		if ( is_object( $media ) && method_exists( $media, 'url' ) ) {
			$resolved = $media->url();

			return is_string( $resolved ) && '' !== $resolved ? $resolved : null;
		}

		if ( is_object( $media ) && isset( $media->url ) ) {
			return (string) $media->url;
		}

		return null;
	}

	private function trimWords( string $text, int $words ): string
	{
		$text = trim( preg_replace( '/\s+/', ' ', $text ) ?? '' );

		if ( '' === $text ) {
			return '';
		}

		$pieces = explode( ' ', $text );

		if ( count( $pieces ) <= $words ) {
			return $text;
		}

		return implode( ' ', array_slice( $pieces, 0, $words ) ) . '…';
	}

	/**
	 * @return array<int, int>
	 */
	private function normalizeCategoryIds( mixed $categories ): array
	{
		if ( ! is_array( $categories ) ) {
			return [];
		}

		$ids = [];

		foreach ( $categories as $category ) {
			if ( is_array( $category ) && isset( $category['id'] ) && is_numeric( $category['id'] ) ) {
				$ids[] = (int) $category['id'];
			} elseif ( is_numeric( $category ) ) {
				$ids[] = (int) $category;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return array<int, string>
	 */
	protected function wrapperClasses( array $attrs ): array
	{
		$classes = [ 'wp-block-latest-posts__list', 'wp-block-latest-posts' ];

		if ( 'grid' === $attrs['postLayout'] ) {
			$classes[] = 'is-grid';
			$classes[] = 'columns-' . $attrs['columns'];
		}

		if ( $attrs['displayPostDate'] ) {
			$classes[] = 'has-dates';
		}

		if ( $attrs['displayAuthor'] ) {
			$classes[] = 'has-author';
		}

		if ( '' !== $attrs['className'] ) {
			$classes[] = $attrs['className'];
		}

		return $classes;
	}
}
