<?php

/**
 * `core/button` / `artisanpack/button` width: `style.dimensions.width`
 * skips serialization, so the renderer adds WordPress's width classes to
 * the wrapper like `save()` does (#921).
 *
 * @since 1.15.2
 */

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

/**
 * @param  array<string, mixed>  $attributes
 */
function renderButtonBlock( array $attributes, string $name = 'artisanpack/button' ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [ [
		'clientId'    => 'button-1',
		'name'        => $name,
		'attributes'  => array_merge( [ 'text' => 'Get Pro', 'url' => '#' ], $attributes ),
		'innerBlocks' => [],
	] ] ] );
}

it( 'adds the legacy width classes for a preset width', function ( string $name ): void {
	$html = renderButtonBlock( [ 'style' => [ 'dimensions' => [ 'width' => '100%' ] ] ], $name );

	expect( $html )->toContain( '<div class="wp-block-button has-custom-width wp-block-button__width wp-block-button__width-100">' );
} )->with( [ 'core/button', 'artisanpack/button' ] );

it( 'keeps the style variation class next to the width classes', function (): void {
	$html = renderButtonBlock( [
		'className' => 'is-style-hollow',
		'style'     => [ 'dimensions' => [ 'width' => '50%' ] ],
	] );

	expect( $html )->toMatch( '/<div class="[^"]*\bwp-block-button__width-50\b[^"]*"/' )
		->and( $html )->toMatch( '/<div class="[^"]*\bis-style-hollow\b[^"]*"/' );
} );

it( 'sets the width variable for an arbitrary percentage', function (): void {
	$html = renderButtonBlock( [ 'style' => [ 'dimensions' => [ 'width' => '60%' ] ] ] );

	expect( $html )->toContain( 'class="wp-block-button has-custom-width wp-block-button__width" style="--wp--block-button--width:60"' );
} );

it( 'sets a length width directly', function (): void {
	$html = renderButtonBlock( [ 'style' => [ 'dimensions' => [ 'width' => '240px' ] ] ] );

	expect( $html )->toContain( 'class="wp-block-button has-custom-width" style="width:240px"' );
} );

it( 'drops a width that is not a plain length', function (): void {
	$html = renderButtonBlock( [ 'style' => [ 'dimensions' => [ 'width' => '1px;position:fixed' ] ] ] );

	expect( $html )->toContain( '<div class="wp-block-button has-custom-width">' )
		->and( $html )->not->toContain( 'position:fixed' );
} );

it( 'leaves a button without a width unchanged', function (): void {
	expect( renderButtonBlock( [] ) )->toContain( '<div class="wp-block-button">' );
} );
