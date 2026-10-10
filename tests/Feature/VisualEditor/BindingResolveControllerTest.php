<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\Fixtures\TestBindingsModel;
use Tests\TestUser;

beforeEach( function (): void {
	config()->set( 'artisanpack.visual-editor.api.middleware', [ 'auth' ] );

	config()->set( 'artisanpack.visual-editor.resources', [
		'bindings' => TestBindingsModel::class,
	] );

	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();

	$this->actor = TestUser::create( [
		'name'     => 'Resolve Tester',
		'email'    => 'resolve+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $this->actor );
} );

it( 'resolves a binding to the parent model column value', function (): void {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Bound Title',
		'status'  => 'published',
		'content' => [],
	] );

	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => [ 'icon' => 'STATIC', 'label' => 'Original' ],
		'bindings' => [
			'icon' => [
				'source' => 'custom_field',
				'args'   => [ 'key' => 'title' ],
			],
		],
		'context'  => [
			'resource' => 'bindings',
			'id'       => $model->getKey(),
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'values.icon', 'Bound Title' );
} );

it( 'returns the static fallback when the bound field is empty', function (): void {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Has Title',
		'status'  => 'published',
		'excerpt' => null,
		'content' => [],
	] );

	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => [ 'label' => 'Fallback' ],
		'bindings' => [
			'label' => [
				'source' => 'custom_field',
				'args'   => [ 'key' => 'excerpt' ],
			],
		],
		'context'  => [
			'resource' => 'bindings',
			'id'       => $model->getKey(),
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'values.label', 'Fallback' );
} );

it( 'returns an empty values object when no bindings are supplied', function (): void {
	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => [ 'icon' => 'STATIC' ],
		'bindings' => [],
		'context'  => [],
	] );

	$response->assertOk()
		->assertJsonPath( 'values', [] );
} );

it( 'returns the fallback value when no context is supplied', function (): void {
	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => [ 'icon' => 'STATIC' ],
		'bindings' => [
			'icon' => [
				'source' => 'custom_field',
				'args'   => [ 'key' => 'title' ],
			],
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'values.icon', 'STATIC' );
} );

it( 'rejects payloads where attrs or bindings is not an array', function (): void {
	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => 'not-an-array',
		'bindings' => [],
	] );

	$response->assertStatus( 422 )
		->assertJsonPath( 'error', 'invalid_payload' );

	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => [],
		'bindings' => 'not-an-array',
	] );

	$response->assertStatus( 422 )
		->assertJsonPath( 'error', 'invalid_payload' );
} );

it( 'reflects draft overrides ahead of saved column values', function (): void {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'Saved Title',
		'status'  => 'published',
		'content' => [],
	] );

	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => [ 'icon' => 'STATIC' ],
		'bindings' => [
			'icon' => [
				'source' => 'custom_field',
				'args'   => [ 'key' => 'title' ],
			],
		],
		'context'  => [
			'resource' => 'bindings',
			'id'       => $model->getKey(),
			'draft'    => [ 'title' => 'Live Draft' ],
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'values.icon', 'Live Draft' );
} );

it( 'resolves multiple bindings on the same block in one round-trip', function (): void {
	$model = TestBindingsModel::query()->create( [
		'title'   => 'My Title',
		'excerpt' => 'My Excerpt',
		'status'  => 'published',
		'content' => [],
	] );

	$response = $this->postJson( '/visual-editor/api/bindings/resolve', [
		'attrs'    => [ 'a' => 'a-static', 'b' => 'b-static' ],
		'bindings' => [
			'a' => [ 'source' => 'post_core', 'args' => [ 'key' => 'title' ] ],
			'b' => [ 'source' => 'post_core', 'args' => [ 'key' => 'excerpt' ] ],
		],
		'context'  => [
			'resource' => 'bindings',
			'id'       => $model->getKey(),
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'values.a', 'My Title' )
		->assertJsonPath( 'values.b', 'My Excerpt' );
} );
