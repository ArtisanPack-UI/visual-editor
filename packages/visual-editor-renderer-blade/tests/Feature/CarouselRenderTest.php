<?php

/**
 * `artisanpack/carousel` (#1127): the parts of the Blade output the
 * cross-renderer markup goldens don't cover — translated labels, the
 * stylesheet link and a slide rendered outside its carousel.
 *
 * @since 1.22.0
 */

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

/**
 * @param  array<string, mixed>  $attributes
 * @param  int  $slides
 */
function renderCarouselTree( array $attributes = [], int $slides = 2 ): string
{
	$innerBlocks = [];

	for ( $index = 1; $index <= $slides; $index++ ) {
		$innerBlocks[] = [
			'clientId'    => 'slide-' . $index,
			'name'        => 'artisanpack/carousel-slide',
			'attributes'  => [],
			'innerBlocks' => [ [
				'clientId'    => 'p-' . $index,
				'name'        => 'core/paragraph',
				'attributes'  => [ 'content' => 'Slide ' . $index ],
				'innerBlocks' => [],
			] ],
		];
	}

	return Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [ [
		'clientId'    => 'carousel-1',
		'name'        => 'artisanpack/carousel',
		'attributes'  => $attributes,
		'innerBlocks' => $innerBlocks,
	] ] ] );
}

it( 'translates the region, slide and control labels', function (): void {
	app( 'translator' )->addLines( [
		'*.Carousel'           => 'Karussell',
		'*.:current of :total' => ':current von :total',
		'*.Next slide'         => 'Nächste Folie',
		'*.Go to slide :index' => 'Zu Folie :index',
	], 'en' );

	expect( renderCarouselTree() )
		->toContain( 'aria-label="Karussell"' )
		->toContain( 'aria-label="1 von 2"' )
		->toContain( 'aria-label="Nächste Folie"' )
		->toContain( 'data-dot-label="Zu Folie :index"' );
} );

it( 'skips children that are not slides', function (): void {
	$html = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [ [
		'clientId'    => 'carousel-1',
		'name'        => 'artisanpack/carousel',
		'attributes'  => [],
		'innerBlocks' => [
			[ 'clientId' => 'p-0', 'name' => 'core/paragraph', 'attributes' => [ 'content' => 'Stray' ], 'innerBlocks' => [] ],
			[ 'clientId' => 's-1', 'name' => 'artisanpack/carousel-slide', 'attributes' => [], 'innerBlocks' => [] ],
		],
	] ] ] );

	expect( $html )
		->not->toContain( 'Stray' )
		->toContain( 'aria-label="1 of 1"' );
} );

it( 'prints the autoplay interval only when autoplay is on', function (): void {
	expect( renderCarouselTree() )->not->toContain( 'data-autoplay' )
		->and( renderCarouselTree( [ 'autoplay' => true, 'autoplayInterval' => 8 ] ) )->toContain( 'data-autoplay="8"' );
} );

it( 'labels a slide rendered on its own without numbering it', function (): void {
	$html = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [ [
		'clientId'    => 'slide-1',
		'name'        => 'artisanpack/carousel-slide',
		'attributes'  => [ 'ariaLabel' => 'Lone slide' ],
		'innerBlocks' => [],
	] ] ] );

	expect( $html )
		->toContain( 'class="wp-block-artisanpack-carousel-slide ap-carousel-slide"' )
		->toContain( 'role="group"' )
		->toContain( 'aria-label="Lone slide"' );
} );

it( 'links the carousel stylesheet even without the interactivity script', function (): void {
	expect( Blade::render( '<x-ve-blocks-styles :interactive="false" />' ) )
		->toContain( '/vendor/visual-editor-renderer-blade/frontend/carousel.css' )
		->toContain( 'data-ve-carousel' );
} );
