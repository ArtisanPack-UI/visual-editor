<?php

/**
 * `POST media/import` (#1066). The media library isn't installed in this
 * suite, so its helper is stubbed in a separate process-safe way: the
 * function is defined once for the file.
 *
 * @since 1.20.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Media\RemoteImageException;
use ArtisanPackUI\VisualEditor\Services\Media\RemoteImageFetcher;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Tests\TestUser;

const MEDIA_IMPORT_URL = '/visual-editor/api/media/import';

function actAsAuthor( object $test ): void
{
	$test->actingAs( TestUser::create( [
		'name'     => 'Importer',
		'email'    => 'importer+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] ) );
}

function fakeFetcher( ?Throwable $failure = null ): void
{
	app()->instance( RemoteImageFetcher::class, new class( $failure ) extends RemoteImageFetcher {
		public function __construct( protected ?Throwable $failure )
		{
		}

		public function fetch( string $url ): UploadedFile
		{
			if ( null !== $this->failure ) {
				throw $this->failure;
			}

			$path = tempnam( sys_get_temp_dir(), 'test-' );

			file_put_contents( $path, 'bytes' );

			return new UploadedFile( $path, 'a.png', 'image/png', null, true );
		}
	} );
}

it( 'requires sign-in', function (): void {
	$this->postJson( MEDIA_IMPORT_URL, [ 'url' => 'https://cdn.example.com/a.png' ] )->assertUnauthorized();
} );

it( 'validates the request', function ( array $payload ): void {
	actAsAuthor( $this );

	$this->postJson( MEDIA_IMPORT_URL, $payload )->assertUnprocessable();
} )->with( [
	'no url'     => [ [] ],
	'array url'  => [ [ 'url' => [ 'x' ] ] ],
	'long url'   => [ [ 'url' => 'https://example.com/' . str_repeat( 'a', 2100 ) ] ],
	'long alt'   => [ [ 'url' => 'https://example.com/a.png', 'alt' => str_repeat( 'a', 1001 ) ] ],
] );

describe( 'without a media library', function (): void {
	it( 'says so instead of downloading anything', function (): void {
		if ( function_exists( 'apUploadMedia' ) ) {
			$this->markTestSkipped( 'The media library is loaded.' );
		}

		actAsAuthor( $this );
		fakeFetcher( new RuntimeException( 'Should not fetch.' ) );

		$this->postJson( MEDIA_IMPORT_URL, [ 'url' => 'https://cdn.example.com/a.png' ] )->assertStatus( 501 );
	} );
} );

describe( 'with a media library', function (): void {
	beforeEach( function (): void {
		if ( ! function_exists( 'apUploadMedia' ) ) {
			eval( <<<'STUB'
				function apUploadMedia( \Illuminate\Http\UploadedFile $file, array $options = [] ): object
				{
					$GLOBALS['mediaImportUploads'][] = [ 'path' => $file->getPathname(), 'options' => $options ];

					return new class {
						public int $id = 7;

						public function url(): string
						{
							return 'https://site.test/storage/a.png';
						}
					};
				}
			STUB );
		}

		$GLOBALS['mediaImportUploads'] = [];

		actAsAuthor( $this );
		Gate::define( 'create', fn ( Authenticatable $user, string $model ): bool => true );
	} );

	it( 'imports, keeps the alt text, and removes the temporary file', function (): void {
		fakeFetcher();

		$this->postJson( MEDIA_IMPORT_URL, [ 'url' => 'https://cdn.example.com/a.png', 'alt' => 'A cat' ] )
			->assertCreated()
			->assertExactJson( [ 'id' => 7, 'url' => 'https://site.test/storage/a.png' ] );

		[ $upload ] = $GLOBALS['mediaImportUploads'];

		expect( $upload['options'] )->toBe( [ 'alt_text' => 'A cat' ] )
			->and( file_exists( $upload['path'] ) )->toBeFalse();
	} );

	it( 'reports a refused URL, uploading nothing', function (): void {
		fakeFetcher( new RemoteImageException( 'This address cannot be imported.' ) );

		$this->postJson( MEDIA_IMPORT_URL, [ 'url' => 'http://127.0.0.1/a.png' ] )
			->assertUnprocessable()
			->assertJsonPath( 'message', 'This address cannot be imported.' );

		expect( $GLOBALS['mediaImportUploads'] )->toBe( [] );
	} );

	it( 'needs the media library policy', function (): void {
		Gate::define( 'create', fn ( Authenticatable $user, string $model ): bool => false );
		fakeFetcher( new RuntimeException( 'Should not fetch.' ) );

		$this->postJson( MEDIA_IMPORT_URL, [ 'url' => 'https://cdn.example.com/a.png' ] )->assertForbidden();
	} );

	it( 'is throttled to 30 imports a minute', function (): void {
		fakeFetcher( new RemoteImageException( 'This address cannot be imported.' ) );

		for ( $attempt = 0; $attempt < 30; $attempt++ ) {
			$this->postJson( MEDIA_IMPORT_URL, [ 'url' => 'https://cdn.example.com/a.png' ] )->assertUnprocessable();
		}

		$this->postJson( MEDIA_IMPORT_URL, [ 'url' => 'https://cdn.example.com/a.png' ] )->assertTooManyRequests();
	} );
} );
