<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;

it( 'drops a hidden top-level block from rendered output', function (): void {
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

it( 'drops a hidden inner block while preserving surrounding siblings + the parent wrapper', function (): void {
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

it( 'CSS-hides a screen-sized block without dropping it', function (): void {
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

it( 'preserves the parent block CSS wrapping when the parent itself has a screen-size rule and children do not', function (): void {
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

/**
 * Renders a paragraph with the given screen-size rule and returns the
 * emitted `<style>` contents (#974).
 */
function screenSizeCss( array $screenSize ): string
{
	$html = app( BlockRenderer::class )->render( [
		[
			'name'       => 'artisanpack/paragraph',
			'attributes' => [
				'content'               => 'target',
				'artisanpackVisibility' => [ 'screenSize' => $screenSize ],
			],
		],
	] );

	expect( $html )->toContain( 'target' );

	return 1 === preg_match( '/<style>(.*?)<\/style>/s', $html, $match ) ? $match[1] : '';
}

it( 'hides the mobile key at max-width 767px', function (): void {
	expect( screenSizeCss( [ 'direction' => 'hide', 'breakpoints' => [ 'mobile' ] ] ) )
		->toMatch( '/^@media \(max-width:767px\)\{\.ve-vis-\d+\{display:none !important;\}\}$/' );
} );

it( 'hides the tablet key at max-width 1023px', function (): void {
	expect( screenSizeCss( [ 'direction' => 'hide', 'breakpoints' => [ 'tablet' ] ] ) )
		->toMatch( '/^@media \(max-width:1023px\)\{\.ve-vis-\d+\{display:none !important;\}\}$/' );
} );

it( 'combines device keys with legacy ranges', function (): void {
	$css = screenSizeCss( [ 'direction' => 'hide', 'breakpoints' => [ 'mobile', 'xl' ] ] );

	expect( $css )->toContain( '@media (max-width:767px)' );
	expect( $css )->toContain( '@media (min-width:1280px) and (max-width:1535px)' );
} );

it( 'keeps legacy keys emitting their ranges unchanged', function (): void {
	$css = screenSizeCss( [ 'direction' => 'hide', 'breakpoints' => [ 'sm', '2xl' ] ] );

	expect( $css )->toContain( '@media (min-width:640px) and (max-width:767px)' );
	expect( $css )->toContain( '@media (min-width:1536px){' );
	expect( $css )->not->toContain( '@media (max-width:' );
} );

it( 'shows a block only on mobile by hiding it from 768px up', function (): void {
	$css = screenSizeCss( [ 'direction' => 'show', 'breakpoints' => [ 'mobile' ] ] );

	expect( $css )->toContain( '@media (min-width:768px) and (max-width:1023px)' );
	expect( $css )->toContain( '@media (min-width:1024px) and (max-width:1279px)' );
	expect( $css )->toContain( '@media (min-width:1536px){' );
	expect( $css )->not->toContain( '640px' );
	expect( $css )->not->toContain( '@media (max-width:' );
} );

it( 'follows a host-narrowed mobile width', function (): void {
	config()->set( 'artisanpack.visual-editor.breakpoints', [ 'mobile' => '599px' ] );
	app()->forgetInstance( BreakpointRegistry::class );

	expect( screenSizeCss( [ 'direction' => 'hide', 'breakpoints' => [ 'mobile' ] ] ) )
		->toContain( '@media (max-width:599px)' );
} );
