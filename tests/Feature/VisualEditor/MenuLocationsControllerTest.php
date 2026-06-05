<?php

declare( strict_types=1 );

use Tests\TestUser;

/*
 * The controller is a thin facade over cms-framework's `ThemeManager` +
 * `MenuLocationAssignment` — see `src/Http/Controllers/MenuLocationsController.php`
 * for the contract. Tests cover the boundary cases the controller owns:
 * auth-gating (middleware-driven) and the no-cms-framework fallback.
 * The actual data path is exercised by the consuming app's test suite
 * (Keystone CMS) where a real theme + assignment table is available;
 * reproducing that here would require a Testbench fixture larger than
 * the value it adds.
 */

function actingAsMenuLocationUser(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Locations Tester',
		'email'    => 'locations+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

it( 'returns 401 when unauthenticated', function () {
	$this->getJson( '/visual-editor/api/menu-locations' )->assertUnauthorized();
} );

it( 'returns an empty data array when cms-framework is not installed', function () {
	actingAsMenuLocationUser();

	// In the Testbench environment cms-framework's `ThemeManager` is not
	// registered, so the controller's class_exists check short-circuits
	// to an empty response. The Phase H install gate is the user-facing
	// surface for the same condition; this just verifies the API stays
	// well-formed if a direct caller bypasses the gate.
	$this->getJson( '/visual-editor/api/menu-locations' )
		->assertOk()
		->assertExactJson( [ 'data' => [] ] );
} );
