<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Visibility\Rules\LoginStateRule;
use ArtisanPackUI\VisualEditor\Visibility\Rules\SpecificUserRule;
use ArtisanPackUI\VisualEditor\Visibility\Rules\UserRoleRule;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;

function anon(): VisibilityContext
{
	return new VisibilityContext();
}

function loggedIn( array $roles = [], ?int $id = 42, ?string $email = 'me@example.com' ): VisibilityContext
{
	return new VisibilityContext(
		isAuthenticated: true,
		userId:          $id,
		userEmail:       $email,
		roles:           $roles,
	);
}

// LoginStateRule

it( 'login state either short-circuits to visible', function () {
	$rule = new LoginStateRule();
	expect( $rule->evaluate( [ 'state' => 'either' ], anon() )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( [ 'state' => 'either' ], loggedIn() )->isVisible() )->toBeTrue();
} );

it( 'login state loggedIn hides anonymous visitors', function () {
	$rule = new LoginStateRule();
	expect( $rule->evaluate( [ 'state' => 'loggedIn' ], anon() )->isHidden() )->toBeTrue();
	expect( $rule->evaluate( [ 'state' => 'loggedIn' ], loggedIn() )->isVisible() )->toBeTrue();
} );

it( 'login state loggedOut hides authenticated visitors', function () {
	$rule = new LoginStateRule();
	expect( $rule->evaluate( [ 'state' => 'loggedOut' ], loggedIn() )->isHidden() )->toBeTrue();
	expect( $rule->evaluate( [ 'state' => 'loggedOut' ], anon() )->isVisible() )->toBeTrue();
} );

// UserRoleRule

it( 'user role rule short-circuits without DB queries for anonymous visitors', function () {
	$rule = new UserRoleRule();
	// direction=show + any + roles configured → anon has no match → hidden
	expect( $rule->evaluate( [ 'direction' => 'show', 'combinator' => 'any', 'roles' => [ 'admin' ] ], anon() )->isHidden() )->toBeTrue();
	// direction=hide + any + roles configured → anon has no match → visible
	expect( $rule->evaluate( [ 'direction' => 'hide', 'combinator' => 'any', 'roles' => [ 'admin' ] ], anon() )->isVisible() )->toBeTrue();
} );

it( 'user role rule with combinator=any matches when the user has ANY of the roles', function () {
	$rule = new UserRoleRule();
	$attrs = [ 'direction' => 'show', 'combinator' => 'any', 'roles' => [ 'admin', 'editor' ] ];
	expect( $rule->evaluate( $attrs, loggedIn( [ 'editor' ] ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, loggedIn( [ 'subscriber' ] ) )->isHidden() )->toBeTrue();
} );

it( 'user role rule with combinator=all matches only when the user has ALL of the roles', function () {
	$rule = new UserRoleRule();
	$attrs = [ 'direction' => 'show', 'combinator' => 'all', 'roles' => [ 'admin', 'billing' ] ];
	expect( $rule->evaluate( $attrs, loggedIn( [ 'admin', 'billing' ] ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, loggedIn( [ 'admin' ] ) )->isHidden() )->toBeTrue();
} );

// SpecificUserRule

it( 'specific user rule always fails for anonymous', function () {
	$rule = new SpecificUserRule();
	$attrs = [ 'direction' => 'show', 'users' => [ [ 'id' => 42, 'email' => 'me@example.com' ] ] ];
	expect( $rule->evaluate( $attrs, anon() )->isHidden() )->toBeTrue();
} );

it( 'specific user rule matches by email (case-insensitive)', function () {
	$rule = new SpecificUserRule();
	$attrs = [ 'direction' => 'show', 'users' => [ [ 'id' => 1, 'email' => 'ME@EXAMPLE.COM' ] ] ];
	expect( $rule->evaluate( $attrs, loggedIn( [], 999, 'me@example.com' ) )->isVisible() )->toBeTrue();
} );

it( 'specific user rule matches by id as fallback', function () {
	$rule = new SpecificUserRule();
	$attrs = [ 'direction' => 'show', 'users' => [ [ 'id' => 42, 'email' => 'wrong@example.com' ] ] ];
	expect( $rule->evaluate( $attrs, loggedIn( [], 42, 'other@example.com' ) )->isVisible() )->toBeTrue();
} );

it( 'specific user rule matches UUID string IDs', function () {
	$rule = new SpecificUserRule();
	$uuid  = '018f4d2a-6d3a-7000-b5f3-3f5e8a2e1c9d';
	$attrs = [ 'direction' => 'show', 'users' => [ [ 'id' => $uuid, 'email' => 'wrong@example.com' ] ] ];

	$context = new VisibilityContext(
		isAuthenticated: true,
		userId:          $uuid,
		userEmail:       'other@example.com',
	);

	expect( $rule->evaluate( $attrs, $context )->isVisible() )->toBeTrue();
} );
