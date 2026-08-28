<?php

/**
 * Font Library REST controller tests (#634).
 *
 * Covers the public JSON surface the Font Library modal drives: the open read
 * endpoints (installed list, provider list, provider catalog) and their
 * read-only signal, and the `manage_fonts`-gated mutations (install, upload,
 * bulk/single uninstall) — happy path, validation errors, and the missing-
 * capability 403.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Contracts\FontProvider;
use ArtisanPackUI\VisualEditor\Fonts\Models\Font;
use ArtisanPackUI\VisualEditor\Fonts\Models\FontFace;
use ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\Fonts\FontBinaryFactory;
use Tests\Support\FontCapabilityUser;
use Tests\Support\FontPermissionUser;
use Tests\TestUser;

beforeEach( function (): void {
	config()->set( 'artisanpack.visual-editor.api.middleware', [ 'auth' ] );
	config()->set( 'artisanpack.visual-editor.fonts.disk', 'fonts-test' );
	config()->set( 'artisanpack.visual-editor.fonts.capability', 'manage_fonts' );

	Storage::fake( 'fonts-test' );
} );

/**
 * Register an in-memory, self-hostable provider so install/catalog tests never
 * touch the network. Its single `roboto` family fetches a stub WOFF2 face.
 */
function registerFakeFontProvider(): void
{
	app( FontSourceRegistry::class )->register( new class implements FontProvider
	{
		public function key(): string
		{
			return 'fake';
		}

		public function label(): string
		{
			return 'Fake Fonts';
		}

		public function isSelfHostable(): bool
		{
			return true;
		}

		public function searchCatalog( string $query, int $page = 1 ): array
		{
			return [
				'families' => [ [ 'slug' => 'roboto', 'family' => 'Roboto' ] ],
				'page'     => $page,
				'has_more' => false,
			];
		}

		public function getFamily( string $slug ): ?array
		{
			if ( 'roboto' !== $slug ) {
				return null;
			}

			return [
				'slug'        => 'roboto',
				'family'      => 'Roboto',
				'is_variable' => false,
				'faces'       => [ [ 'weight' => 400, 'style' => 'normal' ] ],
			];
		}

		public function fetchFace( string $slug, string $weight, string $style ): string
		{
			return 'wOF2' . str_repeat( "\x00", 200 );
		}
	} );
}

function actingAsFontManager(): FontCapabilityUser
{
	$user                       = new FontCapabilityUser();
	$user->name                 = 'Font Manager';
	$user->email                = 'fonts+' . uniqid() . '@example.com';
	$user->password             = bcrypt( 'secret' );
	$user->grantedCapabilities  = [ 'manage_fonts' ];
	$user->save();

	test()->actingAs( $user );

	return $user;
}

function actingAsRbacFontManager(): FontPermissionUser
{
	$user                     = new FontPermissionUser();
	$user->name               = 'RBAC Font Manager';
	$user->email              = 'rbac-fonts+' . uniqid() . '@example.com';
	$user->password           = bcrypt( 'secret' );
	$user->grantedPermissions = [ 'manage_fonts' ];
	$user->save();

	test()->actingAs( $user );

	return $user;
}

function actingAsFontBrowser(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Font Browser',
		'email'    => 'browser+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

function seedInstalledFont( string $provider = 'fake', string $slug = 'installed' ): Font
{
	$font = Font::query()->create( [
		'provider'     => $provider,
		'family'       => ucfirst( $slug ),
		'slug'         => $slug,
		'is_variable'  => false,
		'installed_at' => now(),
	] );

	$font->faces()->create( [
		'weight'    => 400,
		'style'     => 'normal',
		'format'    => 'woff2',
		'disk'      => 'fonts-test',
		'path'      => "visual-editor/fonts/{$provider}/{$slug}/400-normal.woff2",
		'file_size' => 1024,
	] );

	Storage::disk( 'fonts-test' )->put( "visual-editor/fonts/{$provider}/{$slug}/400-normal.woff2", 'bytes' );

	return $font;
}

describe( 'GET /visual-editor/api/fonts', function (): void {
	it( 'lists installed fonts with their faces', function (): void {
		actingAsFontBrowser();
		seedInstalledFont( slug: 'roboto' );

		$this->getJson( '/visual-editor/api/fonts' )
			->assertOk()
			->assertJsonPath( 'data.0.slug', 'roboto' )
			->assertJsonPath( 'data.0.faces.0.weight', 400 )
			->assertJsonPath( 'data.0.faces.0.style', 'normal' );
	} );

	it( 'flags the session read-only for a user without the capability', function (): void {
		actingAsFontBrowser();

		$this->getJson( '/visual-editor/api/fonts' )
			->assertOk()
			->assertJsonPath( 'can_manage', false )
			->assertJsonPath( 'read_only', true );
	} );

	it( 'flags the session writable for a user with the capability', function (): void {
		actingAsFontManager();

		$this->getJson( '/visual-editor/api/fonts' )
			->assertOk()
			->assertJsonPath( 'can_manage', true )
			->assertJsonPath( 'read_only', false );
	} );

	it( 'flags the session writable for a cms-framework rbac user granted the permission', function (): void {
		actingAsRbacFontManager();

		$this->getJson( '/visual-editor/api/fonts' )
			->assertOk()
			->assertJsonPath( 'can_manage', true )
			->assertJsonPath( 'read_only', false );
	} );
} );

describe( 'GET /visual-editor/api/fonts/sources', function (): void {
	it( 'lists the registered providers', function (): void {
		actingAsFontBrowser();
		registerFakeFontProvider();

		$response = $this->getJson( '/visual-editor/api/fonts/sources' )->assertOk();

		$keys = array_column( $response->json( 'data' ), 'key' );

		expect( $keys )->toContain( 'fake' );
	} );
} );

describe( 'GET /visual-editor/api/fonts/sources/{provider}/catalog', function (): void {
	it( 'returns a provider catalog page', function (): void {
		actingAsFontBrowser();
		registerFakeFontProvider();

		$this->getJson( '/visual-editor/api/fonts/sources/fake/catalog?q=rob' )
			->assertOk()
			->assertJsonPath( 'data.families.0.slug', 'roboto' )
			->assertJsonPath( 'data.has_more', false );
	} );

	it( 'decorates each family with a same-origin preview_url', function (): void {
		actingAsFontBrowser();
		registerFakeFontProvider();

		$this->getJson( '/visual-editor/api/fonts/sources/fake/catalog?q=rob' )
			->assertOk()
			->assertJsonPath(
				'data.families.0.preview_url',
				'/visual-editor/api/fonts/sources/fake/preview/roboto'
			);
	} );

	it( 'returns 404 for an unregistered provider', function (): void {
		actingAsFontBrowser();

		$this->getJson( '/visual-editor/api/fonts/sources/nope/catalog' )
			->assertNotFound()
			->assertJsonPath( 'error', 'unknown_provider' );
	} );
} );

describe( 'GET /visual-editor/api/fonts/sources/{provider}/preview/{slug}', function (): void {
	it( 'serves a same-origin @font-face stylesheet for a representative face', function (): void {
		actingAsFontBrowser();
		registerFakeFontProvider();

		$response = $this->get( '/visual-editor/api/fonts/sources/fake/preview/roboto' )
			->assertOk();

		expect( $response->headers->get( 'content-type' ) )->toContain( 'text/css' );

		$css = $response->getContent();

		expect( $css )->toContain( '@font-face' );
		expect( $css )->toContain( 'font-family: "Roboto"' );
		expect( $css )->toContain(
			'src: url("/visual-editor/api/fonts/sources/fake/preview/roboto/400/normal") format("woff2")'
		);
	} );

	it( 'returns 404 for an unknown family slug', function (): void {
		actingAsFontBrowser();
		registerFakeFontProvider();

		$this->get( '/visual-editor/api/fonts/sources/fake/preview/nosuchfont' )
			->assertNotFound();
	} );

	it( 'returns 404 for an unregistered provider', function (): void {
		actingAsFontBrowser();

		$this->get( '/visual-editor/api/fonts/sources/nope/preview/roboto' )
			->assertNotFound();
	} );
} );

describe( 'GET /visual-editor/api/fonts/sources/{provider}/preview/{slug}/{weight}/{style}', function (): void {
	it( 'streams the face bytes as woff2', function (): void {
		actingAsFontBrowser();
		registerFakeFontProvider();

		$response = $this->get( '/visual-editor/api/fonts/sources/fake/preview/roboto/400/normal' )
			->assertOk();

		expect( $response->headers->get( 'content-type' ) )->toBe( 'font/woff2' );
		expect( $response->getContent() )->toStartWith( 'wOF2' );
	} );

	it( 'returns 404 for an unregistered provider', function (): void {
		actingAsFontBrowser();

		$this->get( '/visual-editor/api/fonts/sources/nope/preview/roboto/400/normal' )
			->assertNotFound();
	} );

	it( 'caches a normal-sized preview face body', function (): void {
		actingAsFontBrowser();
		registerFakeFontProvider();

		$this->get( '/visual-editor/api/fonts/sources/fake/preview/roboto/400/normal' )->assertOk();

		expect( Cache::has( 've.font-preview.fake.roboto.400.normal' ) )->toBeTrue();
	} );

	it( 'refuses to cache a preview face body over the ceiling', function (): void {
		actingAsFontBrowser();

		// A provider whose face body exceeds the 2 MB cache ceiling: the response
		// is still served, but nothing is written to the shared cache store.
		app( FontSourceRegistry::class )->register( new class implements FontProvider
		{
			public function key(): string
			{
				return 'big';
			}

			public function label(): string
			{
				return 'Big Fonts';
			}

			public function isSelfHostable(): bool
			{
				return true;
			}

			public function searchCatalog( string $query, int $page = 1 ): array
			{
				return [ 'families' => [], 'page' => $page, 'has_more' => false ];
			}

			public function getFamily( string $slug ): ?array
			{
				return null;
			}

			public function fetchFace( string $slug, string $weight, string $style ): string
			{
				return 'wOF2' . str_repeat( "\x00", 2 * 1024 * 1024 );
			}
		} );

		$this->get( '/visual-editor/api/fonts/sources/big/preview/huge/400/normal' )->assertOk();

		expect( Cache::has( 've.font-preview.big.huge.400.normal' ) )->toBeFalse();
	} );
} );

describe( 'POST /visual-editor/api/fonts', function (): void {
	it( 'installs a catalog font for a capable user', function (): void {
		actingAsFontManager();
		registerFakeFontProvider();

		$this->postJson( '/visual-editor/api/fonts', [
			'provider' => 'fake',
			'slug'     => 'roboto',
			'faces'    => [ [ 'weight' => 400, 'style' => 'normal' ] ],
		] )
			->assertCreated()
			->assertJsonPath( 'data.slug', 'roboto' )
			->assertJsonPath( 'data.faces.0.weight', 400 );

		expect( Font::query()->where( 'slug', 'roboto' )->exists() )->toBeTrue();
		Storage::disk( 'fonts-test' )->assertExists( 'visual-editor/fonts/fake/roboto/400-normal.woff2' );
	} );

	it( 'forbids install without the capability', function (): void {
		actingAsFontBrowser();
		registerFakeFontProvider();

		$this->postJson( '/visual-editor/api/fonts', [
			'provider' => 'fake',
			'slug'     => 'roboto',
			'faces'    => [ [ 'weight' => 400, 'style' => 'normal' ] ],
		] )
			->assertForbidden()
			->assertJsonPath( 'error', 'forbidden' )
			->assertJsonPath( 'read_only', true );

		expect( Font::query()->count() )->toBe( 0 );
	} );

	it( 'validates the install payload', function (): void {
		actingAsFontManager();
		registerFakeFontProvider();

		$this->postJson( '/visual-editor/api/fonts', [
			'provider' => 'fake',
			'slug'     => 'roboto',
			'faces'    => [],
		] )
			->assertStatus( Response::HTTP_UNPROCESSABLE_ENTITY )
			->assertJsonValidationErrors( [ 'faces' ] );
	} );

	it( 'rejects an unauthorized install before validating the payload', function (): void {
		actingAsFontBrowser();
		registerFakeFontProvider();

		// An invalid payload from a user without the capability must return the
		// shaped 403 (authorization runs before validation), not a 422.
		$this->postJson( '/visual-editor/api/fonts', [
			'provider' => 'fake',
			'slug'     => 'roboto',
			'faces'    => [],
		] )
			->assertForbidden()
			->assertJsonPath( 'error', 'forbidden' )
			->assertJsonPath( 'read_only', true );
	} );

	it( 'returns 404 for an unregistered provider', function (): void {
		actingAsFontManager();

		$this->postJson( '/visual-editor/api/fonts', [
			'provider' => 'nope',
			'slug'     => 'roboto',
			'faces'    => [ [ 'weight' => 400 ] ],
		] )
			->assertNotFound()
			->assertJsonPath( 'error', 'unknown_provider' );
	} );
} );

describe( 'POST /visual-editor/api/fonts/upload', function (): void {
	it( 'installs an uploaded custom font for a capable user', function (): void {
		actingAsFontManager();

		$path = sys_get_temp_dir() . '/font-upload-' . bin2hex( random_bytes( 4 ) ) . '.ttf';
		file_put_contents( $path, FontBinaryFactory::variableTtf() );
		$file = new UploadedFile( $path, 'brand.ttf', 'font/ttf', null, true );

		$this->post( '/visual-editor/api/fonts/upload', [
			'family' => 'Brand Sans',
			'faces'  => [ [ 'weight' => 400, 'style' => 'normal', 'file' => $file ] ],
		] )
			->assertCreated()
			->assertJsonPath( 'data.family', 'Brand Sans' )
			->assertJsonPath( 'data.provider', 'custom' );

		expect( Font::query()->where( 'provider', 'custom' )->where( 'slug', 'brand-sans' )->exists() )->toBeTrue();

		@unlink( $path );
	} );

	it( 'forbids upload without the capability', function (): void {
		actingAsFontBrowser();

		$path = sys_get_temp_dir() . '/font-upload-' . bin2hex( random_bytes( 4 ) ) . '.ttf';
		file_put_contents( $path, FontBinaryFactory::variableTtf() );
		$file = new UploadedFile( $path, 'brand.ttf', 'font/ttf', null, true );

		$this->post( '/visual-editor/api/fonts/upload', [
			'family' => 'Brand Sans',
			'faces'  => [ [ 'weight' => 400, 'style' => 'normal', 'file' => $file ] ],
		] )->assertForbidden();

		expect( Font::query()->count() )->toBe( 0 );

		@unlink( $path );
	} );

	it( 'rejects an unauthorized upload before validating the multipart body', function (): void {
		actingAsFontBrowser();

		// A user without the capability sending an empty (invalid) body must get
		// the shaped 403 before the file rules run, so a hostile large multipart
		// upload is never materialised and validated for an unauthorized caller.
		$this->postJson( '/visual-editor/api/fonts/upload', [
			'family' => '',
			'faces'  => [],
		] )
			->assertForbidden()
			->assertJsonPath( 'error', 'forbidden' )
			->assertJsonPath( 'read_only', true );

		expect( Font::query()->count() )->toBe( 0 );
	} );
} );

describe( 'POST /visual-editor/api/fonts/bulk-uninstall', function (): void {
	it( 'uninstalls several fonts at once', function (): void {
		actingAsFontManager();
		$one = seedInstalledFont( slug: 'one' );
		$two = seedInstalledFont( slug: 'two' );

		$this->postJson( '/visual-editor/api/fonts/bulk-uninstall', [
			'ids' => [ $one->id, $two->id ],
		] )
			->assertOk()
			->assertJsonPath( 'data.removed', 2 );

		expect( Font::query()->count() )->toBe( 0 );
	} );

	it( 'forbids bulk uninstall without the capability', function (): void {
		actingAsFontBrowser();
		$font = seedInstalledFont();

		$this->postJson( '/visual-editor/api/fonts/bulk-uninstall', [
			'ids' => [ $font->id ],
		] )->assertForbidden();

		expect( Font::query()->count() )->toBe( 1 );
	} );

	it( 'validates that ids are supplied', function (): void {
		actingAsFontManager();

		$this->postJson( '/visual-editor/api/fonts/bulk-uninstall', [ 'ids' => [] ] )
			->assertStatus( Response::HTTP_UNPROCESSABLE_ENTITY )
			->assertJsonValidationErrors( [ 'ids' ] );
	} );
} );

describe( 'DELETE /visual-editor/api/fonts/{font}', function (): void {
	it( 'uninstalls a single font for a capable user', function (): void {
		actingAsFontManager();
		$font = seedInstalledFont();

		$this->deleteJson( "/visual-editor/api/fonts/{$font->id}" )
			->assertNoContent();

		expect( Font::query()->whereKey( $font->id )->exists() )->toBeFalse();
		expect( FontFace::query()->where( 'font_id', $font->id )->exists() )->toBeFalse();
	} );

	it( 'forbids uninstall without the capability', function (): void {
		actingAsFontBrowser();
		$font = seedInstalledFont();

		$this->deleteJson( "/visual-editor/api/fonts/{$font->id}" )
			->assertForbidden();

		expect( Font::query()->whereKey( $font->id )->exists() )->toBeTrue();
	} );
} );
