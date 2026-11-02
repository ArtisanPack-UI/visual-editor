<?php

/**
 * Covers the Image block's border and shadow output on the `<img>` (#1052).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

function renderBorderedImage( array $attributes, string $name = 'core/image' ): string
{
	return app( BlockRenderer::class )->render( [
		[
			'clientId'    => 'image',
			'name'        => $name,
			'attributes'  => [ 'url' => 'https://example.test/a.jpg', 'alt' => 'x', ...$attributes ],
			'innerBlocks' => [],
		],
	] );
}

function imageTagOf( string $html ): string
{
	preg_match( '/<img[^>]*>/', $html, $matches );

	return $matches[0] ?? '';
}

function figureTagOf( string $html ): string
{
	preg_match( '/<figure[^>]*>/', $html, $matches );

	return $matches[0] ?? '';
}

it( 'paints the border and radius on the image, not the figure', function ( string $name ): void {
	$html = renderBorderedImage( [
		'id'          => 7,
		'caption'     => 'Caption',
		'sizeSlug'    => 'full',
		'borderColor' => 'primary',
		'style'       => [ 'border' => [ 'width' => '3px', 'radius' => '12px', 'style' => 'solid' ] ],
	], $name );

	expect( figureTagOf( $html ) )->toBe( '<figure class="wp-block-image size-full has-custom-border">' );
	expect( imageTagOf( $html ) )
		->toContain( 'class="has-border-color has-primary-border-color wp-image-7"' )
		->toContain( 'style="border-radius: 12px; border-style: solid; border-width: 3px"' );
	expect( $html )->toContain( '<figcaption class="wp-element-caption">Caption</figcaption>' );
} )->with( [ 'core/image', 'artisanpack/image' ] );

it( 'keeps spacing and alignment on the figure', function (): void {
	$figure = figureTagOf( renderBorderedImage( [
		'align' => 'center',
		'style' => [
			'border'  => [ 'width' => '1px' ],
			'spacing' => [ 'margin' => [ 'top' => '1rem' ] ],
		],
	] ) );

	expect( $figure )
		->toContain( 'aligncenter' )
		->toContain( 'style="margin-top: 1rem;"' )
		->not->toContain( 'border-width' );
} );

it( 'prints the border ahead of the image styles', function (): void {
	expect( imageTagOf( renderBorderedImage( [
		'aspectRatio' => '16/9',
		'scale'       => 'cover',
		'width'       => '300px',
		'style'       => [ 'border' => [ 'radius' => '4px' ] ],
	] ) ) )->toContain( 'style="border-radius: 4px;aspect-ratio:16/9;object-fit:cover;width:300px"' );
} );

it( 'prints no border markers for an image without a border', function (): void {
	$html = renderBorderedImage( [ 'style' => [ 'spacing' => [ 'margin' => [ 'top' => '1rem' ] ] ] ] );

	expect( $html )->not->toContain( 'has-custom-border' );
	expect( imageTagOf( $html ) )->not->toContain( 'class=' )->not->toContain( 'style=' );
} );

it( 'tolerates a legacy string style', function (): void {
	expect( renderBorderedImage( [ 'style' => 'default' ] ) )->not->toContain( 'has-custom-border' );
} );

it( 'scopes a shadow to the image', function ( mixed $shadow ): void {
	$html = renderBorderedImage( [ 'style' => [ 'shadow' => $shadow ] ] );

	expect( imageTagOf( $html ) )->toMatch( '/class="ve-bs-[^"]+"/' );
	expect( figureTagOf( $html ) )->not->toContain( 've-bs-' );
} )->with( [
	'preset reference' => 'var:preset|shadow|natural',
	'structured'       => [ [ 'offsetY' => '4px', 'blur' => '8px', 'color' => '#000000' ] ],
] );

it( 'keeps a gradient border and shadow on the figure', function (): void {
	$html = renderBorderedImage( [ 'style' => [
		'border' => [ 'gradient' => 'linear-gradient(red, blue)', 'width' => '2px' ],
		'shadow' => [ 'blur' => '8px', 'gradient' => 'linear-gradient(red, blue)' ],
	] ] );

	expect( figureTagOf( $html ) )
		->toContain( 've-gb-' )
		->toContain( 've-bs-' )
		->toContain( 'border-width: 2px' )
		->not->toContain( 'has-custom-border' );
	expect( imageTagOf( $html ) )->not->toContain( 'class=' );
} );

it( 'keeps a solid shadow on the image beside a gradient border', function (): void {
	$html = renderBorderedImage( [ 'style' => [
		'border' => [ 'gradient' => 'linear-gradient(red, blue)', 'width' => '2px' ],
		'shadow' => 'var:preset|shadow|natural',
	] ] );

	expect( figureTagOf( $html ) )->toContain( 've-gb-' )->not->toContain( 've-bs-' );
	expect( imageTagOf( $html ) )->toContain( 've-bs-' );
} );

it( 'moves state and responsive border overrides to the image', function (): void {
	$html = renderBorderedImage( [
		'style'      => [ 'border' => [ 'width' => '1px' ] ],
		'states'     => [
			'_scopeId'             => 'abc',
			'border.color'         => [ 'hover' => '#ff0000' ],
			'transition'           => [ 'hover' => 'all 200ms ease' ],
			'dimensions.transform' => [ 'hover' => 'scale(1.1)' ],
		],
		'responsive' => [
			'style.border.width'    => [ 'md' => '5px' ],
			'style.spacing.margin'  => [ 'md' => '2rem' ],
		],
	] );

	$figure = figureTagOf( $html );
	$image  = imageTagOf( $html );

	expect( $figure )->toContain( 'ap-state-abc' )->toContain( 've-r-' );
	expect( $image )->toContain( 'ap-state-abc-img' )->toContain( 've-r-' );

	preg_match( '/ve-r-[a-z0-9]+/', $figure, $figureScope );
	preg_match( '/ve-r-[a-z0-9]+/', $image, $imageScope );

	expect( $figureScope[0] )->not->toBe( $imageScope[0] );
} );

it( 'writes a moved state rule for the image scope only', function (): void {
	$split = BlockSupports::splitBorderAndShadow( [
		'states' => [
			'_scopeId'             => 'abc',
			'border.color'         => [ 'hover' => '#ff0000' ],
			'transition'           => [ 'hover' => 'all 200ms ease' ],
			'dimensions.transform' => [ 'hover' => 'scale(1.1)' ],
		],
	] );

	$wrapper = BlockSupports::compile( $split['wrapper'] )['statesRules'];
	$element = BlockSupports::compile( $split['element'] )['statesRules'];

	expect( $wrapper )->toContain( '.ap-state-abc:hover' )->toContain( 'transform' )->not->toContain( 'border-color' );
	expect( $element )->toContain( '.ap-state-abc-img:hover' )->toContain( 'border-color' )->toContain( 'transition' )->not->toContain( 'transform' );
} );

it( 'splits overrides without touching the rest of the bag', function (): void {
	$split = BlockSupports::splitBorderAndShadow( [
		'className' => 'keep',
		'states'    => [
			'_scopeId'      => 'abc',
			'shadow'        => [ 'hover' => 'var:preset|shadow|deep' ],
			'style.opacity' => [ 'hover' => '0.5' ],
		],
	] );

	expect( $split['wrapper'] )->toBe( [
		'className' => 'keep',
		'states'    => [ '_scopeId' => 'abc', 'style.opacity' => [ 'hover' => '0.5' ] ],
	] );
	expect( $split['element'] )->toBe( [
		'states' => [ 'shadow' => [ 'hover' => 'var:preset|shadow|deep' ], '_scopeId' => 'abc-img' ],
	] );
} );
