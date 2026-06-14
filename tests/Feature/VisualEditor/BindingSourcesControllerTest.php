<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Registries\BlockBindingSourceRegistry;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\BlockBindingSource;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\Fixtures\TestBindingsModel;
use Tests\TestUser;

beforeEach( function () {
	config()->set( 'artisanpack.visual-editor.api.middleware', [ 'auth' ] );

	config()->set( 'artisanpack.visual-editor.resources', [
		'bindings' => TestBindingsModel::class,
	] );

	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();

	$this->actor = TestUser::create( [
		'name'     => 'Sources Tester',
		'email'    => 'sources+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $this->actor );
} );

it( 'lists the built-in registered sources', function () {
	$response = $this->getJson( '/visual-editor/api/bindings/sources' );

	$response->assertOk();

	$names = collect( $response->json( 'sources' ) )->pluck( 'name' )->all();

	expect( $names )->toContain( 'custom_field', 'post_core', 'relation' );
} );

it( 'returns the post_core field catalog for a known resource', function () {
	$response = $this->getJson( '/visual-editor/api/bindings/sources/post_core/fields?resource=bindings' );

	$response->assertOk()
		->assertJsonPath( 'source', 'post_core' )
		->assertJsonPath( 'resource', 'bindings' );

	$keys = collect( $response->json( 'fields' ) )->pluck( 'key' )->all();

	expect( $keys )->toContain( 'title', 'excerpt', 'author_name' );
} );

it( 'returns the post_core catalog even when no resource is supplied', function () {
	$response = $this->getJson( '/visual-editor/api/bindings/sources/post_core/fields' );

	$response->assertOk();

	$keys = collect( $response->json( 'fields' ) )->pluck( 'key' )->all();

	expect( $keys )->toContain( 'title' );
} );

it( 'returns an empty field catalog for the relation source', function () {
	$response = $this->getJson( '/visual-editor/api/bindings/sources/relation/fields?resource=bindings' );

	$response->assertOk()
		->assertJsonPath( 'fields', [] );
} );

it( 'returns 404 for an unknown source', function () {
	$response = $this->getJson( '/visual-editor/api/bindings/sources/imaginary/fields?resource=bindings' );

	$response->assertNotFound()
		->assertJsonPath( 'error', 'source_not_registered' )
		->assertJsonPath( 'source', 'imaginary' );
} );

it( 'rejects a source name that does not match the snake_case pattern at route level', function () {
	$response = $this->getJson( '/visual-editor/api/bindings/sources/BAD-NAME/fields' );

	$response->assertNotFound();
} );

it( 'reflects host-registered custom sources in the listing and field endpoints', function () {
	app( BlockBindingSourceRegistry::class )->register( new class implements BlockBindingSource
	{
		public function name(): string
		{
			return 'site_settings';
		}

		public function resolve( BindingContext $context, array $args ): mixed
		{
			return null;
		}

		public function eagerLoadRelations( array $bindingArgs ): array
		{
			return [];
		}

		public function availableFields( string $resource, ?string $modelClass = null ): array
		{
			return [
				[ 'key' => 'site_name', 'label' => 'Site name', 'type' => 'string' ],
			];
		}
	} );

	$list = $this->getJson( '/visual-editor/api/bindings/sources' );
	$names = collect( $list->json( 'sources' ) )->pluck( 'name' )->all();
	expect( $names )->toContain( 'site_settings' );

	$fields = $this->getJson( '/visual-editor/api/bindings/sources/site_settings/fields?resource=bindings' );
	$fields->assertOk()
		->assertJsonPath( 'fields.0.key', 'site_name' )
		->assertJsonPath( 'fields.0.type', 'string' );
} );
