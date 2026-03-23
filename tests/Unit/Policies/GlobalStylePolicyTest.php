<?php

/**
 * Global Style Policy Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Policies
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Policies\GlobalStylePolicy;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

uses( RefreshDatabase::class );

/**
 * Create a test user for global style policy tests.
 *
 * @param array<string, bool> $abilities Gate abilities to define.
 *
 * @return Authenticatable
 */
function createGlobalStylePolicyTestUser( array $abilities = [] ): Authenticatable
{
	$email = 'style-policy-' . Illuminate\Support\Str::random( 6 ) . '@example.com';

	$id = DB::table( 'users' )->insertGetId( [
		'name'       => 'Style Policy Test User',
		'email'      => $email,
		'created_at' => now(),
		'updated_at' => now(),
	] );

	$user        = new class () extends Authenticatable {
		protected $table = 'users';
	};
	$user->id    = $id;
	$user->name  = 'Style Policy Test User';
	$user->email = $email;

	foreach ( $abilities as $ability => $allowed ) {
		Gate::define( $ability, fn () => $allowed );
	}

	return $user;
}

test( 'update allows user with manage-styles permission', function (): void {
	$user   = createGlobalStylePolicyTestUser( [ 'visual-editor.manage-styles' => true ] );
	$policy = new GlobalStylePolicy();

	expect( $policy->update( $user ) )->toBeTrue();
} );

test( 'update denies user without manage-styles permission', function (): void {
	$user   = createGlobalStylePolicyTestUser( [ 'visual-editor.manage-styles' => false ] );
	$policy = new GlobalStylePolicy();

	expect( $policy->update( $user ) )->toBeFalse();
} );

test( 'reset allows user with manage-styles permission', function (): void {
	$user   = createGlobalStylePolicyTestUser( [ 'visual-editor.manage-styles' => true ] );
	$policy = new GlobalStylePolicy();

	expect( $policy->reset( $user ) )->toBeTrue();
} );

test( 'reset denies user without manage-styles permission', function (): void {
	$user   = createGlobalStylePolicyTestUser( [ 'visual-editor.manage-styles' => false ] );
	$policy = new GlobalStylePolicy();

	expect( $policy->reset( $user ) )->toBeFalse();
} );
