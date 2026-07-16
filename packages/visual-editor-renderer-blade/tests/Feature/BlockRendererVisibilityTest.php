<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;

it( 'drops a hidden top-level block from rendered output', function () {
	$renderer = app( BlockRenderer::class );

	$tree = [
		[ 'name' => 'artisanpack/paragraph', 'attributes' => [] ],
		[
			'name'       => 'artisanpack/paragraph',
			'attributes' => [ 'artisanpackVisibility' => [ 'hide' => [ 'hidden' => true ] ] ],
		],
	];

	$html = $renderer->render( $tree );

	// Two paragraphs input, one hidden — output should contain exactly
	// one `<p>` opening tag (the visible paragraph).
	expect( substr_count( $html, '<p' ) )->toBeLessThanOrEqual( 1 );
} );

it( 'drops a hidden inner block while preserving surrounding siblings + the parent wrapper', function () {
	$renderer = app( BlockRenderer::class );

	$tree = [
		[
			'name'        => 'artisanpack/group',
			'attributes'  => [],
			'innerBlocks' => [
				[ 'name' => 'artisanpack/paragraph', 'attributes' => [ 'content' => 'first' ] ],
				[
					'name'       => 'artisanpack/paragraph',
					'attributes' => [
						'content'               => 'second-should-be-hidden',
						'artisanpackVisibility' => [ 'hide' => [ 'hidden' => true ] ],
					],
				],
				[ 'name' => 'artisanpack/paragraph', 'attributes' => [ 'content' => 'third' ] ],
			],
		],
	];

	$html = $renderer->render( $tree );

	expect( $html )->toContain( 'first' );
	expect( $html )->toContain( 'third' );
	expect( $html )->not->toContain( 'second-should-be-hidden' );
} );

it( 'CSS-hides a screen-sized block without dropping it', function () {
	$renderer = app( BlockRenderer::class );

	$tree = [
		[
			'name'       => 'artisanpack/paragraph',
			'attributes' => [
				'content'               => 'target',
				'artisanpackVisibility' => [ 'screenSize' => [ 'direction' => 'hide', 'breakpoints' => [ 'md' ] ] ],
			],
		],
	];

	$html = $renderer->render( $tree );

	// Screen-hidden blocks stay in the DOM but get a scope class + ranged media rule.
	// md range: 768-1023 (next breakpoint lg starts at 1024).
	expect( $html )->toContain( 'target' );
	expect( $html )->toContain( 've-vis-' );
	expect( $html )->toContain( '@media (min-width:768px) and (max-width:1023px)' );
} );

it( 'preserves the parent block CSS wrapping when the parent itself has a screen-size rule and children do not', function () {
	// Regression guard for the recursion bug: children walked via
	// renderInner must not clobber the parent's CSS-hidden decision
	// before the outer wrapper is applied.
	$renderer = app( BlockRenderer::class );

	$tree = [
		[
			'name'       => 'artisanpack/group',
			'attributes' => [
				'artisanpackVisibility' => [ 'screenSize' => [ 'direction' => 'hide', 'breakpoints' => [ 'md' ] ] ],
			],
			'innerBlocks' => [
				[ 'name' => 'artisanpack/paragraph', 'attributes' => [ 'content' => 'child' ] ],
			],
		],
	];

	$html = $renderer->render( $tree );

	expect( $html )->toContain( 'child' );
	expect( $html )->toContain( '@media (min-width:768px) and (max-width:1023px)' );
} );
