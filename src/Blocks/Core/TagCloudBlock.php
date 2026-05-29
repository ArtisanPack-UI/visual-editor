<?php

/**
 * Server-rendered `core/tag-cloud` block.
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

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\PostTag;
use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use Illuminate\Support\Collection;

/**
 * Renders cms-framework post tags weighted by post count, matching the
 * upstream `core/tag-cloud` markup. Supports the upstream `numberOfTags`,
 * `showTagCounts`, `smallestFontSize`, and `largestFontSize` attributes.
 *
 * The taxonomy attribute is fixed to `post_tag` for V1 — custom
 * taxonomies are deferred per the issue's "out of scope" list.
 */
class TagCloudBlock extends DynamicBlock
{
	public function name(): string
	{
		return 'artisanpack/tag-cloud';
	}

	public function validateAttrs( array $attrs ): array
	{
		return [
			'numberOfTags'     => max( 1, min( 100, (int) ( $attrs['numberOfTags'] ?? 45 ) ) ),
			'showTagCounts'    => (bool) ( $attrs['showTagCounts'] ?? false ),
			'smallestFontSize' => isset( $attrs['smallestFontSize'] ) && is_string( $attrs['smallestFontSize'] ) ? $attrs['smallestFontSize'] : '8pt',
			'largestFontSize'  => isset( $attrs['largestFontSize'] ) && is_string( $attrs['largestFontSize'] ) ? $attrs['largestFontSize'] : '22pt',
			'className'        => isset( $attrs['className'] ) && is_string( $attrs['className'] ) ? $attrs['className'] : '',
		];
	}

	public function render( array $attrs ): string
	{
		$tags    = $this->fetchTags( $attrs );
		$classes = $this->wrapperClasses( $attrs );

		if ( $tags->isEmpty() ) {
			return sprintf( '<p class="%s"></p>', e( implode( ' ', $classes ) ) );
		}

		[ $smallestPt, $largestPt, $unit ] = $this->parseFontSizes( $attrs );
		$counts                            = $tags->pluck( 'posts_count' )->map( static fn ( $count ): int => (int) $count );
		$minCount                          = max( 1, (int) $counts->min() );
		$maxCount                          = max( $minCount, (int) $counts->max() );

		$items = $tags->map( function ( object $tag ) use ( $minCount, $maxCount, $smallestPt, $largestPt, $unit, $attrs ): string {
			$count = (int) ( $tag->posts_count ?? 0 );
			$size  = $this->scaleFontSize( $count, $minCount, $maxCount, $smallestPt, $largestPt );

			$countMarkup = $attrs['showTagCounts']
				? sprintf( ' <span class="tag-link-count">(%d)</span>', $count )
				: '';

			return sprintf(
				'<a href="%s" class="tag-cloud-link" style="font-size: %s%s" data-wp-tag-count="%d">%s%s</a>',
				e( (string) $tag->permalink ),
				e( $this->formatSize( $size, $unit ) ),
				e( $unit ),
				$count,
				e( (string) $tag->name ),
				$countMarkup
			);
		} )->implode( '' );

		return sprintf(
			'<p class="%s">%s</p>',
			e( implode( ' ', $classes ) ),
			$items
		);
	}

	/**
	 * Returns the tag records used by {@see render()}. Pulled out so
	 * tests can override the data source without exercising the database.
	 *
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return Collection<int, object>
	 */
	protected function fetchTags( array $attrs ): Collection
	{
		return PostTag::query()
			->withCount( 'posts' )
			->orderByDesc( 'posts_count' )
			->orderBy( 'name' )
			->limit( $attrs['numberOfTags'] )
			->get()
			->filter(
				static fn ( object $tag ): bool => (int) ( $tag->posts_count ?? 0 ) > 0
			)
			->values();
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return array{0: float, 1: float, 2: string}
	 */
	protected function parseFontSizes( array $attrs ): array
	{
		$smallest = $this->parseSize( $attrs['smallestFontSize'], 8.0, 'pt' );
		$largest  = $this->parseSize( $attrs['largestFontSize'], 22.0, $smallest[1] );

		// Lock both ends to the same unit so the linear scale is meaningful.
		return [ $smallest[0], $largest[0], $smallest[1] ];
	}

	/**
	 * @return array{0: float, 1: string}
	 */
	protected function parseSize( string $value, float $default, string $defaultUnit ): array
	{
		if ( preg_match( '/^\s*([0-9]+(?:\.[0-9]+)?)\s*([a-z%]+)?\s*$/i', $value, $matches ) === 1 ) {
			return [ (float) $matches[1], $matches[2] ?? $defaultUnit ];
		}

		return [ $default, $defaultUnit ];
	}

	protected function scaleFontSize( int $count, int $min, int $max, float $smallest, float $largest ): float
	{
		if ( $max === $min ) {
			return $smallest;
		}

		$ratio = ( $count - $min ) / ( $max - $min );

		return $smallest + ( $ratio * ( $largest - $smallest ) );
	}

	protected function formatSize( float $size, string $unit ): string
	{
		// Pixel-based units want integer values; pt/em/rem/% keep one
		// decimal so the linear scale doesn't collapse for small ranges.
		if ( in_array( strtolower( $unit ), [ 'px' ], true ) ) {
			return (string) (int) round( $size );
		}

		return rtrim( rtrim( number_format( $size, 1, '.', '' ), '0' ), '.' );
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return array<int, string>
	 */
	protected function wrapperClasses( array $attrs ): array
	{
		$classes = [ 'wp-block-tag-cloud' ];

		if ( '' !== $attrs['className'] ) {
			$classes[] = $attrs['className'];
		}

		return $classes;
	}
}
