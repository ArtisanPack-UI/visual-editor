<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Facades\VisualEditor;
use ArtisanPackUI\VisualEditor\Registries\BlockBindingSourceRegistry;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\BlockBindingSource;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\Fixtures\TestBindingsModel;
use Tests\Fixtures\TestDynamicBlock;
use Tests\TestUser;

beforeEach( function () {
	config()->set( 'artisanpack.visual-editor.api.middleware', [ 'auth' ] );

	config()->set( 'artisanpack.visual-editor.resources', [
		'bindings' => TestBindingsModel::class,
	] );

	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();

	$this->actor = TestUser::create( [
		'name'     => 'Binding Tester',
		'email'    => 'binding+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $this->actor );
} );

it( 'resolves a bound attribute against the parent model when context is supplied', function () {
	VisualEditor::registerDynamicBlock( TestDynamicBlock::class );

	$model = TestBindingsModel::query()->create( [
		'title'   => 'Bound Title',
		'status'  => 'published',
		'content' => [],
	] );

	$response = $this->postJson( '/visual-editor/api/blocks/preview', [
		'name'       => 'tests/hello',
		'attributes' => [ 'name' => 'Static', 'greeting' => 'Hi' ],
		'bindings'   => [
			'name' => [
				'source' => 'custom_field',
				'args'   => [ 'key' => 'title' ],
			],
		],
		'context'    => [
			'resource' => 'bindings',
			'id'       => $model->getKey(),
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'html', '<p>Hi, Bound Title!</p>' );
} );

it( 'falls back to the static value when the bound field is empty', function () {
	VisualEditor::registerDynamicBlock( TestDynamicBlock::class );

	$model = TestBindingsModel::query()->create( [
		'title'   => 'Has Title',
		'status'  => 'published',
		'excerpt' => null,
		'content' => [],
	] );

	$response = $this->postJson( '/visual-editor/api/blocks/preview', [
		'name'       => 'tests/hello',
		'attributes' => [ 'name' => 'Fallback', 'greeting' => 'Hi' ],
		'bindings'   => [
			'name' => [
				'source' => 'custom_field',
				'args'   => [ 'key' => 'excerpt' ],
			],
		],
		'context'    => [
			'resource' => 'bindings',
			'id'       => $model->getKey(),
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'html', '<p>Hi, Fallback!</p>' );
} );

it( 'falls back when no context is supplied', function () {
	VisualEditor::registerDynamicBlock( TestDynamicBlock::class );

	$response = $this->postJson( '/visual-editor/api/blocks/preview', [
		'name'       => 'tests/hello',
		'attributes' => [ 'name' => 'Solo', 'greeting' => 'Hi' ],
		'bindings'   => [
			'name' => [
				'source' => 'custom_field',
				'args'   => [ 'key' => 'title' ],
			],
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'html', '<p>Hi, Solo!</p>' );
} );

it( 'falls back silently when the resource is unknown', function () {
	VisualEditor::registerDynamicBlock( TestDynamicBlock::class );

	$response = $this->postJson( '/visual-editor/api/blocks/preview', [
		'name'       => 'tests/hello',
		'attributes' => [ 'name' => 'Static', 'greeting' => 'Hi' ],
		'bindings'   => [
			'name' => [
				'source' => 'custom_field',
				'args'   => [ 'key' => 'title' ],
			],
		],
		'context'    => [
			'resource' => 'not-registered',
			'id'       => 999,
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'html', '<p>Hi, Static!</p>' );
} );

it( 'reflects unsaved draft overrides in the bound attribute', function () {
	VisualEditor::registerDynamicBlock( TestDynamicBlock::class );

	$model = TestBindingsModel::query()->create( [
		'title'   => 'Saved',
		'status'  => 'published',
		'content' => [],
	] );

	$response = $this->postJson( '/visual-editor/api/blocks/preview', [
		'name'       => 'tests/hello',
		'attributes' => [ 'name' => 'Static', 'greeting' => 'Hi' ],
		'bindings'   => [
			'name' => [
				'source' => 'custom_field',
				'args'   => [ 'key' => 'title' ],
			],
		],
		'context'    => [
			'resource' => 'bindings',
			'id'       => $model->getKey(),
			'draft'    => [ 'title' => 'Editor Draft' ],
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'html', '<p>Hi, Editor Draft!</p>' );
} );

it( 'lets a host application register a custom source driver', function () {
	$registry = app( BlockBindingSourceRegistry::class );

	$registry->register( new class implements BlockBindingSource
	{
		public function name(): string
		{
			return 'site_settings';
		}

		public function resolve( BindingContext $context, array $args ): mixed
		{
			$key = is_string( $args['key'] ?? null ) ? $args['key'] : '';

			$settings = [ 'site_name' => 'Custom Site' ];

			return $settings[ $key ] ?? null;
		}

		public function eagerLoadRelations( array $bindingArgs ): array
		{
			return [];
		}

		public function availableFields( string $resource, ?string $modelClass = null ): array
		{
			return [];
		}
	} );

	VisualEditor::registerDynamicBlock( TestDynamicBlock::class );

	$response = $this->postJson( '/visual-editor/api/blocks/preview', [
		'name'       => 'tests/hello',
		'attributes' => [ 'name' => 'Static', 'greeting' => 'Hi' ],
		'bindings'   => [
			'name' => [
				'source' => 'site_settings',
				'args'   => [ 'key' => 'site_name' ],
			],
		],
	] );

	$response->assertOk()
		->assertJsonPath( 'html', '<p>Hi, Custom Site!</p>' );
} );

it( 'leaves a preview without a bindings payload unchanged (BC regression)', function () {
	VisualEditor::registerDynamicBlock( TestDynamicBlock::class );

	$response = $this->postJson( '/visual-editor/api/blocks/preview', [
		'name'       => 'tests/hello',
		'attributes' => [ 'name' => 'Original', 'greeting' => 'Hello' ],
	] );

	$response->assertOk()
		->assertJsonPath( 'html', '<p>Hello, Original!</p>' );
} );
