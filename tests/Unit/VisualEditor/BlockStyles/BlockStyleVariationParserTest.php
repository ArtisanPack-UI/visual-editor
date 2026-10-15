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

it( 'treats any partial that declares blockTypes as block-scoped, as WordPress does', function ( mixed $blockTypes, bool $scoped ): void {
	expect( BlockStyleVariationParser::isBlockScoped( [ 'slug' => 'x', 'blockTypes' => $blockTypes ] ) )->toBe( $scoped );
} )->with( [
	'list'         => [ [ 'core/group' ], true ],
	'empty list'   => [ [], true ],
	'bare string'  => [ 'core/group', true ],
	'false'        => [ false, true ],
	'null'         => [ null, false ],
] );

it( 'is not block-scoped without a blockTypes key', function (): void {
	expect( BlockStyleVariationParser::isBlockScoped( [ 'slug' => 'blue', 'styles' => [] ] ) )->toBeFalse();
} );

it( 'contributes no block styles for a block-scoped partial with an empty or invalid blockTypes', function ( mixed $blockTypes ): void {
	expect( $this->parser->fromPartial( [
		'slug'       => 'card',
		'blockTypes' => $blockTypes,
		'styles'     => [ 'color' => [ 'background' => '#fff' ] ],
	] ) )->toBe( [] );
} )->with( [
	'empty list'          => [ [] ],
	'bare string'         => [ 'core/group' ],
	'only invalid names'  => [ [ 'not a block', 42 ] ],
	'null'                => [ null ],
] );

it( 'keeps the valid names of a partially invalid blockTypes list', function (): void {
	$entries = $this->parser->fromPartial( [ 'slug' => 'card', 'blockTypes' => [ 'core/group', 'bad name', 7 ] ] );

	expect( array_column( $entries, 'block' ) )->toBe( [ 'core/group' ] );
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

it( 'keeps one cache entry per directory however often its partials change (R-4)', function (): void {
	config()->set( 'cache.default', 'array' );
	app()->forgetInstance( 'cache' );
	app()->forgetInstance( 'cache.store' );

	$directory = sys_get_temp_dir() . '/ve-block-styles-' . uniqid();
	mkdir( $directory );
	$file = $directory . '/pill.json';

	foreach ( [ 'Pill', 'Capsule', 'Lozenge' ] as $title ) {
		file_put_contents( $file, json_encode( [ 'title' => $title, 'blockTypes' => [ 'core/button' ] ] ) );

		expect( $this->parser->fromDirectory( $directory )[0]['label'] )->toBe( $title );
	}

	unlink( $file );
	rmdir( $directory );

	$storage = ( fn (): array => $this->storage )->call( app( 'cache' )->store()->getStore() );
	$keys    = array_filter( array_keys( $storage ), static fn ( string $key ): bool => str_starts_with( $key, BlockStyleVariationParser::CACHE_PREFIX ) );

	expect( $keys )->toHaveCount( 1 );
} );

it( 'reads a directory whose name contains glob wildcards (R-4)', function (): void {
	$directory = sys_get_temp_dir() . '/ve-block-styles-[x]*?-' . uniqid();
	mkdir( $directory );
	file_put_contents( $directory . '/pill.json', json_encode( [ 'title' => 'Pill', 'blockTypes' => [ 'core/button' ] ] ) );

	$entries = $this->parser->fromDirectory( $directory );

	unlink( $directory . '/pill.json' );
	rmdir( $directory );

	expect( $entries )->toHaveCount( 1 )
		->and( BlockStyleVariationParser::escapeGlob( '/a/[b]*?/' ) )->toBe( '/a/[[]b][*][?]/' );
} );
