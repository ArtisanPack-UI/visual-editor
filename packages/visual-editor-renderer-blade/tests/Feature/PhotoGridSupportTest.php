<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Support\PhotoGridSupport;

/**
 * #594 — Photo Grid wrapper serializer parity.
 *
 * Mirrors `resources/js/visual-editor/blocks/_shared/photo-grid/
 * wrapper.ts` exactly. Confirms the PHP wrapper emits identical
 * classes + CSS variables across attribute combinations and rejects
 * malformed input the same way the JS helper does.
 */

it( 'returns empty result when photoGrid attribute is missing', function () {
	$result = PhotoGridSupport::wrapper( [] );

	expect( $result[ 'classes' ] )->toBe( [] )
		->and( $result[ 'styles' ] )->toBe( [] );
} );

it( 'returns empty result when photoGrid attribute is null', function () {
	$result = PhotoGridSupport::wrapper( [ 'photoGrid' => null ] );

	expect( $result[ 'classes' ] )->toBe( [] )
		->and( $result[ 'styles' ] )->toBe( [] );
} );

it( 'returns empty result when photoGrid.enabled is false', function () {
	$result = PhotoGridSupport::wrapper( [
		'photoGrid' => [
			'enabled'        => false,
			'aspectRatio'    => '1/1',
			'objectFit'      => 'cover',
			'objectPosition' => '50% 50%',
		],
	] );

	expect( $result[ 'classes' ] )->toBe( [] )
		->and( $result[ 'styles' ] )->toBe( [] );
} );

it( 'emits the has-photo-grid class plus aspect/fit/position vars when enabled', function () {
	$result = PhotoGridSupport::wrapper( [
		'photoGrid' => [
			'enabled'        => true,
			'aspectRatio'    => '16/9',
			'objectFit'      => 'cover',
			'objectPosition' => '50% 50%',
		],
	] );

	expect( $result[ 'classes' ] )->toBe( [ 'has-photo-grid' ] )
		->and( $result[ 'styles' ] )->toBe( [
			'--ap-photo-grid-fit'      => 'cover',
			'--ap-photo-grid-position' => '50% 50%',
			'--ap-photo-grid-aspect'   => '16/9',
		] );
} );

it( 'preserves the contain object-fit token', function () {
	$result = PhotoGridSupport::wrapper( [
		'photoGrid' => [
			'enabled'        => true,
			'aspectRatio'    => '1/1',
			'objectFit'      => 'contain',
			'objectPosition' => '30% 70%',
		],
	] );

	expect( $result[ 'styles' ][ '--ap-photo-grid-fit' ] )->toBe( 'contain' )
		->and( $result[ 'styles' ][ '--ap-photo-grid-position' ] )->toBe( '30% 70%' );
} );

it( 'omits the aspect-ratio var when value is null (inherit container)', function () {
	$result = PhotoGridSupport::wrapper( [
		'photoGrid' => [
			'enabled'        => true,
			'aspectRatio'    => null,
			'objectFit'      => 'cover',
			'objectPosition' => '50% 50%',
		],
	] );

	expect( $result[ 'classes' ] )->toBe( [ 'has-photo-grid' ] )
		->and( $result[ 'styles' ] )->not->toHaveKey( '--ap-photo-grid-aspect' );
} );

it( 'accepts decimal aspect ratios', function () {
	$result = PhotoGridSupport::wrapper( [
		'photoGrid' => [
			'enabled'        => true,
			'aspectRatio'    => '21.5/9',
			'objectFit'      => 'cover',
			'objectPosition' => '50% 50%',
		],
	] );

	expect( $result[ 'styles' ][ '--ap-photo-grid-aspect' ] )->toBe( '21.5/9' );
} );

it( 'rejects malformed aspect ratios (drops the aspect var)', function () {
	foreach ( [ '16x9', '16 9', '/9', '16/', '-16/9', '0/9', '16/0', 'abc' ] as $bad ) {
		$result = PhotoGridSupport::wrapper( [
			'photoGrid' => [
				'enabled'        => true,
				'aspectRatio'    => $bad,
				'objectFit'      => 'cover',
				'objectPosition' => '50% 50%',
			],
		] );

		expect( $result[ 'styles' ] )->not->toHaveKey( '--ap-photo-grid-aspect' );
	}
} );

it( 'defaults objectFit to cover for unknown tokens', function () {
	$result = PhotoGridSupport::wrapper( [
		'photoGrid' => [
			'enabled'        => true,
			'aspectRatio'    => '1/1',
			'objectFit'      => 'bogus',
			'objectPosition' => '50% 50%',
		],
	] );

	expect( $result[ 'styles' ][ '--ap-photo-grid-fit' ] )->toBe( 'cover' );
} );

it( 'defaults objectPosition to 50% 50% for empty / non-string values', function () {
	foreach ( [ '', null, 0, false, [] ] as $bad ) {
		$result = PhotoGridSupport::wrapper( [
			'photoGrid' => [
				'enabled'        => true,
				'aspectRatio'    => '1/1',
				'objectFit'      => 'cover',
				'objectPosition' => $bad,
			],
		] );

		expect( $result[ 'styles' ][ '--ap-photo-grid-position' ] )->toBe( '50% 50%' );
	}
} );

it( 'inlineStyle renders declarations as a `key:value;…` string', function () {
	$css = PhotoGridSupport::inlineStyle( [
		'--ap-photo-grid-fit'      => 'cover',
		'--ap-photo-grid-position' => '50% 50%',
		'--ap-photo-grid-aspect'   => '16/9',
	] );

	expect( $css )->toBe( '--ap-photo-grid-fit:cover;--ap-photo-grid-position:50% 50%;--ap-photo-grid-aspect:16/9;' );
} );

it( 'inlineStyle returns an empty string for an empty styles array', function () {
	expect( PhotoGridSupport::inlineStyle( [] ) )->toBe( '' );
} );

it( 'wrapperForBlock returns the class list including a scope class when enabled', function () {
	$classes = PhotoGridSupport::wrapperForBlock( [
		'photoGrid' => [
			'enabled'        => true,
			'aspectRatio'    => '1/1',
			'objectFit'      => 'cover',
			'objectPosition' => '50% 50%',
		],
	] );

	expect( $classes )->toContain( 'has-photo-grid' )
		->and( count( $classes ) )->toBe( 2 );

	// Scope class follows `photo-grid-{12-char-sha1}` and is stable
	// for identical declarations — same input twice should produce
	// the same scope class.
	$again = PhotoGridSupport::wrapperForBlock( [
		'photoGrid' => [
			'enabled'        => true,
			'aspectRatio'    => '1/1',
			'objectFit'      => 'cover',
			'objectPosition' => '50% 50%',
		],
	] );

	expect( $classes )->toEqual( $again );
} );

it( 'wrapperForBlock returns an empty array when disabled', function () {
	expect( PhotoGridSupport::wrapperForBlock( [] ) )->toBe( [] )
		->and( PhotoGridSupport::wrapperForBlock( [ 'photoGrid' => null ] ) )->toBe( [] )
		->and( PhotoGridSupport::wrapperForBlock( [
			'photoGrid' => [ 'enabled' => false ],
		] ) )->toBe( [] );
} );
