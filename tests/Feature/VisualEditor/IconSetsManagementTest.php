<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Icon\UploadedIconSet;
use ArtisanPackUI\VisualEditor\Services\Icon\UploadedIconSetRegistry;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestUser;

function bindAllowingGate(): void
{
	app()->bind( SiteEditorAccessGate::class, function () {
		return new class implements SiteEditorAccessGate
		{
			public function check( Request $request ): ?Response
			{
				return null;
			}
		};
	} );
}

function bindDenyingGate(): void
{
	app()->bind( SiteEditorAccessGate::class, function () {
		return new class implements SiteEditorAccessGate
		{
			public function check( Request $request ): ?Response
			{
				return response( 'denied', Response::HTTP_FORBIDDEN );
			}
		};
	} );
}

function & managementZipPaths(): array
{
	static $paths = [];

	return $paths;
}

function makeManagementTestZip( array $entries ): UploadedFile
{
	$path    = sys_get_temp_dir() . '/icon-mgmt-' . bin2hex( random_bytes( 4 ) ) . '.zip';
	$archive = new ZipArchive();
	$status  = $archive->open( $path, ZipArchive::CREATE );
	if ( true !== $status ) {
		throw new RuntimeException( "Failed to create test zip {$path}: status {$status}" );
	}
	foreach ( $entries as $name => $contents ) {
		$archive->addFromString( $name, $contents );
	}
	$archive->close();

	$paths   = & managementZipPaths();
	$paths[] = $path;

	return new UploadedFile( $path, basename( $path ), 'application/zip', null, true );
}

function actingAsIconSetsAdmin(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Icon Sets Admin',
		'email'    => 'icon-mgmt+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

function rebindRegistryToTempDir(): string
{
	$base = sys_get_temp_dir() . '/icon-mgmt-base-' . bin2hex( random_bytes( 4 ) );
	mkdir( $base, 0o755, true );

	app()->instance( UploadedIconSetRegistry::class, new UploadedIconSetRegistry( $base ) );

	return $base;
}

beforeEach( function (): void {
	test()->iconBase = rebindRegistryToTempDir();
	$paths           = & managementZipPaths();
	$paths           = [];
	// Force the uploader to pick up the rebound registry.
	app()->forgetInstance( \ArtisanPackUI\VisualEditor\Services\Icon\IconSetUploader::class );
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

	if ( isset( test()->iconBase ) && is_dir( test()->iconBase ) ) {
		$rrm( test()->iconBase );
	}

	foreach ( managementZipPaths() as $zip ) {
		if ( is_file( $zip ) ) {
			@unlink( $zip );
		}
	}
} );

it( 'uploads and persists a new icon set', function (): void {
	actingAsIconSetsAdmin();
	bindAllowingGate();

	$zip = makeManagementTestZip( [
		'home.svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0"/></svg>',
		'user.svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 1"/></svg>',
	] );

	$response = $this->post( '/visual-editor/api/admin/icon-sets', [
		'prefix' => 'fa-pro',
		'label'  => 'Font Awesome Pro',
		'zip'    => $zip,
	] );

	$response->assertStatus( Response::HTTP_CREATED )
		->assertJsonPath( 'data.prefix', 'fa-pro' )
		->assertJsonPath( 'data.label', 'Font Awesome Pro' )
		->assertJsonCount( 2, 'report.stored' );

	expect( app( UploadedIconSetRegistry::class )->has( 'fa-pro' ) )->toBeTrue()
		->and( file_exists( test()->iconBase . '/fa-pro/home.svg' ) )->toBeTrue();
} );

it( 'returns 409 with the offending prefix on collision', function (): void {
	actingAsIconSetsAdmin();
	bindAllowingGate();

	app( UploadedIconSetRegistry::class )->register(
		new UploadedIconSet( 'brand', 'Brand', '2026-01-01T00:00:00Z' ),
	);

	$zip = makeManagementTestZip( [
		'home.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>',
	] );

	$response = $this->post( '/visual-editor/api/admin/icon-sets', [
		'prefix' => 'brand',
		'label'  => 'Brand',
		'zip'    => $zip,
	] );

	$response->assertStatus( Response::HTTP_CONFLICT )
		->assertJsonPath( 'error', 'prefix_collision' )
		->assertJsonPath( 'prefix', 'brand' );
} );

it( 'reports skipped non-svg entries and failed sanitization in the upload report', function (): void {
	actingAsIconSetsAdmin();
	bindAllowingGate();

	$zip = makeManagementTestZip( [
		'home.svg'   => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>',
		'readme.txt' => 'not an icon',
		'evil.svg'   => '<script>alert(1)</script>',
	] );

	$response = $this->post( '/visual-editor/api/admin/icon-sets', [
		'prefix' => 'brand',
		'label'  => 'Brand',
		'zip'    => $zip,
	] );

	$response->assertStatus( Response::HTTP_CREATED )
		->assertJsonPath( 'report.stored.0', 'home.svg' )
		->assertJsonPath( 'report.skipped.0', 'readme.txt' )
		->assertJsonPath( 'report.failed.0.file', 'evil.svg' );
} );

it( 'lists registered sets', function (): void {
	actingAsIconSetsAdmin();
	bindAllowingGate();

	app( UploadedIconSetRegistry::class )->register(
		new UploadedIconSet( 'brand', 'Brand', '2026-01-01T00:00:00Z' ),
	);

	$this->getJson( '/visual-editor/api/admin/icon-sets' )
		->assertOk()
		->assertJsonPath( 'data.0.prefix', 'brand' )
		->assertJsonPath( 'data.0.label', 'Brand' );
} );

it( 'renames a registered set', function (): void {
	actingAsIconSetsAdmin();
	bindAllowingGate();

	app( UploadedIconSetRegistry::class )->register(
		new UploadedIconSet( 'brand', 'Old', '2026-01-01T00:00:00Z' ),
	);

	$this->patchJson( '/visual-editor/api/admin/icon-sets/brand', [ 'label' => 'New' ] )
		->assertOk()
		->assertJsonPath( 'data.label', 'New' );
} );

it( 'deletes a registered set and clears the registry', function (): void {
	actingAsIconSetsAdmin();
	bindAllowingGate();

	app( UploadedIconSetRegistry::class )->register(
		new UploadedIconSet( 'brand', 'Brand', '2026-01-01T00:00:00Z' ),
	);
	mkdir( test()->iconBase . '/brand', 0o755, true );
	file_put_contents( test()->iconBase . '/brand/home.svg', '<svg/>' );

	$this->delete( '/visual-editor/api/admin/icon-sets/brand' )
		->assertNoContent();

	expect( app( UploadedIconSetRegistry::class )->has( 'brand' ) )->toBeFalse()
		->and( is_dir( test()->iconBase . '/brand' ) )->toBeFalse();
} );

it( 'returns the gate response when the management policy denies access', function (): void {
	actingAsIconSetsAdmin();
	bindDenyingGate();

	$this->get( '/visual-editor/api/admin/icon-sets' )
		->assertStatus( Response::HTTP_FORBIDDEN );
} );

it( 'serves the settings page through the same gate', function (): void {
	actingAsIconSetsAdmin();
	bindAllowingGate();

	$this->withoutVite();
	$this->get( '/visual-editor/admin/icon-sets' )
		->assertOk()
		->assertSee( 'Icon Sets' );
} );

it( 'hides the settings page from users denied by the gate', function (): void {
	actingAsIconSetsAdmin();
	bindDenyingGate();

	$this->get( '/visual-editor/admin/icon-sets' )
		->assertStatus( Response::HTTP_FORBIDDEN );
} );
