<?php

/**
 * Font Library authorization policy tests (#733).
 *
 * Covers {@see \ArtisanPackUI\VisualEditor\Fonts\Policies\FontPolicy}: browsing
 * is always allowed, and the mutating `manage()` gate resolves the configured
 * capability against whichever RBAC contract the host user exposes — the
 * WordPress-style `hasCapability()` and, crucially for a stock cms-framework
 * host, the rbac `hasPermissionTo()` method — while degrading to a plain denial
 * on user models that compose neither.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Policies\FontPolicy;
use Tests\Support\FontCapabilityUser;
use Tests\Support\FontPermissionUser;
use Tests\TestUser;

beforeEach( function (): void {
	config()->set( 'artisanpack.visual-editor.fonts.capability', 'manage_fonts' );
} );

it( 'always allows browsing, even for a guest', function (): void {
	expect( ( new FontPolicy() )->viewAny( null ) )->toBeTrue();
	expect( ( new FontPolicy() )->viewAny( new TestUser() ) )->toBeTrue();
} );

it( 'denies management for a guest', function (): void {
	expect( ( new FontPolicy() )->manage( null ) )->toBeFalse();
} );

it( 'denies management when the configured capability is blank', function (): void {
	config()->set( 'artisanpack.visual-editor.fonts.capability', '' );

	$user                      = new FontCapabilityUser();
	$user->grantedCapabilities = [ 'manage_fonts' ];

	expect( ( new FontPolicy() )->manage( $user ) )->toBeFalse();
} );

it( 'allows a hasCapability() host user granted the capability', function (): void {
	$user                      = new FontCapabilityUser();
	$user->grantedCapabilities = [ 'manage_fonts' ];

	expect( ( new FontPolicy() )->manage( $user ) )->toBeTrue();
} );

it( 'denies a hasCapability() host user without the capability', function (): void {
	$user                      = new FontCapabilityUser();
	$user->grantedCapabilities = [ 'edit_posts' ];

	expect( ( new FontPolicy() )->manage( $user ) )->toBeFalse();
} );

it( 'allows a cms-framework rbac user granted the permission', function (): void {
	$user                     = new FontPermissionUser();
	$user->grantedPermissions = [ 'manage_fonts' ];

	expect( ( new FontPolicy() )->manage( $user ) )->toBeTrue();
} );

it( 'denies a cms-framework rbac user without the permission', function (): void {
	$user                     = new FontPermissionUser();
	$user->grantedPermissions = [ 'edit_posts' ];

	expect( ( new FontPolicy() )->manage( $user ) )->toBeFalse();
} );

it( 'degrades to a denial for a user model exposing no capability contract', function (): void {
	expect( ( new FontPolicy() )->manage( new TestUser() ) )->toBeFalse();
} );

it( 'honors a custom configured capability name', function (): void {
	config()->set( 'artisanpack.visual-editor.fonts.capability', 'fonts.administer' );

	$granted                     = new FontPermissionUser();
	$granted->grantedPermissions = [ 'fonts.administer' ];

	$denied                     = new FontPermissionUser();
	$denied->grantedPermissions = [ 'manage_fonts' ];

	expect( ( new FontPolicy() )->manage( $granted ) )->toBeTrue();
	expect( ( new FontPolicy() )->manage( $denied ) )->toBeFalse();
} );
