<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Support\BlockShape;

describe( 'readName', function (): void {
	it( 'reads the block name from each supported shape', function ( array $block, string $expected ): void {
		expect( BlockShape::readName( $block ) )->toBe( $expected );
	} )->with( [
		'editor tree'       => [ [ 'name' => 'artisanpack/heading' ], 'artisanpack/heading' ],
		'parse_blocks tree' => [ [ 'blockName' => 'core/heading' ], 'core/heading' ],
		'simplified shape'  => [ [ 'type' => 'heading' ], 'heading' ],
	] );

	it( 'prefers `name` over `blockName` and `type`', function (): void {
		expect( BlockShape::readName( [
			'type'      => 'heading',
			'blockName' => 'core/heading',
			'name'      => 'artisanpack/heading',
		] ) )->toBe( 'artisanpack/heading' );
	} );

	it( 'skips empty and non-string keys', function (): void {
		expect( BlockShape::readName( [ 'name' => '', 'blockName' => null, 'type' => 'heading' ] ) )->toBe( 'heading' )
			->and( BlockShape::readName( [ 'name' => [ 'x' ], 'type' => 42 ] ) )->toBe( '' );
	} );

	it( 'returns an empty string when the block carries no name', function (): void {
		expect( BlockShape::readName( [ 'attributes' => [] ] ) )->toBe( '' );
	} );
} );
