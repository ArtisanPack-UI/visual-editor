<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Facades\VisualEditor;
use Tests\TestUser;

it( 'returns 401 for unauthenticated blocks requests', function () {
	$response = $this->getJson( '/visual-editor/api/blocks' );

	$response->assertUnauthorized();
} );

it( 'returns the registered block types from block.json manifests', function () {
	$user = TestUser::create( [
		'name'     => 'Jane',
		'email'    => 'jane@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );

	$response = $this->getJson( '/visual-editor/api/blocks' );

	$response->assertOk()
		->assertJsonStructure( [
			'blocks' => [
				'*' => ['name', 'title'],
			],
		] );

	$names = collect( $response->json( 'blocks' ) )->pluck( 'name' )->all();

	expect( $names )->toContain( 'artisanpack/paragraph', 'artisanpack/heading' );
} );

it( 'flags server-rendered blocks with apServerRender for the editor', function () {
	VisualEditor::registerServerBlock(
		'tests/server-widget',
		[
			'title'      => 'Server Widget',
			'category'   => 'widgets',
			'attributes' => [ 'label' => [ 'type' => 'string', 'default' => '' ] ],
		],
		fn ( array $attrs ): string => '<div>' . ( $attrs['label'] ?? '' ) . '</div>',
	);

	// A block registered as a plain type (no dynamic renderer) must NOT be
	// flagged, so the editor only synthesizes server-render edits for blocks
	// that actually resolve on the server.
	VisualEditor::registerBlockType( 'tests/static-widget', [ 'title' => 'Static Widget' ] );

	$user = TestUser::create( [
		'name'     => 'Jane',
		'email'    => 'jane@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );

	$blocks = collect( $this->getJson( '/visual-editor/api/blocks' )->assertOk()->json( 'blocks' ) )
		->keyBy( 'name' );

	expect( $blocks->get( 'tests/server-widget' )['apServerRender'] ?? null )->toBeTrue()
		->and( $blocks->get( 'tests/static-widget' ) )->not->toHaveKey( 'apServerRender' )
		->and( $blocks->get( 'artisanpack/paragraph' ) )->not->toHaveKey( 'apServerRender' );
} );
