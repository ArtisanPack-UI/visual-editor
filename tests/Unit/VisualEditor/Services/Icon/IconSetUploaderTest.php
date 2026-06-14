<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Icon\IconSetUploader;
use ArtisanPackUI\VisualEditor\Services\Icon\PrefixCollisionException;
use ArtisanPackUI\VisualEditor\Services\Icon\SvgSanitizer;
use ArtisanPackUI\VisualEditor\Services\Icon\UploadedIconSet;
use ArtisanPackUI\VisualEditor\Services\Icon\UploadedIconSetRegistry;
use Illuminate\Http\UploadedFile;

function & iconUploaderZipPaths(): array
{
	static $paths = [];

	return $paths;
}

function makeIconZip( array $entries ): string
{
	$path    = sys_get_temp_dir() . '/icon-zip-' . bin2hex( random_bytes( 4 ) ) . '.zip';
	$archive = new ZipArchive();
	$status  = $archive->open( $path, ZipArchive::CREATE );
	if ( true !== $status ) {
		throw new RuntimeException( "Failed to create test zip {$path}: status {$status}" );
	}
	foreach ( $entries as $name => $contents ) {
		$archive->addFromString( $name, $contents );
	}
	$archive->close();

	$paths   = & iconUploaderZipPaths();
	$paths[] = $path;

	return $path;
}

function makeUploadedZip( array $entries ): UploadedFile
{
	$path = makeIconZip( $entries );

	return new UploadedFile( $path, basename( $path ), 'application/zip', null, true );
}

beforeEach( function (): void {
	$base = sys_get_temp_dir() . '/icon-uploader-' . bin2hex( random_bytes( 4 ) );
	mkdir( $base, 0o755, true );

	test()->baseDir  = $base;
	test()->registry = new UploadedIconSetRegistry( $base );
	test()->uploader = new IconSetUploader( test()->registry, new SvgSanitizer() );

	$paths = & iconUploaderZipPaths();
	$paths = [];
} );

afterEach( function (): void {
	$rrm = static function ( string $dir ) use ( &$rrm ): void {
		foreach ( scandir( $dir ) ?: [] as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $entry;
			is_dir( $path ) ? $rrm( $path ) : @unlink( $path );
		}
		@rmdir( $dir );
	};

	if ( isset( test()->baseDir ) && is_dir( test()->baseDir ) ) {
		$rrm( test()->baseDir );
	}

	foreach ( iconUploaderZipPaths() as $zip ) {
		if ( is_file( $zip ) ) {
			@unlink( $zip );
		}
	}
} );

it( 'stores sanitized svgs under the per-set directory', function (): void {
	$zip = makeUploadedZip( [
		'home.svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0"/></svg>',
		'user.svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 1"/></svg>',
	] );

	$result = test()->uploader->upload( $zip, 'fa-pro', 'Font Awesome Pro' );

	expect( $result->stored )->toEqualCanonicalizing( [ 'home.svg', 'user.svg' ] )
		->and( $result->failed )->toBe( [] )
		->and( $result->skipped )->toBe( [] )
		->and( test()->registry->has( 'fa-pro' ) )->toBeTrue()
		->and( file_exists( test()->baseDir . '/fa-pro/home.svg' ) )->toBeTrue()
		->and( file_exists( test()->baseDir . '/fa-pro/user.svg' ) )->toBeTrue();
} );

it( 'skips non-svg entries and never writes them to disk', function (): void {
	$zip = makeUploadedZip( [
		'home.svg'    => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>',
		'readme.txt'  => 'just notes',
		'logo.png'    => 'fake png bytes',
	] );

	$result = test()->uploader->upload( $zip, 'brand', 'Brand' );

	expect( $result->stored )->toBe( [ 'home.svg' ] )
		->and( $result->skipped )->toEqualCanonicalizing( [ 'readme.txt', 'logo.png' ] )
		->and( file_exists( test()->baseDir . '/brand/readme.txt' ) )->toBeFalse()
		->and( file_exists( test()->baseDir . '/brand/logo.png' ) )->toBeFalse();
} );

it( 'reports sanitization failures without writing the empty result', function (): void {
	$zip = makeUploadedZip( [
		'evil.svg' => '<script>alert(1)</script>',
		'good.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>',
	] );

	$result = test()->uploader->upload( $zip, 'brand', 'Brand' );

	expect( $result->stored )->toBe( [ 'good.svg' ] )
		->and( $result->failed )->toHaveCount( 1 )
		->and( $result->failed[0]['file'] )->toBe( 'evil.svg' )
		->and( $result->failed[0]['warnings'] )->not->toBe( [] )
		->and( file_exists( test()->baseDir . '/brand/evil.svg' ) )->toBeFalse();
} );

it( 'rejects path traversal in the zip entry names', function (): void {
	$zip = makeUploadedZip( [
		'../escape.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>',
		'safe.svg'      => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>',
	] );

	$result = test()->uploader->upload( $zip, 'brand', 'Brand' );

	// `../escape.svg` reduces to `escape.svg` via basename(), which is
	// safe — the rule is that the joined target must never escape the
	// set directory, and basename() guarantees that. Verify both that
	// the sanitized name lands inside the per-set directory AND that
	// nothing was written one level up where the unsanitized name
	// would have escaped to.
	expect( $result->stored )->toContain( 'safe.svg' )
		->and( $result->stored )->toContain( 'escape.svg' )
		->and( file_exists( test()->baseDir . '/brand/escape.svg' ) )->toBeTrue()
		->and( file_exists( test()->baseDir . '/escape.svg' ) )->toBeFalse();
} );

it( 'raises a typed collision when the prefix matches a bundled FA set', function (): void {
	$zip = makeUploadedZip( [
		'home.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>',
	] );

	expect( fn (): mixed => test()->uploader->upload( $zip, 'fas', 'FA Solid' ) )
		->toThrow( PrefixCollisionException::class );
} );

it( 'raises a typed collision when the prefix is already taken by an uploaded set', function (): void {
	test()->registry->register( new UploadedIconSet( 'brand', 'Brand', '2026-01-01T00:00:00Z' ) );

	$zip = makeUploadedZip( [
		'home.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>',
	] );

	expect( fn (): mixed => test()->uploader->upload( $zip, 'brand', 'Brand' ) )
		->toThrow( PrefixCollisionException::class );
} );

it( 'rejects an upload that contains no usable svgs', function (): void {
	$zip = makeUploadedZip( [
		'evil.svg' => '<script>alert(1)</script>',
	] );

	expect( fn (): mixed => test()->uploader->upload( $zip, 'brand', 'Brand' ) )
		->toThrow( RuntimeException::class );
} );

it( 'wipes the on-disk directory on delete', function (): void {
	$zip = makeUploadedZip( [
		'home.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>',
	] );

	test()->uploader->upload( $zip, 'brand', 'Brand' );
	expect( is_dir( test()->baseDir . '/brand' ) )->toBeTrue();

	test()->uploader->delete( 'brand' );

	expect( test()->registry->has( 'brand' ) )->toBeFalse()
		->and( is_dir( test()->baseDir . '/brand' ) )->toBeFalse();
} );
