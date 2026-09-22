<?php

/**
 * Per-viewport visibility on the navigation block and its children (#798).
 *
 * The `core/navigation` block ships a single `innerBlocks` tree that
 * renders in both the desktop bar and the mobile overlay (the
 * responsive container flips visibility via CSS). Authors who want
 * a CTA that only appears inside the mobile overlay use the existing
 * block-visibility screen-size rule (`docs/visibility.md`, screen-size
 * rule) — `artisanpackVisibility.screenSize.direction = 'show'` +
 * `breakpoints = [ 'sm' ]` on the child block, and the renderer
 * emits a `@media` rule that hides the child at the desktop / tablet
 * breakpoints while leaving it visible inside the mobile drawer.
 *
 * Every block is opted-in to visibility by default (see
 * `VisibilityEvaluator::supportsVisibility()`), so the nav block, its
 * `core/navigation-link` / `core/navigation-submenu` children, and a
 * nested `core/buttons` CTA all flow through the same evaluator. This
 * test locks in that behavior for the specific nav-per-viewport
 * shape called out in #798.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.12.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;

it( 'CSS-hides a nested core/buttons CTA at the non-sm breakpoints via screen-size visibility on the nav tree', function () {
	$renderer = app( BlockRenderer::class );

	$tree = [
		[
			'name'        => 'core/navigation',
			'attributes'  => [ 'overlayMenu' => 'mobile' ],
			'innerBlocks' => [
				[
					'name'        => 'core/navigation-link',
					'attributes'  => [ 'label' => 'Home', 'url' => '/' ],
					'innerBlocks' => [],
				],
				[
					'name'       => 'core/buttons',
					'attributes' => [
						// Show ONLY at the `sm` breakpoint so the CTA is
						// visible inside the mobile overlay drawer and
						// hidden inline on tablet + desktop.
						'artisanpackVisibility' => [
							'screenSize' => [
								'direction'   => 'show',
								'breakpoints' => [ 'sm' ],
							],
						],
					],
					'innerBlocks' => [
						[
							'name'       => 'core/button',
							'attributes' => [
								'text' => 'Get in touch',
								'url'  => '/contact',
							],
							'innerBlocks' => [],
						],
					],
				],
			],
		],
	];

	$html = $renderer->render( $tree );

	// Nav-link renders normally (no visibility slice → no wrap).
	expect( $html )->toContain( 'wp-block-navigation-link' );

	// The buttons CTA IS wrapped in a scope class + carries the
	// `@media` `display:none` rules for the non-`sm` breakpoints.
	// `direction: show` with `[ 'sm' ]` means "hide everywhere else",
	// i.e. hide at `md`, `lg`, `xl`, `2xl`. Exactly one scope wrap
	// is emitted (only the CTA has a screen-size rule).
	expect( $html )
		->toContain( 'data-ve-vis-scope' )
		->and( substr_count( $html, 'data-ve-vis-scope' ) )->toBe( 1 )
		->and( $html )->toContain( 'wp-block-buttons' )
		// md range 768–1023, then lg 1024–1279, xl 1280–1535, 2xl 1536+.
		->and( $html )->toContain( '@media (min-width:768px) and (max-width:1023px)' )
		->and( $html )->toContain( '@media (min-width:1024px) and (max-width:1279px)' )
		->and( $html )->toContain( '@media (min-width:1280px) and (max-width:1535px)' )
		->and( $html )->toContain( '@media (min-width:1536px)' );
} );

it( 'CSS-hides a core/navigation-link at a chosen breakpoint via the screen-size rule', function () {
	$renderer = app( BlockRenderer::class );

	$tree = [
		[
			'name'        => 'core/navigation',
			'attributes'  => [ 'overlayMenu' => 'mobile' ],
			'innerBlocks' => [
				[
					'name'       => 'core/navigation-link',
					'attributes' => [
						'label'                 => 'Contact',
						'url'                   => '/contact',
						// Hide at `md` (768–1023) only.
						'artisanpackVisibility' => [
							'screenSize' => [
								'direction'   => 'hide',
								'breakpoints' => [ 'md' ],
							],
						],
					],
					'innerBlocks' => [],
				],
			],
		],
	];

	$html = $renderer->render( $tree );

	expect( $html )
		->toContain( 'Contact' )
		->and( $html )->toContain( 'data-ve-vis-scope' )
		->and( $html )->toContain( '@media (min-width:768px) and (max-width:1023px)' );
} );

it( 'drops a fully-hidden nav child from output while preserving its siblings and the nav wrapper', function () {
	$renderer = app( BlockRenderer::class );

	$tree = [
		[
			'name'        => 'core/navigation',
			'attributes'  => [ 'overlayMenu' => 'mobile' ],
			'innerBlocks' => [
				[
					'name'        => 'core/navigation-link',
					'attributes'  => [ 'label' => 'Home', 'url' => '/' ],
					'innerBlocks' => [],
				],
				[
					'name'       => 'core/navigation-link',
					'attributes' => [
						'label'                 => 'staff-only-hidden',
						'url'                   => '/staff',
						'artisanpackVisibility' => [ 'hide' => [ 'hidden' => true ] ],
					],
					'innerBlocks' => [],
				],
				[
					'name'        => 'core/navigation-link',
					'attributes'  => [ 'label' => 'About', 'url' => '/about' ],
					'innerBlocks' => [],
				],
			],
		],
	];

	$html = $renderer->render( $tree );

	expect( $html )
		->toContain( 'Home' )
		->and( $html )->toContain( 'About' )
		->and( $html )->not->toContain( 'staff-only-hidden' )
		// The nav wrapper still renders.
		->and( $html )->toContain( '<ul class="wp-block-navigation__container">' );
} );

it( 'defaults every nav child to visible when no visibility slice is set', function () {
	$renderer = app( BlockRenderer::class );

	$tree = [
		[
			'name'        => 'core/navigation',
			'attributes'  => [ 'overlayMenu' => 'mobile' ],
			'innerBlocks' => [
				[
					'name'        => 'core/navigation-link',
					'attributes'  => [ 'label' => 'Home', 'url' => '/' ],
					'innerBlocks' => [],
				],
				[
					'name'        => 'core/navigation-submenu',
					'attributes'  => [ 'label' => 'Products', 'url' => '/products' ],
					'innerBlocks' => [
						[
							'name'        => 'core/navigation-link',
							'attributes'  => [ 'label' => 'Plans', 'url' => '/plans' ],
							'innerBlocks' => [],
						],
					],
				],
			],
		],
	];

	$html = $renderer->render( $tree );

	expect( $html )
		->toContain( 'Home' )
		->and( $html )->toContain( 'Products' )
		->and( $html )->toContain( 'Plans' )
		// No visibility slice → no scope wrapper anywhere in the tree.
		->and( $html )->not->toContain( 'data-ve-vis-scope' );
} );
