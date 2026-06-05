<?php

/**
 * H6 GlobalStylesController integration tests — runs the visual-editor
 * REST surface against a booted cms-framework.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\GlobalStyles;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class, WithCmsFramework::class );

beforeEach( function (): void {
	$user = TestUser::create( [
		'name'     => 'Global styles tester',
		'email'    => 'global-styles+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );

	$this->mock( ThemeManager::class, function ( $mock ): void {
		$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
			'name' => 'Digital Shopfront',
			'slug' => 'digital-shopfront',
		] );
	} );
} );

function rebuildSiteEditorResolversForGlobalStylesTest(): void
{
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
}

describe( 'GET /visual-editor/api/global-styles/lookup', function (): void {
	it( 'returns __base__ when no DB row exists yet', function (): void {
		rebuildSiteEditorResolversForGlobalStylesTest();

		$this->getJson( '/visual-editor/api/global-styles/lookup' )
			->assertOk()
			->assertJsonPath( 'id', '__base__' );
	} );

	it( 'returns the numeric DB id once the user has customized', function (): void {
		$record = GlobalStyles::create( [
			'theme'    => 'digital-shopfront',
			'title'    => 'Custom',
			'settings' => [ 'color' => [] ],
			'styles'   => [ 'typography' => [ 'fontSize' => '18px' ] ],
		] );

		rebuildSiteEditorResolversForGlobalStylesTest();

		$this->getJson( '/visual-editor/api/global-styles/lookup' )
			->assertOk()
			->assertJsonPath( 'id', $record->id );
	} );
} );

describe( 'GET /visual-editor/api/global-styles/base', function (): void {
	it( 'returns pristine theme defaults from theme.json regardless of DB overrides', function (): void {
		// Override the beforeEach mock with a theme manifest that
		// carries explicit settings + styles, so the assertion targets
		// the theme-default values surfaced by the endpoint.
		$this->mock( ThemeManager::class, function ( $mock ): void {
			$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
				'name'     => 'Digital Shopfront',
				'slug'     => 'digital-shopfront',
				'settings' => [ 'color' => [ 'palette' => [ [ 'slug' => 'primary', 'color' => '#000' ] ] ] ],
				'styles'   => [ 'typography' => [ 'fontSize' => '14px' ] ],
			] );
		} );

		// A DB override exists with different styles — base() must NOT
		// surface it. The pristine theme baseline is what the inspector
		// compares user customization against.
		GlobalStyles::create( [
			'theme'    => 'digital-shopfront',
			'title'    => 'Custom',
			'settings' => [ 'color' => [ 'palette' => [] ] ],
			'styles'   => [ 'typography' => [ 'fontSize' => '20px' ] ],
		] );

		$this->getJson( '/visual-editor/api/global-styles/base' )
			->assertOk()
			->assertJsonPath( 'id', '__base__' )
			->assertJsonPath( 'theme', 'digital-shopfront' )
			->assertJsonPath( 'styles.typography.fontSize', '14px' )
			->assertJsonPath( 'settings.color.palette.0.slug', 'primary' );
	} );

	it( 'returns empty defaults when no active theme is configured', function (): void {
		$this->mock( ThemeManager::class, function ( $mock ): void {
			$mock->shouldReceive( 'getActiveTheme' )->andReturn( null );
		} );

		$this->getJson( '/visual-editor/api/global-styles/base' )
			->assertOk()
			->assertJsonPath( 'id', '__base__' )
			->assertJsonPath( 'theme', '' );
	} );
} );

describe( 'GET /visual-editor/api/global-styles/css', function (): void {
	it( 'returns compiled CSS from cms-framework\'s emitter as text/css', function (): void {
		$this->mock( ThemeManager::class, function ( $mock ): void {
			$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
				'name'     => 'Digital Shopfront',
				'slug'     => 'digital-shopfront',
				'settings' => [ 'color' => [ 'palette' => [ [ 'slug' => 'primary', 'color' => '#0f172a' ] ] ] ],
				'styles'   => [ 'color' => [ 'text' => '#111827' ] ],
			] );
		} );

		rebuildSiteEditorResolversForGlobalStylesTest();

		$response = $this->get( '/visual-editor/api/global-styles/css' )->assertOk();

		expect( $response->headers->get( 'content-type' ) )->toContain( 'text/css' );
		// The emitter compiles palette presets to `--wp--preset--color--{slug}`
		// custom properties on `:root` and `styles.color.text` to a `color`
		// declaration on the root. Asserting on both proves the resolver +
		// emitter pipeline is wired end-to-end.
		expect( $response->getContent() )
			->toContain( '--wp--preset--color--primary: #0f172a;' )
			->toContain( 'color: #111827;' );
	} );

	it( 'returns an empty body when no active theme is configured', function (): void {
		$this->mock( ThemeManager::class, function ( $mock ): void {
			$mock->shouldReceive( 'getActiveTheme' )->andReturn( null );
		} );

		rebuildSiteEditorResolversForGlobalStylesTest();

		$response = $this->get( '/visual-editor/api/global-styles/css' )->assertOk();

		expect( $response->getContent() )->toBe( '' );
		expect( $response->headers->get( 'content-type' ) )->toContain( 'text/css' );
	} );

	it( 'concatenates the active theme\'s style.css after the emitter output (Keystone #47)', function (): void {
		$themesBase = sys_get_temp_dir() . '/keystone-47-css-' . uniqid();
		$slug       = 'parity-theme';
		$themeDir   = $themesBase . '/' . $slug;

		mkdir( $themeDir, 0755, true );
		file_put_contents( $themeDir . '/style.css', ".wp-element-button{border-radius:0.5rem;padding:0.75rem 1.5rem}\n" );

		config()->set( 'cms.themes.directory', $themesBase );

		$this->mock( ThemeManager::class, function ( $mock ) use ( $slug ): void {
			$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
				'name'     => 'Parity theme',
				'slug'     => $slug,
				'settings' => [ 'color' => [ 'palette' => [ [ 'slug' => 'primary', 'color' => '#0f172a' ] ] ] ],
				'styles'   => [ 'elements' => [ 'button' => [ 'color' => [ 'background' => '#2563eb' ] ] ] ],
			] );
		} );

		rebuildSiteEditorResolversForGlobalStylesTest();

		$response = $this->get( '/visual-editor/api/global-styles/css' )->assertOk();
		$body     = $response->getContent();

		// Emitter output is present (palette token + button bg).
		expect( $body )
			->toContain( '--wp--preset--color--primary: #0f172a;' )
			->toContain( 'background-color: #2563eb;' );

		// The hand-authored stylesheet is appended AFTER the emitter so
		// it wins on cascade — buttons get the radius + padding the
		// emitter doesn't compile today.
		expect( $body )->toContain( 'border-radius:0.5rem' );
		expect( strpos( $body, 'background-color: #2563eb;' ) )->toBeLessThan( strpos( $body, 'border-radius:0.5rem' ) );

		unlink( $themeDir . '/style.css' );
		rmdir( $themeDir );
		rmdir( $themesBase );
	} );

	it( 'rejects a malformed theme slug instead of reading off-disk (path-traversal guard)', function (): void {
		$this->mock( ThemeManager::class, function ( $mock ): void {
			$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
				'name' => 'Bad slug',
				'slug' => '../../etc',
			] );
		} );

		rebuildSiteEditorResolversForGlobalStylesTest();

		$response = $this->get( '/visual-editor/api/global-styles/css' )->assertOk();

		// The stylesheet read short-circuited on the bogus slug —
		// body should not contain the theme-stylesheet marker.
		expect( $response->getContent() )->not->toContain( '/* === theme stylesheet === */' );
	} );
} );

describe( 'GET /visual-editor/api/global-styles/{id}', function (): void {
	it( 'returns the singleton when id matches the resolver state', function (): void {
		$record = GlobalStyles::create( [
			'theme'    => 'digital-shopfront',
			'title'    => 'Custom',
			'settings' => [],
			'styles'   => [],
		] );

		rebuildSiteEditorResolversForGlobalStylesTest();

		$this->getJson( "/visual-editor/api/global-styles/{$record->id}" )
			->assertOk()
			->assertJsonPath( 'id', $record->id );
	} );

	it( 'returns 404 when the id does not match the singleton', function (): void {
		GlobalStyles::create( [
			'theme'    => 'digital-shopfront',
			'title'    => 'Custom',
			'settings' => [],
			'styles'   => [],
		] );

		rebuildSiteEditorResolversForGlobalStylesTest();

		$this->getJson( '/visual-editor/api/global-styles/9999' )->assertNotFound();
	} );
} );

describe( 'PUT /visual-editor/api/global-styles/{id}', function (): void {
	it( 'upserts a new row when id is __base__ and no DB record exists', function (): void {
		$this->putJson( '/visual-editor/api/global-styles/__base__', [
			'theme'    => 'digital-shopfront',
			'settings' => [ 'color' => [ 'palette' => [ [ 'slug' => 'primary', 'color' => '#000' ] ] ] ],
			'styles'   => [ 'typography' => [ 'fontSize' => '16px' ] ],
		] )
			->assertOk()
			->assertJsonPath( 'theme', 'digital-shopfront' )
			->assertJsonPath( 'styles.typography.fontSize', '16px' );

		expect( GlobalStyles::query()->where( 'theme', 'digital-shopfront' )->exists() )->toBeTrue();
	} );

	it( 'updates the existing record when id is numeric', function (): void {
		$record = GlobalStyles::create( [
			'theme'    => 'digital-shopfront',
			'title'    => 'Original',
			'settings' => [],
			'styles'   => [ 'typography' => [ 'fontSize' => '14px' ] ],
		] );

		$this->putJson( "/visual-editor/api/global-styles/{$record->id}", [
			'theme'  => 'digital-shopfront',
			'styles' => [ 'typography' => [ 'fontSize' => '18px' ] ],
		] )
			->assertOk()
			->assertJsonPath( 'styles.typography.fontSize', '18px' );
	} );

	it( 'returns 404 when the numeric id does not exist', function (): void {
		$this->putJson( '/visual-editor/api/global-styles/9999', [
			'theme'  => 'digital-shopfront',
			'styles' => [],
		] )->assertNotFound();
	} );
} );
