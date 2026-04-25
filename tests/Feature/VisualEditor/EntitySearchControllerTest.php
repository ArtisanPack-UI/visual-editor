<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;
use Tests\TestUser;

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

it( 'searches the visual_editor_templates table for type=template', function () {
	actingAsSearchUser();

	VisualEditorTemplate::create( [
		'slug'    => 'single-book',
		'title'   => 'Single Book Layout',
		'content' => [ 'raw' => '', 'blocks' => [] ],
		'theme'   => 'default',
		'status'  => 'publish',
		'source'  => 'custom',
	] );

	VisualEditorTemplate::create( [
		'slug'    => 'archive-book',
		'title'   => 'Archive Book Page',
		'content' => [ 'raw' => '', 'blocks' => [] ],
		'theme'   => 'default',
		'status'  => 'publish',
		'source'  => 'custom',
	] );

	VisualEditorTemplate::create( [
		'slug'    => 'index',
		'title'   => 'Default Index',
		'content' => [ 'raw' => '', 'blocks' => [] ],
		'theme'   => 'default',
		'status'  => 'publish',
		'source'  => 'custom',
	] );

	$response = $this->getJson( '/visual-editor/api/search?type=template&q=Book' )
		->assertOk();

	$rows = $response->json( 'data' );

	expect( $rows )->toHaveCount( 2 );
	expect( collect( $rows )->pluck( 'type' )->all() )->each->toBe( 'template' );
} );

it( 'caps the result list at MAX_RESULTS', function () {
	actingAsSearchUser();

	for ( $i = 0; $i < 25; $i++ ) {
		VisualEditorTemplate::create( [
			'slug'    => "page-{$i}",
			'title'   => "Page {$i}",
			'content' => [ 'raw' => '', 'blocks' => [] ],
			'theme'   => 'default',
			'status'  => 'publish',
			'source'  => 'custom',
		] );
	}

	$response = $this->getJson( '/visual-editor/api/search?type=template' )->assertOk();

	expect( $response->json( 'data' ) )->toHaveCount( 20 );
} );
