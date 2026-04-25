<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorNavigation;
use Tests\TestUser;

function makeMenuLocationNavigation( array $overrides = [] ): VisualEditorNavigation
{
	return VisualEditorNavigation::create( array_merge( [
		'slug'       => 'primary-nav',
		'title'      => 'Primary',
		'content'    => [
			'raw'    => '',
			'blocks' => [
				[
					'name'        => 'core/navigation-link',
					'attributes'  => [ 'label' => 'Home', 'url' => '/' ],
					'innerBlocks' => [],
				],
			],
		],
		'status'     => 'publish',
		'menu_order' => 0,
	], $overrides ) );
}

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

beforeEach( function () {
	config( [
		'artisanpack.visual-editor.navigation.locations' => [
			'primary' => [
				'slug'       => 'primary',
				'label'      => 'Primary Menu',
				'primary_id' => null,
			],
			'footer'  => [
				'slug'       => 'footer',
				'label'      => 'Footer',
				'primary_id' => null,
			],
		],
	] );
} );

it( 'returns 401 when unauthenticated', function () {
	$this->getJson( '/visual-editor/api/menu-locations' )->assertUnauthorized();
} );

it( 'returns the configured locations with no menu when nothing is published', function () {
	actingAsMenuLocationUser();

	$this->getJson( '/visual-editor/api/menu-locations' )
		->assertOk()
		->assertJsonCount( 2, 'data' )
		->assertJsonPath( 'data.0.slug', 'primary' )
		->assertJsonPath( 'data.0.menu', null )
		->assertJsonPath( 'data.1.slug', 'footer' )
		->assertJsonPath( 'data.1.menu', null );
} );

it( 'flags the resolved menu as a fallback when no DB assignment exists', function () {
	actingAsMenuLocationUser();

	makeMenuLocationNavigation( [ 'slug' => 'primary-nav', 'location' => null ] );

	$this->getJson( '/visual-editor/api/menu-locations' )
		->assertOk()
		->assertJsonPath( 'data.0.menu.slug', 'primary-nav' )
		->assertJsonPath( 'data.0.is_fallback', true );
} );

it( 'reports a direct assignment as not-fallback', function () {
	actingAsMenuLocationUser();

	$assigned = makeMenuLocationNavigation( [
		'slug'     => 'primary-nav',
		'location' => 'primary',
	] );

	$this->getJson( '/visual-editor/api/menu-locations' )
		->assertOk()
		->assertJsonPath( 'data.0.menu.id', $assigned->id )
		->assertJsonPath( 'data.0.is_fallback', false );
} );
