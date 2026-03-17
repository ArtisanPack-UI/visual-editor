<?php

/**
 * Latest Posts Block Livewire Component.
 *
 * Server-side rendering component for the Latest Posts dynamic block.
 * Receives block attributes as props and renders the appropriate
 * display template (list, grid, or cards).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Blocks
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Livewire\Blocks;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Livewire component for the Latest Posts dynamic block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Blocks
 *
 * @since      2.0.0
 */
class LatestPostsBlockComponent extends Component
{
	/**
	 * The post type to query.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $postType = 'post';

	/**
	 * Number of posts to display.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	public int $numberOfPosts = 5;

	/**
	 * Field to order posts by.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $orderBy = 'date';

	/**
	 * Sort order direction.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $order = 'desc';

	/**
	 * Category IDs to filter by.
	 *
	 * @since 2.0.0
	 *
	 * @var array<int, mixed>
	 */
	public array $categories = [];

	/**
	 * Tag IDs to filter by.
	 *
	 * @since 2.0.0
	 *
	 * @var array<int, mixed>
	 */
	public array $tags = [];

	/**
	 * Display template (list, grid, or cards).
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $displayTemplate = 'list';

	/**
	 * Whether to show featured images.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	public bool $showFeaturedImage = true;

	/**
	 * Whether to show excerpts.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	public bool $showExcerpt = true;

	/**
	 * Whether to show the post date.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	public bool $showDate = true;

	/**
	 * Whether to show the post author.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	public bool $showAuthor = false;

	/**
	 * Excerpt length in words.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	public int $excerptLength = 25;

	/**
	 * Column configuration for grid/cards display.
	 *
	 * @since 2.0.0
	 *
	 * @var array<string, mixed>
	 */
	public array $columns = [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ];

	/**
	 * Number of posts to skip.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	public int $offset = 0;

	/**
	 * Whether to exclude the current post.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	public bool $excludeCurrentPost = false;

	/**
	 * Gap between items.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $gap = '1rem';

	/**
	 * Featured image aspect ratio.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $imageAspectRatio = '16/9';

	/**
	 * Whether this is being rendered in the editor.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	public bool $isEditor = false;

	/**
	 * Initialize and validate component properties.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function mount(): void
	{
		$this->numberOfPosts = max( 1, min( 50, (int) $this->numberOfPosts ) );
		$this->excerptLength = max( 0, min( 500, (int) $this->excerptLength ) );
		$this->offset        = max( 0, min( 1000, (int) $this->offset ) );
	}

	/**
	 * Render the component.
	 *
	 * @since 2.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		$posts = $this->getPosts();

		$columnsData = $this->columns;
		if ( is_array( $columnsData ) ) {
			$columnsCount = ( 'responsive' === ( $columnsData['mode'] ?? 'global' ) )
				? ( $columnsData['desktop'] ?? 3 )
				: ( $columnsData['global'] ?? $columnsData['desktop'] ?? 3 );
		} else {
			$columnsCount = (int) $columnsData;
		}

		return view( 'visual-editor::livewire.blocks.latest-posts-block', [
			'posts'        => $posts,
			'columnsCount' => $columnsCount,
		] );
	}

	/**
	 * Get posts based on the configured query attributes.
	 *
	 * Returns sample data for editor preview or queries the
	 * database on the frontend.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function getPosts(): array
	{
		if ( $this->isEditor ) {
			return $this->getSamplePosts();
		}

		$posts = veApplyFilters( 've.latest-posts.query', null, [
			'postType'      => $this->postType,
			'numberOfPosts' => $this->numberOfPosts,
			'orderBy'       => $this->orderBy,
			'categories'    => $this->categories,
		] );

		if ( is_array( $posts ) ) {
			return $posts;
		}

		return $this->getSamplePosts();
	}

	/**
	 * Generate sample posts for editor preview.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function getSamplePosts(): array
	{
		$posts = [];

		for ( $i = 1; $i <= $this->numberOfPosts; $i++ ) {
			$postDate = now()->subDays( $i );
			$posts[]  = [
				'id'             => $i,
				'title'          => __( 'visual-editor::ve.sample_post_title', [ 'number' => $i ] ),
				'excerpt'        => __( 'visual-editor::ve.sample_post_excerpt' ),
				'url'            => '#',
				'date'           => $postDate->format( 'M j, Y' ),
				'date_iso'       => $postDate->toIso8601String(),
				'author'         => __( 'visual-editor::ve.sample_author' ),
				'featured_image' => null,
			];
		}

		return $posts;
	}
}
