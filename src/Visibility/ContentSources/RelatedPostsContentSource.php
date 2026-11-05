<?php

/**
 * Content source for Related Posts blocks. They stamp the same keys as
 * a Query Loop, so only the block name and label differ.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Visibility\ContentSources;

class RelatedPostsContentSource extends QueryContentSource
{
	/**
	 * @since 1.20.0
	 *
	 * @return string The source key.
	 */
	public function key(): string
	{
		return 'relatedPosts';
	}

	/**
	 * @since 1.20.0
	 *
	 * @return string The source label.
	 */
	public function label(): string
	{
		return __( 'Related Posts' );
	}

	/**
	 * @since 1.20.0
	 *
	 * @return array<int, string> The block names.
	 */
	public function blockNames(): array
	{
		return [ 'artisanpack/related-posts' ];
	}
}
