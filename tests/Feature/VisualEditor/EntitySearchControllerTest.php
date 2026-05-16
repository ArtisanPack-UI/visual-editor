<?php

declare( strict_types=1 );

use Tests\TestUser;

/*
 * Template + template-part searches reach cms-framework's
 * `TemplateResolver` / `TemplatePartResolver` (see
 * `src/Http/Controllers/EntitySearchController.php`). The resolvers
 * aren't bound in this Testbench environment, so the template /
 * template-part branches return empty data — the consuming app's test
 * suite covers the populated path.
 *
 * Resource-config sources (pages, posts, etc.) are still testable
 * here because they go through Eloquent directly via the
 * `artisanpack.visual-editor.resources` map.
 */

function actingAsSearchUser(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Search Tester',
		'email'    => 'search+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

it( 'returns an empty data array when type is missing', function () {
	actingAsSearchUser();

	$this->getJson( '/visual-editor/api/search' )
		->assertOk()
		->assertJsonPath( 'data', [] );
} );

it( 'returns an empty data array for an unknown type slug', function () {
	actingAsSearchUser();

	$this->getJson( '/visual-editor/api/search?type=mystery&q=anything' )
		->assertOk()
		->assertJsonPath( 'data', [] );
} );

it( 'returns an empty data array for template type when cms-framework is not installed', function () {
	actingAsSearchUser();

	$this->getJson( '/visual-editor/api/search?type=template' )
		->assertOk()
		->assertJsonPath( 'data', [] );
} );

it( 'returns an empty data array for template-part type when cms-framework is not installed', function () {
	actingAsSearchUser();

	$this->getJson( '/visual-editor/api/search?type=template-part' )
		->assertOk()
		->assertJsonPath( 'data', [] );
} );
