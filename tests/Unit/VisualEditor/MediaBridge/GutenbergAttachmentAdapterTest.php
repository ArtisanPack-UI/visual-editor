<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\MediaBridge\GutenbergAttachmentAdapter;
use Illuminate\Contracts\Support\Arrayable;

function adapterFixture( array $overrides = [] ): array
{
	return array_merge( [
		'id'           => 42,
		'url'          => 'https://cdn.example.test/pic.jpg',
		'mime_type'    => 'image/jpeg',
		'alt_text'     => 'alt',
		'caption'      => 'caption',
		'width'        => 1024,
		'height'       => 768,
		'file_name'    => 'pic.jpg',
		'is_image'     => true,
		'is_video'     => false,
		'is_audio'     => false,
		'is_document'  => false,
		'metadata'     => null,
	], $overrides );
}

it( 'maps the core attachment fields', function () {
	$adapter = new GutenbergAttachmentAdapter();

	$result = $adapter->toGutenberg( adapterFixture() );

	expect( $result )->toMatchArray( [
		'id'         => 42,
		'url'        => 'https://cdn.example.test/pic.jpg',
		'alt'        => 'alt',
		'caption'    => 'caption',
		'mime'       => 'image/jpeg',
		'media_type' => 'image',
		'width'      => 1024,
		'height'     => 768,
		'filename'   => 'pic.jpg',
	] );
} );

it( 'collapses null alt and caption to empty strings', function () {
	$adapter = new GutenbergAttachmentAdapter();

	$result = $adapter->toGutenberg( adapterFixture( [
		'alt_text' => null,
		'caption'  => null,
	] ) );

	expect( $result['alt'] )->toBe( '' )
		->and( $result['caption'] )->toBe( '' );
} );

it( 'omits width, height, and filename when the source lacks them', function () {
	$adapter = new GutenbergAttachmentAdapter();

	$result = $adapter->toGutenberg( adapterFixture( [
		'width'     => null,
		'height'    => null,
		'file_name' => '',
	] ) );

	expect( $result )->not->toHaveKey( 'width' )
		->and( $result )->not->toHaveKey( 'height' )
		->and( $result )->not->toHaveKey( 'filename' );
} );

it( 'infers media_type from the mime prefix when flags are missing', function () {
	$adapter = new GutenbergAttachmentAdapter();

	expect( $adapter->toGutenberg( [
		'id'        => 1,
		'url'       => 'x',
		'mime_type' => 'video/mp4',
	] )['media_type'] )->toBe( 'video' );

	expect( $adapter->toGutenberg( [
		'id'        => 2,
		'url'       => 'x',
		'mime_type' => 'audio/ogg',
	] )['media_type'] )->toBe( 'audio' );

	expect( $adapter->toGutenberg( [
		'id'        => 3,
		'url'       => 'x',
		'mime_type' => 'application/pdf',
	] )['media_type'] )->toBe( 'file' );

	expect( $adapter->toGutenberg( [
		'id'        => 4,
		'url'       => 'x',
		'mime_type' => 'text/csv',
	] )['media_type'] )->toBe( 'file' );
} );

it( 'prefers explicit type flags over the mime prefix', function () {
	$adapter = new GutenbergAttachmentAdapter();

	$result = $adapter->toGutenberg( adapterFixture( [
		'mime_type' => 'image/jpeg',
		'is_image'  => false,
		'is_video'  => true,
	] ) );

	expect( $result['media_type'] )->toBe( 'video' );
} );

it( 'pulls image sizes from the top-level image_sizes helper output', function () {
	$adapter = new GutenbergAttachmentAdapter();

	$result = $adapter->toGutenberg( adapterFixture( [
		'image_sizes' => [
			'thumbnail' => 'https://cdn.example.test/thumb.jpg',
			'medium'    => 'https://cdn.example.test/medium.jpg',
		],
	] ) );

	expect( $result['sizes'] )->toEqual( [
		'thumbnail' => [ 'url' => 'https://cdn.example.test/thumb.jpg' ],
		'medium'    => [ 'url' => 'https://cdn.example.test/medium.jpg' ],
	] );
} );

it( 'accepts richer metadata.sizes entries with dimensions', function () {
	$adapter = new GutenbergAttachmentAdapter();

	$result = $adapter->toGutenberg( adapterFixture( [
		'metadata' => [
			'sizes' => [
				'thumbnail' => [
					'url'    => 'https://cdn.example.test/thumb.jpg',
					'width'  => 150,
					'height' => 150,
				],
				'medium' => 'https://cdn.example.test/medium.jpg',
			],
		],
	] ) );

	expect( $result['sizes'] )->toEqual( [
		'thumbnail' => [
			'url'    => 'https://cdn.example.test/thumb.jpg',
			'width'  => 150,
			'height' => 150,
		],
		'medium' => [ 'url' => 'https://cdn.example.test/medium.jpg' ],
	] );
} );

it( 'drops malformed size entries instead of emitting a broken url', function () {
	$adapter = new GutenbergAttachmentAdapter();

	$result = $adapter->toGutenberg( adapterFixture( [
		'metadata' => [
			'sizes' => [
				'missing' => [ 'width' => 10 ],
				'broken'  => 123,
				'good'    => [ 'url' => 'https://cdn.example.test/ok.jpg' ],
			],
		],
	] ) );

	expect( $result['sizes'] )->toEqual( [
		'good' => [ 'url' => 'https://cdn.example.test/ok.jpg' ],
	] );
} );

it( 'omits the sizes key when no sizes are available', function () {
	$adapter = new GutenbergAttachmentAdapter();

	$result = $adapter->toGutenberg( adapterFixture() );

	expect( $result )->not->toHaveKey( 'sizes' );
} );

it( 'accepts an Arrayable record', function () {
	$adapter = new GutenbergAttachmentAdapter();

	$record = new class implements Arrayable {
		public function toArray(): array
		{
			return adapterFixture( [ 'id' => 99 ] );
		}
	};

	$result = $adapter->toGutenberg( $record );

	expect( $result['id'] )->toBe( 99 );
} );

it( 'accepts a plain object with toArray()', function () {
	$adapter = new GutenbergAttachmentAdapter();

	$record = new class {
		public function toArray(): array
		{
			return adapterFixture( [ 'id' => 77 ] );
		}
	};

	$result = $adapter->toGutenberg( $record );

	expect( $result['id'] )->toBe( 77 );
} );

it( 'maps a list of records without mutating the inputs', function () {
	$adapter = new GutenbergAttachmentAdapter();

	$inputs = [
		adapterFixture( [ 'id' => 1 ] ),
		adapterFixture( [ 'id' => 2, 'is_image' => false, 'is_video' => true, 'mime_type' => 'video/mp4' ] ),
	];

	$result = $adapter->toGutenbergList( $inputs );

	expect( $result )->toHaveCount( 2 )
		->and( $result[0]['id'] )->toBe( 1 )
		->and( $result[1]['media_type'] )->toBe( 'video' )
		->and( $inputs[0]['id'] )->toBe( 1 );
} );

it( 'is resolvable from the service container', function () {
	$adapter = app( GutenbergAttachmentAdapter::class );

	expect( $adapter )->toBeInstanceOf( GutenbergAttachmentAdapter::class );
} );
