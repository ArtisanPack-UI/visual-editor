<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

it( 'wires the block-animations bag into the cover blade partial', function () {
	$tree = [
		[
			'clientId'    => 'cover-1',
			'name'        => 'artisanpack/cover',
			'attributes'  => [
				'url'                   => 'https://example.test/image.jpg',
				'alt'                   => 'Cover image',
				'artisanpackAnimations' => [
					'entrance' => [
						'name'      => 'fade-in-up',
						'threshold' => 0.3,
					],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	// The block wrapper picks up the runtime classes + data attributes.
	expect( $rendered )->toContain( 'ap-anim ap-anim-pre' );
	expect( $rendered )->toContain( 'data-ap-anim-entrance="fade-in-up"' );
	expect( $rendered )->toContain( 'data-ap-anim-threshold="0.3"' );

	// The accumulator emits a single `<style data-ve-animations>` block
	// with the `@keyframes` definitions plus the scoped rule.
	expect( $rendered )->toContain( '<style data-ve-animations>' );
	expect( $rendered )->toContain( '@keyframes apFadeInUp' );
	expect( $rendered )->toContain( '@media (prefers-reduced-motion: reduce)' );

	// The noscript fallback reveals the block when JS is disabled.
	expect( $rendered )->toContain( '<noscript>' );
	expect( $rendered )->toContain( 'opacity: 1' );

	// The inline runtime ships only when at least one entrance animation
	// is on the page.
	expect( $rendered )->toContain( 'data-ap-anim-entrance' );
	expect( $rendered )->toContain( 'IntersectionObserver' );
} );

it( 'wires the block-animations bag into the image blade partial via wrapperAttrs', function () {
	$tree = [
		[
			'clientId'    => 'image-1',
			'name'        => 'artisanpack/image',
			'attributes'  => [
				'url'                   => 'https://example.test/photo.jpg',
				'alt'                   => 'Photo',
				'artisanpackAnimations' => [
					'entrance' => [ 'name' => 'fade-in-up', 'threshold' => 0.3 ],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )->toContain( 'data-ap-anim-entrance="fade-in-up"' );
	expect( $rendered )->toContain( 'data-ap-anim-threshold="0.3"' );
	expect( $rendered )->toContain( 'ap-anim' );
	expect( $rendered )->toContain( 'ap-anim-pre' );
	expect( $rendered )->toContain( '<style data-ve-animations>' );
	expect( $rendered )->toContain( '@keyframes apFadeInUp' );
	expect( $rendered )->toContain( 'IntersectionObserver' );
} );

it( 'inlines the runtime with the reduced-motion check before the no-IO branch', function () {
	// Regression for the CodeRabbit critical finding: the inline
	// runtime must respect `prefers-reduced-motion: reduce` in
	// no-IntersectionObserver browsers too. Verifying the source
	// ordering is the closest the Pest layer can get without a JS
	// engine — vitest can't reach the Blade-embedded script.
	$tree = [
		[
			'clientId'    => 'image-1',
			'name'        => 'artisanpack/image',
			'attributes'  => [
				'url'                   => 'https://example.test/photo.jpg',
				'artisanpackAnimations' => [ 'entrance' => [ 'name' => 'fade-in' ] ],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = \Illuminate\Support\Facades\Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	// Find the `observeEntry` body specifically — `! hasIO` also
	// appears earlier as part of the comment / variable declaration,
	// which would confuse a naïve `strpos`.
	$observe = strpos( $rendered, 'function observeEntry' );
	expect( $observe )->not->toBeFalse();

	$body      = substr( $rendered, $observe );
	$reducedAt = strpos( $body, 'reducedMotion &&' );
	$hasIoAt   = strpos( $body, 'if ( ! hasIO )' );

	expect( $reducedAt )->not->toBeFalse();
	expect( $hasIoAt )->not->toBeFalse();
	expect( $reducedAt < $hasIoAt )->toBeTrue();
} );

it( 'omits the animation infrastructure entirely when no block opts in', function () {
	$tree = [
		[
			'clientId'    => 'cover-1',
			'name'        => 'artisanpack/cover',
			'attributes'  => [ 'url' => 'https://example.test/image.jpg' ],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )->not->toContain( 'data-ve-animations' );
	expect( $rendered )->not->toContain( 'data-ap-anim-entrance' );
	expect( $rendered )->not->toContain( 'IntersectionObserver' );
} );
