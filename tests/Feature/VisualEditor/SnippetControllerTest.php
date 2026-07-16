<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Snippet;
use Tests\TestUser;

beforeEach( function () {
	config()->set( 'artisanpack.visual-editor.api.middleware', [ 'auth' ] );

	$this->actor = TestUser::create( [
		'name'     => 'Snippet Tester',
		'email'    => 'snip+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $this->actor );
} );

it( 'lists snippets', function () {
	Snippet::factory()->create( [ 'slug' => 'cta_banner', 'title' => 'CTA Banner' ] );
	Snippet::factory()->create( [ 'slug' => 'footer',     'title' => 'Footer' ] );

	$response = $this->getJson( '/visual-editor/api/snippets' );

	$response->assertOk();
	expect( collect( $response->json( 'data' ) )->pluck( 'slug' )->all() )
		->toContain( 'cta_banner', 'footer' );
} );

it( 'creates a snippet with a valid slug and blocks tree', function () {
	$response = $this->postJson( '/visual-editor/api/snippets', [
		'slug'   => 'hero',
		'title'  => 'Hero',
		'blocks' => [
			[ 'name' => 'artisanpack/paragraph', 'attrs' => [ 'content' => 'Hi' ], 'innerBlocks' => [] ],
		],
	] );

	$response->assertCreated()
		->assertJsonPath( 'data.slug', 'hero' )
		->assertJsonPath( 'data.title', 'Hero' );

	expect( Snippet::where( 'slug', 'hero' )->exists() )->toBeTrue();
} );

it( 'rejects a slug that does not match the pattern', function () {
	$response = $this->postJson( '/visual-editor/api/snippets', [
		'slug'  => 'Bad-Slug',
		'title' => 'Nope',
	] );

	$response->assertStatus( 422 )
		->assertJsonPath( 'errors.slug.0', fn ( $msg ) => str_contains( strtolower( (string) $msg ), 'lowercase' ) );
} );

it( 'rejects a duplicate slug', function () {
	Snippet::factory()->create( [ 'slug' => 'hero' ] );

	$response = $this->postJson( '/visual-editor/api/snippets', [
		'slug' => 'hero',
	] );

	$response->assertStatus( 422 );
} );

it( 'updates and deletes a snippet', function () {
	$snippet = Snippet::factory()->create( [ 'slug' => 'cta', 'title' => 'Old' ] );

	$this->putJson( "/visual-editor/api/snippets/{$snippet->id}", [
		'slug'  => 'cta',
		'title' => 'New',
	] )->assertOk()->assertJsonPath( 'data.title', 'New' );

	$this->deleteJson( "/visual-editor/api/snippets/{$snippet->id}" )->assertNoContent();

	expect( Snippet::find( $snippet->id ) )->toBeNull();
} );

it( 'rejects a snippet that references itself directly', function () {
	Snippet::factory()->create( [ 'slug' => 'loop_a' ] );

	$response = $this->postJson( '/visual-editor/api/snippets', [
		'slug'   => 'loop_b',
		'blocks' => [
			[ 'name' => 'artisanpack/snippet', 'attrs' => [ 'slug' => 'loop_b' ], 'innerBlocks' => [] ],
		],
	] );

	$response->assertStatus( 422 );
} );
