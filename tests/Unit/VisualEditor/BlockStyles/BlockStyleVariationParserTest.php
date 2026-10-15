<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\BlockStyles\BlockStyleVariationParser;

beforeEach( function (): void {
	$this->parser = new BlockStyleVariationParser();
} );

it( 'reads styles.blocks.*.variations from theme.json styles', function (): void {
	$entries = $this->parser->fromStyles( [
		'blocks' => [
			'core/group' => [
				'color'      => [ 'background' => '#fff' ],
				'variations' => [
					'card'   => [ 'title' => 'Card', 'border' => [ 'radius' => '12px' ] ],
					'hollow' => [ 'border' => [ 'width' => '1px' ] ],
				],
			],
			'core/button' => [ 'color' => [ 'text' => '#000' ] ],
		],
	] );

	expect( $entries )->toBe( [
		[ 'block' => 'core/group', 'slug' => 'card', 'label' => 'Card', 'style' => [ 'border' => [ 'radius' => '12px' ] ], 'isDefault' => false ],
		[ 'block' => 'core/group', 'slug' => 'hollow', 'label' => 'Hollow', 'style' => [ 'border' => [ 'width' => '1px' ] ], 'isDefault' => false ],
	] );
} );

it( 'skips malformed block names, slugs and nodes', function (): void {
	$entries = $this->parser->fromStyles( [
		'blocks' => [
			'not a block' => [ 'variations' => [ 'x' => [] ] ],
			'core/group'  => [ 'variations' => [ '!!!' => [], 'fine' => 'not-an-array' ] ],
			'core/quote'  => 'nope',
		],
	] );

	expect( $entries )->toBe( [] );
} );

it( 'reads a styles/*.json partial with blockTypes', function (): void {
	$entries = $this->parser->fromPartial( [
		'version'    => 3,
		'title'      => 'Glow',
		'slug'       => 'glow',
		'blockTypes' => [ 'core/button', 'core/group', 'bad name' ],
		'styles'     => [ 'shadow' => '0 0 24px #22d3ee' ],
	] );

	expect( $entries )->toHaveCount( 2 )
		->and( $entries[0] )->toBe( [ 'block' => 'core/button', 'slug' => 'glow', 'label' => 'Glow', 'style' => [ 'shadow' => '0 0 24px #22d3ee' ], 'isDefault' => false ] )
		->and( $entries[1]['block'] )->toBe( 'core/group' );
} );

it( 'ignores theme style variation partials without blockTypes', function (): void {
	expect( $this->parser->fromPartial( [ 'slug' => 'blue', 'settings' => [], 'styles' => [] ] ) )->toBe( [] );
} );

it( 'falls back to the filename for a partial without a slug', function (): void {
	$entries = $this->parser->fromPartial( [ 'blockTypes' => [ 'core/button' ] ], 'pillShape' );

	expect( $entries[0]['slug'] )->toBe( 'pill-shape' )
		->and( $entries[0]['label'] )->toBe( 'Pill shape' );
} );

it( 'reads every partial in a directory, skipping invalid files', function (): void {
	$directory = sys_get_temp_dir() . '/ve-block-styles-' . uniqid();
	mkdir( $directory );
	file_put_contents( $directory . '/pill.json', json_encode( [ 'title' => 'Pill', 'blockTypes' => [ 'core/button' ], 'styles' => [ 'border' => [ 'radius' => '999px' ] ] ] ) );
	file_put_contents( $directory . '/blue.json', json_encode( [ 'title' => 'Blue', 'styles' => [] ] ) );
	file_put_contents( $directory . '/broken.json', '{ nope' );

	$entries = $this->parser->fromDirectory( $directory );

	array_map( 'unlink', glob( $directory . '/*.json' ) );
	rmdir( $directory );

	expect( $entries )->toHaveCount( 1 )
		->and( $entries[0]['slug'] )->toBe( 'pill' )
		->and( $entries[0]['label'] )->toBe( 'Pill' );
} );

it( 'returns nothing for a missing directory', function (): void {
	expect( $this->parser->fromDirectory( '/definitely/not/here' ) )->toBe( [] );
} );

it( 're-reads a directory when a partial changes', function (): void {
	$directory = sys_get_temp_dir() . '/ve-block-styles-' . uniqid();
	mkdir( $directory );
	file_put_contents( $directory . '/pill.json', json_encode( [ 'title' => 'Pill', 'blockTypes' => [ 'core/button' ] ] ) );

	expect( $this->parser->fromDirectory( $directory )[0]['label'] )->toBe( 'Pill' );

	file_put_contents( $directory . '/pill.json', json_encode( [ 'title' => 'Capsule', 'blockTypes' => [ 'core/button' ] ] ) );
	touch( $directory . '/pill.json', time() + 10 );
	clearstatcache();

	$label = $this->parser->fromDirectory( $directory )[0]['label'];

	unlink( $directory . '/pill.json' );
	rmdir( $directory );

	expect( $label )->toBe( 'Capsule' );
} );

it( 're-reads a partial edited without a new modification time', function (): void {
	$directory = sys_get_temp_dir() . '/ve-block-styles-' . uniqid();
	mkdir( $directory );
	$file = $directory . '/pill.json';
	file_put_contents( $file, json_encode( [ 'title' => 'Pill', 'blockTypes' => [ 'core/button' ] ] ) );
	touch( $file, 1700000000 );
	clearstatcache();

	expect( $this->parser->fromDirectory( $directory )[0]['label'] )->toBe( 'Pill' );

	file_put_contents( $file, json_encode( [ 'title' => 'Capsule', 'blockTypes' => [ 'core/button' ] ] ) );
	touch( $file, 1700000000 );
	clearstatcache();

	$label = $this->parser->fromDirectory( $directory )[0]['label'];

	unlink( $file );
	rmdir( $directory );

	expect( $label )->toBe( 'Capsule' );
} );
