<?php

/**
 * Site-editor data API access — 1.12.0.
 *
 * Every write to site-wide data (templates, template parts, global
 * styles, patterns, menus, menu items) runs through the bound
 * `SiteEditorAccessGate` via `EnsureSiteEditorAccess`. Reads stay open to
 * any authenticated user because the post editor consumes them too.
 *
 * @since 1.12.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\DenyByDefaultGate;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\GrantsSiteEditorAccess;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class, WithCmsFramework::class, GrantsSiteEditorAccess::class );

beforeEach( function (): void {
	$user = TestUser::create( [
		'name'     => 'Plain user',
		'email'    => 'plain+' . uniqid() . '@example.com',
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

dataset( 'site editor write routes', [
	'templates store'        => [ 'POST', '/visual-editor/api/templates' ],
	'templates update'       => [ 'PUT', '/visual-editor/api/templates/single' ],
	'templates destroy'      => [ 'DELETE', '/visual-editor/api/templates/single' ],
	'template-parts store'   => [ 'POST', '/visual-editor/api/template-parts' ],
	'template-parts update'  => [ 'PUT', '/visual-editor/api/template-parts/header' ],
	'template-parts destroy' => [ 'DELETE', '/visual-editor/api/template-parts/header' ],
	'global-styles update'   => [ 'PUT', '/visual-editor/api/global-styles/1' ],
	'patterns store'         => [ 'POST', '/visual-editor/api/patterns' ],
	'patterns update'        => [ 'PUT', '/visual-editor/api/patterns/user/cta' ],
	'patterns destroy'       => [ 'DELETE', '/visual-editor/api/patterns/user/cta' ],
	'menus store'            => [ 'POST', '/visual-editor/api/menus' ],
	'menus update'           => [ 'PUT', '/visual-editor/api/menus/1' ],
	'menus destroy'          => [ 'DELETE', '/visual-editor/api/menus/1' ],
	'menu-items store'       => [ 'POST', '/visual-editor/api/menu-items' ],
	'menu-items update'      => [ 'PUT', '/visual-editor/api/menu-items/1' ],
	'menu-items destroy'     => [ 'DELETE', '/visual-editor/api/menu-items/1' ],
] );

it( 'returns a JSON 403 when the gate denies a site-editor write', function ( string $method, string $uri ): void {
	$this->denySiteEditorAccess();

	$this->json( $method, $uri, [ 'title' => 'Nope', 'slug' => 'nope' ] )
		->assertForbidden()
		->assertJsonStructure( [ 'message' ] );
} )->with( 'site editor write routes' );

it( 'returns a JSON 403 under the package default deny-by-default gate', function ( string $method, string $uri ): void {
	$this->app->bind( SiteEditorAccessGate::class, DenyByDefaultGate::class );

	$this->json( $method, $uri, [ 'title' => 'Nope', 'slug' => 'nope' ] )
		->assertForbidden()
		->assertJsonStructure( [ 'message' ] );
} )->with( 'site editor write routes' );

it( 'does not create a menu when a plain user is denied', function (): void {
	$this->denySiteEditorAccess();

	$this->postJson( '/visual-editor/api/menus', [ 'title' => 'Primary' ] )->assertForbidden();

	expect( Menu::query()->count() )->toBe( 0 );
} );

it( 'passes a JSON denial from the gate through verbatim', function (): void {
	$this->app->bind( SiteEditorAccessGate::class, fn (): SiteEditorAccessGate => new class implements SiteEditorAccessGate
	{
		public function check( Request $request ): ?Response
		{
			return response()->json( [ 'error' => 'host-shaped' ], Response::HTTP_UNAUTHORIZED );
		}
	} );

	$this->postJson( '/visual-editor/api/menus', [ 'title' => 'Primary' ] )
		->assertUnauthorized()
		->assertExactJson( [ 'error' => 'host-shaped' ] );
} );

it( 'lets an allowed user create a menu', function (): void {
	$this->postJson( '/visual-editor/api/menus', [ 'title' => 'Primary' ] )
		->assertCreated()
		->assertJsonPath( 'slug', 'primary' );
} );

it( 'lets an allowed user create a template part', function (): void {
	$this->postJson( '/visual-editor/api/template-parts', [ 'slug' => 'header', 'area' => 'header', 'title' => 'Header' ] )
		->assertSuccessful();
} );

it( 'lets an allowed user create a pattern', function (): void {
	$this->postJson( '/visual-editor/api/patterns', [ 'slug' => 'cta', 'title' => 'CTA' ] )
		->assertCreated();
} );

it( 'keeps site-editor reads open to authenticated users the gate denies', function ( string $uri ): void {
	$this->denySiteEditorAccess();

	$this->getJson( $uri )->assertOk();
} )->with( [
	'/visual-editor/api/menus',
	'/visual-editor/api/template-parts',
	'/visual-editor/api/templates',
	'/visual-editor/api/patterns',
	'/visual-editor/api/global-styles/base',
] );

it( 'still requires authentication for reads', function (): void {
	auth()->logout();

	$this->getJson( '/visual-editor/api/menus' )->assertUnauthorized();
} );
