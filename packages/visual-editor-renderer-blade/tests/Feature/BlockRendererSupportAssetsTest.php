<?php

/**
 * `BlockRenderer::flushSupportAssets()` for hosts that render directly
 * instead of through `<x-ve-blocks>` / `<x-ve-template>`.
 *
 * @since 1.14.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use Illuminate\Support\Facades\Blade;

/**
 * A paragraph with a per-block link color.
 *
 * @return array<string, mixed>
 */
function supportAssetsLinkParagraph(): array
{
	return [
		'clientId'    => 'p-1',
		'name'        => 'core/paragraph',
		'attributes'  => [
			'content' => 'See <a href="/x">this</a>',
			'style'   => [ 'elements' => [ 'link' => [ 'color' => [ 'text' => '#ff0000' ] ] ] ],
		],
		'innerBlocks' => [],
	];
}

it( 'returns the elements CSS a direct render collected', function (): void {
	$renderer = app( BlockRenderer::class );
	$html     = $renderer->render( [ supportAssetsLinkParagraph() ] );
	$assets   = $renderer->flushSupportAssets();

	preg_match( '/wp-elements-[a-f0-9]{8}/', $html, $class );

	expect( $class )->not->toBeEmpty()
		->and( $assets['css'] )->toContain( '<style data-ve-elements>' )
		->and( $assets['css'] )->toContain( '.' . $class[0] . ' a:where(:not(.wp-element-button)){color: #ff0000 !important;}' )
		->and( $assets['scripts'] )->toBe( '' );
} );

it( 'drains the accumulators so a later component render does not repeat the rules', function (): void {
	$renderer = app( BlockRenderer::class );
	$renderer->render( [ supportAssetsLinkParagraph() ] );
	$renderer->flushSupportAssets();

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [ [
		'clientId'    => 'p-2',
		'name'        => 'core/paragraph',
		'attributes'  => [ 'content' => 'Plain' ],
		'innerBlocks' => [],
	] ] ] );

	expect( $rendered )->not->toContain( 'data-ve-elements' )
		->and( $renderer->flushSupportAssets() )->toBe( [ 'css' => '', 'scripts' => '' ] );
} );

it( 'returns the animation runtime as a script for the end of the output', function (): void {
	$renderer = app( BlockRenderer::class );
	$renderer->render( [ [
		'clientId'    => 'cover-1',
		'name'        => 'artisanpack/cover',
		'attributes'  => [
			'url'                   => 'https://example.test/image.jpg',
			'alt'                   => 'Cover image',
			'artisanpackAnimations' => [ 'entrance' => [ 'name' => 'fade-in-up' ] ],
		],
		'innerBlocks' => [],
	] ] );

	$assets = $renderer->flushSupportAssets();

	expect( $assets['css'] )->toContain( '<style data-ve-animations>' )
		->and( $assets['css'] )->toContain( '<noscript>' )
		->and( $assets['scripts'] )->toContain( 'IntersectionObserver' );
} );
