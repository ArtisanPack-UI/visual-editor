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

/**
 * @param  array<int, array<string, mixed>>  $slideAttributes  One entry per slide.
 */
function renderCarouselSlides( array $slideAttributes ): string
{
	$innerBlocks = [];

	foreach ( $slideAttributes as $index => $attributes ) {
		$innerBlocks[] = [
			'clientId'    => 'slide-' . $index,
			'name'        => 'artisanpack/carousel-slide',
			'attributes'  => $attributes,
			'innerBlocks' => [ [
				'clientId'    => 'p-' . $index,
				'name'        => 'core/paragraph',
				'attributes'  => [ 'content' => 'Slide ' . ( $index + 1 ) ],
				'innerBlocks' => [],
			] ],
		];
	}

	return app( ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer::class )->render( [ [
		'name'        => 'artisanpack/carousel',
		'attributes'  => [],
		'innerBlocks' => $innerBlocks,
	] ] );
}

it( 'drops a slide Block Visibility hides and numbers only the slides that render', function (): void {
	$html = renderCarouselSlides( [
		[],
		[ 'artisanpackVisibility' => [ 'hide' => [ 'hidden' => true ] ] ],
		[],
	] );

	expect( $html )
		->not->toContain( 'Slide 2' )
		->toContain( 'aria-label="1 of 2"' )
		->toContain( 'aria-label="2 of 2"' )
		->not->toContain( 'of 3' );
} );

it( 'counts a slide with its own label without numbering it', function (): void {
	$html = renderCarouselSlides( [ [ 'ariaLabel' => 'Featured' ], [] ] );

	expect( $html )
		->toContain( 'aria-label="Featured"' )
		->toContain( 'aria-label="2 of 2"' )
		->not->toContain( 'data-ap-carousel-slide' )
		->not->toContain( 'ap-carousel-slide-' );
} );

it( 'puts a screen-size visibility scope on the slide itself, not a wrapper', function (): void {
	$html = renderCarouselSlides( [
		[],
		[ 'artisanpackVisibility' => [ 'screenSize' => [ 'direction' => 'hide', 'breakpoints' => [ 'mobile' ] ] ] ],
	] );

	expect( $html )->toMatch( '/<div class="wp-block-artisanpack-carousel-slide ap-carousel-slide ve-vis-\d+" role="group"/' )
		->and( $html )->not->toContain( '<div class="ve-vis-' )
		->and( $html )->toContain( 'aria-label="2 of 2"' );
} );

it( 'ignores a stored slide token or nonce', function (): void {
	$html = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [ [
		'name'        => 'artisanpack/carousel',
		'attributes'  => [ '_veCarouselSlides' => 'abc' ],
		'innerBlocks' => [ [
			'name'        => 'artisanpack/carousel-slide',
			'attributes'  => [ '_veCarouselSlide' => 'ap-carousel-slide-abc-7' ],
			'innerBlocks' => [],
		] ],
	] ] ] );

	expect( $html )->toContain( 'aria-label="1 of 1"' )
		->not->toContain( 'abc' );
} );

it( 'numbers a nested carousel on its own', function (): void {
	$slide = static fn ( string $id, array $inner ): array => [
		'clientId'    => $id,
		'name'        => 'artisanpack/carousel-slide',
		'attributes'  => [],
		'innerBlocks' => $inner,
	];

	$html = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [ [
		'name'        => 'artisanpack/carousel',
		'attributes'  => [ 'ariaLabel' => 'Outer' ],
		'innerBlocks' => [
			$slide( 'o1', [ [
				'name'        => 'artisanpack/carousel',
				'attributes'  => [ 'ariaLabel' => 'Inner' ],
				'innerBlocks' => [ $slide( 'i1', [] ), $slide( 'i2', [] ), $slide( 'i3', [] ) ],
			] ] ),
			$slide( 'o2', [] ),
		],
	] ] ] );

	expect( substr_count( $html, 'of 3"' ) )->toBe( 3 )
		->and( substr_count( $html, 'of 2"' ) )->toBe( 2 );
} );

it( 'reads "false" and "0" flags as off, like the React and Vue renderers', function (): void {
	$html = renderCarouselTree( [ 'showArrows' => 'false', 'showDots' => '0', 'autoplay' => 'false', 'loop' => '' ] );

	expect( $html )
		->not->toContain( 'wp-block-artisanpack-carousel__controls' )
		->not->toContain( 'data-autoplay' )
		->not->toContain( 'data-loop' );
} );

it( 'links the carousel stylesheet even without the interactivity script', function (): void {
	expect( Blade::render( '<x-ve-blocks-styles :interactive="false" />' ) )
		->toContain( '/vendor/visual-editor-renderer-blade/frontend/carousel.css' )
		->toContain( 'data-ve-carousel' );
} );
