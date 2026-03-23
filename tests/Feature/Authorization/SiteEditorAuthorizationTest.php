<?php

/**
 * Site Editor Authorization Feature Tests.
 *
 * Tests permission checks for all site editor routes.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Authorization
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

uses( RefreshDatabase::class );

/**
 * Create a test user for authorization tests.
 *
 * @return Authenticatable
 */
function createAuthTestUser(): Authenticatable
{
	$email = 'auth-test-' . Illuminate\Support\Str::random( 6 ) . '@example.com';

	$id = DB::table( 'users' )->insertGetId( [
		'name'       => 'Auth Test User',
		'email'      => $email,
		'created_at' => now(),
		'updated_at' => now(),
	] );

	$user        = new class () extends Authenticatable {
		protected $table = 'users';
	};
	$user->id    = $id;
	$user->name  = 'Auth Test User';
	$user->email = $email;

	return $user;
}

test( 'hub route accessible with access-site-editor permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );

	$user = createAuthTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.site-editor' ) )
		->assertSuccessful();
} );

test( 'hub route forbidden without access-site-editor permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => false );

	$user = createAuthTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.site-editor' ) )
		->assertForbidden();
} );

test( 'global styles route accessible with both permissions', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );
	Gate::define( 'visual-editor.manage-styles', fn () => true );

	$user = createAuthTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.global-styles' ) )
		->assertSuccessful();
} );

test( 'global styles route forbidden without manage-styles permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );
	Gate::define( 'visual-editor.manage-styles', fn () => false );

	$user = createAuthTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.global-styles' ) )
		->assertForbidden();
} );

test( 'templates route accessible with both permissions', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );
	Gate::define( 'visual-editor.manage-templates', fn () => true );

	$user = createAuthTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.templates' ) )
		->assertSuccessful();
} );

test( 'templates route forbidden without manage-templates permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );
	Gate::define( 'visual-editor.manage-templates', fn () => false );

	$user = createAuthTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.templates' ) )
		->assertForbidden();
} );

test( 'template parts route accessible with both permissions', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );
	Gate::define( 'visual-editor.manage-parts', fn () => true );

	$user = createAuthTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.template-parts' ) )
		->assertSuccessful();
} );

test( 'template parts route forbidden without manage-parts permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );
	Gate::define( 'visual-editor.manage-parts', fn () => false );

	$user = createAuthTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.template-parts' ) )
		->assertForbidden();
} );

test( 'patterns route accessible with both permissions', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );
	Gate::define( 'visual-editor.manage-patterns', fn () => true );

	$user = createAuthTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.patterns' ) )
		->assertSuccessful();
} );

test( 'patterns route forbidden without manage-patterns permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );
	Gate::define( 'visual-editor.manage-patterns', fn () => false );

	$user = createAuthTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.patterns' ) )
		->assertForbidden();
} );

test( 'unauthenticated user cannot access hub route', function (): void {
	$response = $this->get( route( 'visual-editor.site-editor' ) );

	expect( $response->isSuccessful() )->toBeFalse();
} );

test( 'unauthenticated user cannot access global styles route', function (): void {
	$response = $this->get( route( 'visual-editor.global-styles' ) );

	expect( $response->isSuccessful() )->toBeFalse();
} );

test( 'unauthenticated user cannot access templates route', function (): void {
	$response = $this->get( route( 'visual-editor.templates' ) );

	expect( $response->isSuccessful() )->toBeFalse();
} );

test( 'unauthenticated user cannot access template parts route', function (): void {
	$response = $this->get( route( 'visual-editor.template-parts' ) );

	expect( $response->isSuccessful() )->toBeFalse();
} );

test( 'unauthenticated user cannot access patterns route', function (): void {
	$response = $this->get( route( 'visual-editor.patterns' ) );

	expect( $response->isSuccessful() )->toBeFalse();
} );
