<?php

/**
 * Template Site Editor Policy Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Policies
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Template;
use ArtisanPackUI\VisualEditor\Policies\TemplateSiteEditorPolicy;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

uses( RefreshDatabase::class );

/**
 * Create a test user for policy tests.
 *
 * @param array<string, bool> $abilities Gate abilities to define.
 *
 * @return Authenticatable
 */
function createPolicyTestUser( array $abilities = [] ): Authenticatable
{
	$email = 'policy-test-' . Illuminate\Support\Str::random( 6 ) . '@example.com';

	$id = DB::table( 'users' )->insertGetId( [
		'name'       => 'Policy Test User',
		'email'      => $email,
		'created_at' => now(),
		'updated_at' => now(),
	] );

	$user        = new class () extends Authenticatable {
		protected $table = 'users';
	};
	$user->id    = $id;
	$user->name  = 'Policy Test User';
	$user->email = $email;

	foreach ( $abilities as $ability => $allowed ) {
		Gate::define( $ability, fn () => $allowed );
	}

	return $user;
}

test( 'viewAny allows user with manage-templates permission', function (): void {
	$user   = createPolicyTestUser( [ 'visual-editor.manage-templates' => true ] );
	$policy = new TemplateSiteEditorPolicy();

	expect( $policy->viewAny( $user ) )->toBeTrue();
} );

test( 'viewAny denies user without manage-templates permission', function (): void {
	$user   = createPolicyTestUser( [ 'visual-editor.manage-templates' => false ] );
	$policy = new TemplateSiteEditorPolicy();

	expect( $policy->viewAny( $user ) )->toBeFalse();
} );

test( 'create allows user with manage-templates permission', function (): void {
	$user   = createPolicyTestUser( [ 'visual-editor.manage-templates' => true ] );
	$policy = new TemplateSiteEditorPolicy();

	expect( $policy->create( $user ) )->toBeTrue();
} );

test( 'create denies user without manage-templates permission', function (): void {
	$user   = createPolicyTestUser( [ 'visual-editor.manage-templates' => false ] );
	$policy = new TemplateSiteEditorPolicy();

	expect( $policy->create( $user ) )->toBeFalse();
} );

test( 'update allows user with manage-templates permission on unlocked template', function (): void {
	$user     = createPolicyTestUser( [ 'visual-editor.manage-templates' => true ] );
	$policy   = new TemplateSiteEditorPolicy();
	$template = Template::create( [
		'name'      => 'Test Template',
		'slug'      => 'test-update-' . Illuminate\Support\Str::random( 6 ),
		'type'      => 'page',
		'content'   => [],
		'status'    => 'active',
		'is_custom' => true,
		'is_locked' => false,
	] );

	expect( $policy->update( $user, $template ) )->toBeTrue();
} );

test( 'update on locked template requires lock-content permission', function (): void {
	$user     = createPolicyTestUser( [
		'visual-editor.manage-templates' => true,
		'visual-editor.lock-content'     => true,
	] );
	$policy   = new TemplateSiteEditorPolicy();
	$template = Template::create( [
		'name'      => 'Locked Template',
		'slug'      => 'test-locked-' . Illuminate\Support\Str::random( 6 ),
		'type'      => 'page',
		'content'   => [],
		'status'    => 'active',
		'is_custom' => true,
		'is_locked' => true,
	] );

	expect( $policy->update( $user, $template ) )->toBeTrue();
} );

test( 'update on locked template denied without lock-content permission', function (): void {
	$user     = createPolicyTestUser( [
		'visual-editor.manage-templates' => true,
		'visual-editor.lock-content'     => false,
	] );
	$policy   = new TemplateSiteEditorPolicy();
	$template = Template::create( [
		'name'      => 'Locked Template',
		'slug'      => 'test-locked-deny-' . Illuminate\Support\Str::random( 6 ),
		'type'      => 'page',
		'content'   => [],
		'status'    => 'active',
		'is_custom' => true,
		'is_locked' => true,
	] );

	expect( $policy->update( $user, $template ) )->toBeFalse();
} );

test( 'update on locked template denied with lock-content but without manage-templates', function (): void {
	$user     = createPolicyTestUser( [
		'visual-editor.manage-templates' => false,
		'visual-editor.lock-content'     => true,
	] );
	$policy   = new TemplateSiteEditorPolicy();
	$template = Template::create( [
		'name'      => 'Locked Template',
		'slug'      => 'test-locked-no-manage-' . Illuminate\Support\Str::random( 6 ),
		'type'      => 'page',
		'content'   => [],
		'status'    => 'active',
		'is_custom' => true,
		'is_locked' => true,
	] );

	expect( $policy->update( $user, $template ) )->toBeFalse();
} );

test( 'delete allows user with manage-templates permission on unlocked template', function (): void {
	$user     = createPolicyTestUser( [ 'visual-editor.manage-templates' => true ] );
	$policy   = new TemplateSiteEditorPolicy();
	$template = Template::create( [
		'name'      => 'Deletable Template',
		'slug'      => 'test-delete-' . Illuminate\Support\Str::random( 6 ),
		'type'      => 'page',
		'content'   => [],
		'status'    => 'active',
		'is_custom' => true,
		'is_locked' => false,
	] );

	expect( $policy->delete( $user, $template ) )->toBeTrue();
} );

test( 'delete always denied on locked template', function (): void {
	$user     = createPolicyTestUser( [
		'visual-editor.manage-templates' => true,
		'visual-editor.lock-content'     => true,
	] );
	$policy   = new TemplateSiteEditorPolicy();
	$template = Template::create( [
		'name'      => 'Locked Template',
		'slug'      => 'test-delete-locked-' . Illuminate\Support\Str::random( 6 ),
		'type'      => 'page',
		'content'   => [],
		'status'    => 'active',
		'is_custom' => true,
		'is_locked' => true,
	] );

	expect( $policy->delete( $user, $template ) )->toBeFalse();
} );
