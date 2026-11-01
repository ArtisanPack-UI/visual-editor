<?php

/**
 * Content source for Post Navigation Link blocks. Every link is stamped
 * with both neighbours, so any one of them answers for either direction.
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

class PostNavigationContentSource extends AbstractContentSource
{
	/**
	 * @since 1.20.0
	 *
	 * @return string The source key.
	 */
	public function key(): string
	{
		return 'postNavigation';
	}

	/**
	 * @since 1.20.0
	 *
	 * @return string The source label.
	 */
	public function label(): string
	{
		return __( 'Post Navigation' );
	}

	/**
	 * @since 1.20.0
	 *
	 * @return array<int, string> The block names.
	 */
	public function blockNames(): array
	{
		return [ 'artisanpack/post-navigation-link', 'core/post-navigation-link' ];
	}

	/**
	 * @since 1.20.0
	 *
	 * @return array<string, string> Condition key => translated label.
	 */
	public function conditions(): array
	{
		return [
			'hasEither'   => __( 'Has a previous or next post' ),
			'hasPrevious' => __( 'Has a previous post' ),
			'hasNext'     => __( 'Has a next post' ),
		];
	}

	/**
	 * Whether the post has the neighbour the condition asks for.
	 *
	 * @since 1.20.0
	 *
	 * @param  string                $condition  A key from {@see conditions()}.
	 * @param  array<string, mixed>  $block      A resolved block.
	 *
	 * @return bool True when the neighbour exists.
	 */
	public function passes( string $condition, array $block ): bool
	{
		$hasPrevious = $this->hasUrl( $block, '_resolvedPrevUrl' );
		$hasNext     = $this->hasUrl( $block, '_resolvedNextUrl' );

		return match ( $condition ) {
			'hasPrevious' => $hasPrevious,
			'hasNext'     => $hasNext,
			default       => $hasPrevious || $hasNext,
		};
	}

	/**
	 * @since 1.20.0
	 *
	 * @return string The attribute name.
	 */
	protected function resolvedKey(): string
	{
		return '_resolvedPrevUrl';
	}

	/**
	 * Whether the attribute holds a non-empty URL.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<string, mixed>  $block  A resolved block.
	 * @param  string                $key    The attribute to read.
	 *
	 * @return bool True when a URL is present.
	 */
	protected function hasUrl( array $block, string $key ): bool
	{
		$url = $this->attributes( $block )[ $key ] ?? null;

		return is_string( $url ) && '' !== trim( $url );
	}
}
