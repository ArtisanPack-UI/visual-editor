<?php

/**
 * Capability probe tests (M-1): a permission backend that throws must deny
 * instead of erroring, and Spatie's `checkPermissionTo()` is preferred.
 *
 * @since 1.15.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Ai\Support\AiAccess;
use ArtisanPackUI\VisualEditor\Effects\BlockCssAccess;
use ArtisanPackUI\VisualEditor\Effects\CustomCssGuard;
use ArtisanPackUI\VisualEditor\Fonts\Policies\FontPolicy;
use ArtisanPackUI\VisualEditor\Support\CapabilityCheck;
use ArtisanPackUI\VisualEditor\Support\ContentAccess;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Gate;
use Tests\Support\CheckPermissionUser;
use Tests\Support\FontPermissionUser;
use Tests\Support\ThrowingPermissionUser;
use Tests\TestUser;

beforeEach( function (): void {
	config()->set( 'artisanpack.visual-editor.content_access.capability', 'edit_content' );
	config()->set( 'artisanpack.visual-editor.ai.capability', 'use_ai_features' );
	config()->set( 'artisanpack.visual-editor.fonts.capability', 'manage_fonts' );
} );

it( 'denies and reports when the permission backend throws', function (): void {
	$reported = [];

	$this->mock( ExceptionHandler::class, function ( $mock ) use ( &$reported ): void {
		$mock->shouldReceive( 'report' )->andReturnUsing( function ( Throwable $e ) use ( &$reported ): void {
			$reported[] = $e->getMessage();
		} );
	} );

	expect( CapabilityCheck::check( new ThrowingPermissionUser(), 'edit_block_css' ) )->toBeFalse()
		->and( $reported )->toBe( [ 'There is no permission named `edit_block_css`.' ] );
} );

it( 'prefers checkPermissionTo() over a throwing hasPermissionTo()', function (): void {
	$user                     = new CheckPermissionUser();
	$user->grantedPermissions = [ 'edit_block_css' ];

	expect( CapabilityCheck::check( $user, 'edit_block_css' ) )->toBeTrue()
		->and( CapabilityCheck::check( $user, 'other' ) )->toBeFalse();
} );

it( 'returns null when the user exposes no capability method', function (): void {
	expect( CapabilityCheck::check( new TestUser(), 'edit_block_css' ) )->toBeNull();
} );

it( 'keeps every gate deny-by-default when hasPermissionTo() throws', function (): void {
	$user = new ThrowingPermissionUser();

	expect( BlockCssAccess::allows( $user ) )->toBeFalse()
		->and( AiAccess::allows( $user ) )->toBeFalse()
		->and( ContentAccess::allows( $user ) )->toBeFalse()
		->and( ( new FontPolicy() )->manage( $user ) )->toBeFalse();
} );

it( 'allows every gate through checkPermissionTo()', function (): void {
	$user                     = new CheckPermissionUser();
	$user->grantedPermissions = [ 'edit_block_css', 'use_ai_features', 'edit_content', 'manage_fonts' ];

	expect( BlockCssAccess::allows( $user ) )->toBeTrue()
		->and( AiAccess::allows( $user ) )->toBeTrue()
		->and( ContentAccess::allows( $user ) )->toBeTrue()
		->and( ( new FontPolicy() )->manage( $user ) )->toBeTrue();
} );

it( 'still honours the rbac hasPermissionTo() contract', function (): void {
	$user                     = new FontPermissionUser();
	$user->grantedPermissions = [ 'edit_block_css' ];

	expect( BlockCssAccess::allows( $user ) )->toBeTrue();
} );

it( 'denies the block-CSS gate and strips Custom CSS without throwing', function (): void {
	$this->actingAs( new ThrowingPermissionUser() );

	$blocks = [ [ 'name' => 'core/group', 'attributes' => [ 'artisanpackEffects' => [ 'customCss' => 'color: red;' ] ], 'innerBlocks' => [] ] ];

	expect( Gate::allows( BlockCssAccess::ABILITY ) )->toBeFalse()
		->and( app( CustomCssGuard::class )->filter( $blocks )[0]['attributes']['artisanpackEffects'] )->toBe( [] );
} );

it( 'allows the block-CSS gate through checkPermissionTo()', function (): void {
	$user                     = new CheckPermissionUser();
	$user->grantedPermissions = [ 'edit_block_css' ];

	$this->actingAs( $user );

	expect( Gate::allows( BlockCssAccess::ABILITY ) )->toBeTrue();
} );
