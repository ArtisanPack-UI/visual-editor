<?php

/**
 * Unit tests for the background block support (#873): the Group
 * background panel's `style.background.*` keys reaching the wrapper.
 *
 * @since 1.15.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

/**
 * Compile a `style.background` payload and return the inline style.
 *
 * @param  array<string, mixed>  $background
 */
function compileBackgroundStyle( array $background ): string
{
	return BlockSupports::compile( [ 'style' => [ 'background' => $background ] ] )['style'];
}

describe( 'BlockSupports::compile (background)', function (): void {
	it( 'emits image, size, position, repeat and attachment', function (): void {
		$style = compileBackgroundStyle( [
			'backgroundImage'      => [ 'url' => 'https://example.com/hero.jpg', 'id' => 7, 'source' => 'file', 'title' => 'Hero' ],
			'backgroundSize'       => 'contain',
			'backgroundPosition'   => '25% 75%',
			'backgroundRepeat'     => 'no-repeat',
			'backgroundAttachment' => 'fixed',
		] );

		expect( $style )->toBe(
			"background-image: url('https://example.com/hero.jpg'); background-size: contain; background-position: 25% 75%; background-repeat: no-repeat; background-attachment: fixed;",
		);
	} );

	it( 'accepts a bare URL string for the image', function (): void {
		expect( compileBackgroundStyle( [ 'backgroundImage' => '/storage/hero.jpg' ] ) )
			->toBe( "background-image: url('/storage/hero.jpg'); background-size: cover;" );
	} );

	it( 'defaults the size to cover, like WordPress core', function (): void {
		expect( compileBackgroundStyle( [ 'backgroundImage' => [ 'url' => 'https://example.com/a.jpg' ] ] ) )
			->toContain( 'background-size: cover' )
			->not->toContain( 'background-position' );
	} );

	it( 'centres a contain image that has no position', function (): void {
		expect( compileBackgroundStyle( [
			'backgroundImage' => [ 'url' => 'https://example.com/a.jpg' ],
			'backgroundSize'  => 'contain',
		] ) )->toContain( 'background-position: 50% 50%' );
	} );

	it( 'emits nothing without an image', function (): void {
		expect( compileBackgroundStyle( [
			'backgroundSize'     => 'cover',
			'backgroundPosition' => 'center',
		] ) )->toBe( '' );
	} );

	it( 'drops an image with an unsafe scheme', function (): void {
		expect( compileBackgroundStyle( [ 'backgroundImage' => [ 'url' => 'javascript:alert(1)' ] ] ) )->toBe( '' );
	} );

	it( 'percent-encodes characters that could close the url() token or the declaration', function (): void {
		$style = compileBackgroundStyle( [
			'backgroundImage' => [ 'url' => "https://example.com/a'); color: red; (b.jpg" ],
		] );

		expect( $style )
			->toContain( "url('https://example.com/a%27%29%3B%20color:%20red%3B%20%28b.jpg')" )
			->not->toContain( '; color: red' );
	} );

	it( 'drops unsafe size / position / repeat values and unknown attachments', function (): void {
		$style = compileBackgroundStyle( [
			'backgroundImage'      => [ 'url' => 'https://example.com/a.jpg' ],
			'backgroundSize'       => 'cover; position: fixed',
			'backgroundPosition'   => '} body { color: red',
			'backgroundRepeat'     => 'repeat;x',
			'backgroundAttachment' => 'sticky',
		] );

		expect( $style )->toBe( "background-image: url('https://example.com/a.jpg'); background-size: cover;" );
	} );

	it( 'lets the image layer win over a custom gradient shorthand', function (): void {
		$style = BlockSupports::compile( [
			'style' => [
				'color'      => [ 'gradient' => 'linear-gradient(red, blue)' ],
				'background' => [ 'backgroundImage' => [ 'url' => 'https://example.com/a.jpg' ] ],
			],
		] )['style'];

		expect( strpos( $style, 'background-image' ) )->toBeGreaterThan( strpos( $style, 'background: linear-gradient' ) );
	} );
} );
