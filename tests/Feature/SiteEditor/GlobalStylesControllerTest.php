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
		stubThemeManagerHelpersForGlobalStylesTest( $mock );
	} );
} );

function rebuildSiteEditorResolversForGlobalStylesTest(): void
{
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
}

/**
 * Stub the ThemeManager methods the ThemeStylesheetReader (cms-framework
 * ≥ 2.5) calls on top of `getActiveTheme()`. Each `$this->mock()` in a
 * test replaces the outer beforeEach mock, so tests hitting the /css
 * endpoint need to re-declare these too.
 */
function stubThemeManagerHelpersForGlobalStylesTest( \Mockery\MockInterface $mock ): void
{
	$mock->shouldReceive( 'validateSlug' )->andReturnUsing(
		static fn ( string $slug ): bool => (bool) preg_match( '/^[a-zA-Z0-9_-]+$/', $slug ),
	);
	$mock->shouldReceive( 'getThemesPath' )->andReturnUsing(
		static function (): string {
			$directory = (string) config( 'cms.themes.directory', 'themes' );

			if ( '' === $directory ) {
				$directory = 'themes';
			}

			// Match the real ThemeManager::getThemesPath() behavior:
			// honor absolute paths so tests that point at a temp dir
			// (e.g. sys_get_temp_dir()) resolve correctly.
			if ( '/' === $directory[0] || preg_match( '#^[A-Za-z]:[\\\\/]#', $directory ) ) {
				return $directory;
			}

			return base_path( $directory );
		},
	);
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
			stubThemeManagerHelpersForGlobalStylesTest( $mock );
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
			stubThemeManagerHelpersForGlobalStylesTest( $mock );
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
			stubThemeManagerHelpersForGlobalStylesTest( $mock );
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
			stubThemeManagerHelpersForGlobalStylesTest( $mock );
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
			stubThemeManagerHelpersForGlobalStylesTest( $mock );
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
			stubThemeManagerHelpersForGlobalStylesTest( $mock );
		} );

		rebuildSiteEditorResolversForGlobalStylesTest();

		$response = $this->get( '/visual-editor/api/global-styles/css' )->assertOk();

		// The stylesheet reader short-circuits on the bogus slug —
		// no banners land in the body from either convention.
		expect( $response->getContent() )
			->not->toContain( '/* === style.css === */' )
			->not->toContain( '/* === editor.css === */' )
			->not->toContain( '/* === theme stylesheet === */' );
	} );

	it( 'concatenates the active theme\'s editor.css after style.css (cms-framework #199)', function (): void {
		$themesBase = sys_get_temp_dir() . '/cms199-editor-css-' . uniqid();
		$slug       = 'canvas-only-theme';
		$themeDir   = $themesBase . '/' . $slug;

		mkdir( $themeDir, 0755, true );
		file_put_contents( $themeDir . '/style.css', "body { background: papayawhip; }\n" );
		file_put_contents( $themeDir . '/editor.css', ".editor-only-marker { outline: 3px solid magenta; }\n" );

		config()->set( 'cms.themes.directory', $themesBase );

		$this->mock( ThemeManager::class, function ( $mock ) use ( $slug ): void {
			$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
				'name'     => 'Canvas-only theme',
				'slug'     => $slug,
				'settings' => [ 'color' => [ 'palette' => [ [ 'slug' => 'primary', 'color' => '#0f172a' ] ] ] ],
				'styles'   => [],
			] );
			stubThemeManagerHelpersForGlobalStylesTest( $mock );
		} );

		rebuildSiteEditorResolversForGlobalStylesTest();

		$body = $this->get( '/visual-editor/api/global-styles/css' )->assertOk()->getContent();

		// Emitter, style.css, and editor.css all present in that order —
		// closing the gap flagged in cms-framework #199 where the canvas
		// used to see style.css only.
		$emitterPos = strpos( $body, '--wp--preset--color--primary' );
		$stylePos   = strpos( $body, '/* === style.css === */' );
		$editorPos  = strpos( $body, '/* === editor.css === */' );

		expect( $emitterPos )->not->toBeFalse()
			->and( $stylePos )->not->toBeFalse()
			->and( $editorPos )->not->toBeFalse()
			->and( $emitterPos )->toBeLessThan( $stylePos )
			->and( $stylePos )->toBeLessThan( $editorPos );

		expect( $body )
			->toContain( 'body { background: papayawhip; }' )
			->toContain( '.editor-only-marker' );

		unlink( $themeDir . '/style.css' );
		unlink( $themeDir . '/editor.css' );
		rmdir( $themeDir );
		rmdir( $themesBase );
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
