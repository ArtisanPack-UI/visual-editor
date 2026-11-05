<?php

/**
 * Content source for Post Navigation Link blocks and the Previous Post /
 * Next Post containers. Every link is stamped with both neighbours, so any
 * one of them answers for either direction; a container knows only its own.
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
	 * Containers `PostResolver` stamps with `_resolvedHasAdjacent`, by the
	 * direction each one reads.
	 *
	 * @since 1.20.0
	 *
	 * @var array<string, string>
	 */
	protected const ADJACENT_CONTAINERS = [
		'artisanpack/previous-post' => 'hasPrevious',
		'artisanpack/next-post'     => 'hasNext',
	];

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
		return [
			'artisanpack/post-navigation-link',
			'core/post-navigation-link',
			...array_keys( self::ADJACENT_CONTAINERS ),
		];
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
	 * Containers carry `_resolvedHasAdjacent`; links carry both URLs.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<string, mixed>  $block  A block {@see matches()} accepted.
	 *
	 * @return bool True when the block was resolved.
	 */
	public function isResolved( array $block ): bool
	{
		if ( $this->isContainer( $block ) ) {
			$attributes = $this->attributes( $block );

			return ! array_key_exists( '_resolutionError', $attributes )
				&& array_key_exists( '_resolvedHasAdjacent', $attributes );
		}

		return parent::isResolved( $block );
	}

	/**
	 * Whether the post has the neighbour the condition asks for. A
	 * container answers only for its own direction, so `hasNext` on a
	 * Previous Post container is false: under *Any* the Next Post
	 * container decides, and under *All* the rule fails, as a link with
	 * no next post would.
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
		if ( $this->isContainer( $block ) ) {
			$has       = true === ( $this->attributes( $block )['_resolvedHasAdjacent'] ?? null );
			$direction = self::ADJACENT_CONTAINERS[ $block['name'] ];

			return match ( $condition ) {
				'hasPrevious', 'hasNext' => $condition === $direction && $has,
				default                  => $has,
			};
		}

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
	 * Whether the block is a Previous Post or Next Post container.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<string, mixed>  $block  A block node.
	 *
	 * @return bool True for a container.
	 */
	protected function isContainer( array $block ): bool
	{
		$name = $block['name'] ?? null;

		return is_string( $name ) && array_key_exists( $name, self::ADJACENT_CONTAINERS );
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
