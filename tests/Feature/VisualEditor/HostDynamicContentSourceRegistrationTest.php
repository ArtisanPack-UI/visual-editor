<?php

/**
 * Coverage for the #762 host-registerable Dynamic Content sources API:
 * the VisualEditor facade entry point, the shared registry, the
 * DynamicContentSource resolver's host-first path, and the sources
 * controller's merged listing.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.9.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\DynamicContent\HostDynamicContentSource;
use ArtisanPackUI\VisualEditor\Facades\VisualEditor;
use ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry;
use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use ArtisanPackUI\VisualEditor\Registries\DynamicContentSourceRegistry;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\DynamicContentSource;
use ArtisanPackUI\VisualEditor\VisualEditor as VisualEditorClass;
use Tests\Support\FakeDynamicContentAccessor;
use Tests\Support\FakeDynamicContentTypeRegistry;
use Tests\TestUser;

beforeEach( function () {
	// Clear any registrations from prior tests without swapping the
	// container binding — VisualEditor captures the registry in its
	// constructor, so rebinding would leave the facade writing to a
	// different instance than the DynamicContentSource reads from.
	$registry = app( DynamicContentSourceRegistry::class );
	foreach ( array_keys( $registry->all() ) as $slug ) {
		$registry->unregister( $slug );
	}
} );

it( 'registers a singleton host source via the facade', function () {
	$source = VisualEditor::registerDynamicContentSource( [
		'slug'        => 'business',
		'label'       => 'Business',
		'cardinality' => 'singleton',
		'fields'      => [
			[ 'slug' => 'name',  'label' => 'Name',  'type' => 'text' ],
			[ 'slug' => 'phone', 'label' => 'Phone', 'type' => 'phone' ],
		],
		'resolver'    => fn () => [
			'name'  => 'Acme Roofing',
			'phone' => '(555) 123-4567',
		],
	] );

	expect( $source )->toBeInstanceOf( HostDynamicContentSource::class );
	expect( VisualEditor::getDynamicContentSourceRegistry()->has( 'business' ) )->toBeTrue();
} );

it( 'rejects a definition missing required fields', function () {
	expect( fn () => VisualEditor::registerDynamicContentSource( [
		'label'       => 'No slug',
		'cardinality' => 'singleton',
		'resolver'    => fn () => [],
	] ) )->toThrow( InvalidArgumentException::class );
} );

it( 'rejects an invalid slug', function () {
	expect( fn () => VisualEditor::registerDynamicContentSource( [
		'slug'        => 'Not-Snake-Case',
		'cardinality' => 'singleton',
		'resolver'    => fn () => [],
	] ) )->toThrow( InvalidArgumentException::class );
} );

it( 'rejects an unknown cardinality', function () {
	expect( fn () => VisualEditor::registerDynamicContentSource( [
		'slug'        => 'business',
		'cardinality' => 'sometimes',
		'resolver'    => fn () => [],
	] ) )->toThrow( InvalidArgumentException::class );
} );

it( 'drops duplicate field slugs on a first-wins basis', function () {
	$source = VisualEditor::registerDynamicContentSource( [
		'slug'        => 'business',
		'cardinality' => 'singleton',
		'fields'      => [
			[ 'slug' => 'name',  'label' => 'First Name',     'type' => 'text' ],
			[ 'slug' => 'name',  'label' => 'Duplicate Name', 'type' => 'text' ],
			[ 'slug' => ' name ', 'label' => 'Padded Name',   'type' => 'text' ],
			[ 'slug' => 'phone', 'label' => 'Phone',          'type' => 'phone' ],
		],
		'resolver'    => fn () => [],
	] );

	expect( $source->fields )->toHaveCount( 2 );

	$slugs = array_column( $source->fields, 'slug' );
	expect( $slugs )->toBe( [ 'name', 'phone' ] );

	// The first occurrence's label wins.
	$nameField = collect( $source->fields )->firstWhere( 'slug', 'name' );
	expect( $nameField['label'] )->toBe( 'First Name' );
} );

it( 'supports the pre-1.9 two-argument constructor signature', function () {
	// A host that constructs the class directly (rather than resolving
	// it from the container) must still work without passing a
	// DynamicContentSourceRegistry.
	$editor = new VisualEditorClass(
		app( BlockTypeRegistry::class ),
		app( DynamicBlockRegistry::class ),
	);

	$source = $editor->registerDynamicContentSource( [
		'slug'        => 'business',
		'cardinality' => 'singleton',
		'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
		'resolver'    => fn () => [ 'name' => 'Acme' ],
	] );

	expect( $source )->toBeInstanceOf( HostDynamicContentSource::class );
	expect( $editor->getDynamicContentSourceRegistry() )->toBeInstanceOf( DynamicContentSourceRegistry::class );
	expect( $editor->getDynamicContentSourceRegistry()->has( 'business' ) )->toBeTrue();
} );

it( 'rejects a non-callable resolver', function () {
	expect( fn () => VisualEditor::registerDynamicContentSource( [
		'slug'        => 'business',
		'cardinality' => 'singleton',
		'resolver'    => 'not-a-callable',
	] ) )->toThrow( InvalidArgumentException::class );
} );

it( 'resolves a host singleton token without cms-framework installed', function () {
	VisualEditor::registerDynamicContentSource( [
		'slug'        => 'business',
		'label'       => 'Business',
		'cardinality' => 'singleton',
		'fields'      => [
			[ 'slug' => 'name',  'label' => 'Name',  'type' => 'text' ],
			[ 'slug' => 'phone', 'label' => 'Phone', 'type' => 'phone' ],
			[ 'slug' => 'city',  'label' => 'City',  'type' => 'text' ],
		],
		'resolver'    => fn () => [
			'name'  => 'Acme Roofing',
			'phone' => '5551234567',
			'city'  => 'Sacramento',
		],
	] );

	$source = new DynamicContentSource();
	$ctx    = new BindingContext();

	expect( $source->resolve( $ctx, [ 'token' => 'business.name' ] ) )->toBe( 'Acme Roofing' );
	expect( $source->resolve( $ctx, [ 'token' => 'business.city' ] ) )->toBe( 'Sacramento' );
	expect( $source->resolve( $ctx, [ 'token' => 'business.phone' ] ) )->toBe( '5551234567' );
} );

it( 'applies the tel: scheme to host source values just like cms-framework values', function () {
	VisualEditor::registerDynamicContentSource( [
		'slug'        => 'business',
		'cardinality' => 'singleton',
		'fields'      => [ [ 'slug' => 'phone', 'label' => 'Phone', 'type' => 'phone' ] ],
		'resolver'    => fn () => [ 'phone' => '(555) 123-4567' ],
	] );

	$source = new DynamicContentSource();
	$ctx    = new BindingContext();

	expect( $source->resolve( $ctx, [
		'token'  => 'business.phone',
		'scheme' => 'tel',
	] ) )->toBe( 'tel:5551234567' );
} );

it( 'resolves a host collection with implicit first-record semantics', function () {
	VisualEditor::registerDynamicContentSource( [
		'slug'        => 'team',
		'cardinality' => 'collection',
		'fields'      => [
			[ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ],
			[ 'slug' => 'role', 'label' => 'Role', 'type' => 'text' ],
		],
		'resolver'    => fn () => [
			[ 'name' => 'Alice', 'role' => 'CTO' ],
			[ 'name' => 'Bob',   'role' => 'Ops' ],
		],
	] );

	$source = new DynamicContentSource();
	$ctx    = new BindingContext();

	// Explicit index.
	expect( $source->resolve( $ctx, [ 'token' => 'team[0].name' ] ) )->toBe( 'Alice' );
	expect( $source->resolve( $ctx, [ 'token' => 'team[1].role' ] ) )->toBe( 'Ops' );

	// Implicit first record (matches cms-framework behavior).
	expect( $source->resolve( $ctx, [ 'token' => 'team.name' ] ) )->toBe( 'Alice' );

	// Loop-scope index override.
	$loopCtx = new BindingContext( null, [], [ DynamicContentSource::EXTRAS_INDEX_KEY => [ 'team' => 1 ] ] );
	expect( $source->resolve( $loopCtx, [ 'token' => 'team.name' ] ) )->toBe( 'Bob' );

	// Out-of-range → null.
	expect( $source->resolve( $ctx, [ 'token' => 'team[42].name' ] ) )->toBeNull();
} );

it( 'returns null when a singleton source is accessed with an explicit index', function () {
	VisualEditor::registerDynamicContentSource( [
		'slug'        => 'business',
		'cardinality' => 'singleton',
		'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
		'resolver'    => fn () => [ 'name' => 'Acme' ],
	] );

	$source = new DynamicContentSource();
	$ctx    = new BindingContext();

	// Bare token still works.
	expect( $source->resolve( $ctx, [ 'token' => 'business.name' ] ) )->toBe( 'Acme' );

	// `[N]` against a singleton is a shape mismatch — matches
	// cms-framework's DynamicContentAccessor::collectionItem() returning
	// null for a singleton source.
	expect( $source->resolve( $ctx, [ 'token' => 'business[0].name' ] ) )->toBeNull();
	expect( $source->resolve( $ctx, [ 'token' => 'business[3].name' ] ) )->toBeNull();
} );

it( 'reports and swallows a throwing resolver instead of surfacing the exception', function () {
	VisualEditor::registerDynamicContentSource( [
		'slug'        => 'business',
		'cardinality' => 'singleton',
		'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
		'resolver'    => function () {
			throw new RuntimeException( 'resolver blew up' );
		},
	] );

	$source = new DynamicContentSource();
	$ctx    = new BindingContext();

	// The exception is caught + reported by DynamicContentSource::resolve;
	// the binding falls back to the empty-value policy the same way a
	// missing token does. If the throwable were surfaced here the test
	// would blow up with the RuntimeException.
	expect( $source->resolve( $ctx, [ 'token' => 'business.name' ] ) )->toBeNull();
} );

it( 'invokes the resolver lazily and only when the token is walked', function () {
	$calls = 0;

	VisualEditor::registerDynamicContentSource( [
		'slug'        => 'business',
		'cardinality' => 'singleton',
		'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
		'resolver'    => function () use ( &$calls ) {
			$calls++;

			return [ 'name' => 'Acme' ];
		},
	] );

	$source = new DynamicContentSource();
	$ctx    = new BindingContext();

	expect( $calls )->toBe( 0 );
	$source->resolve( $ctx, [ 'token' => 'business.name' ] );
	expect( $calls )->toBe( 1 );

	// A token for a slug the host registry does not own must not invoke
	// the business resolver at all.
	$source->resolve( $ctx, [ 'token' => 'unrelated.field' ] );
	expect( $calls )->toBe( 1 );
} );

it( 'host source wins over a same-slug cms-framework type', function () {
	VisualEditor::registerDynamicContentSource( [
		'slug'        => 'business_info',
		'label'       => 'Host Business',
		'cardinality' => 'singleton',
		'fields'      => [ [ 'slug' => 'phone', 'label' => 'Phone', 'type' => 'phone' ] ],
		'resolver'    => fn () => [ 'phone' => 'host-value' ],
	] );

	// cms-framework also has business_info — the host one should win.
	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor',
		new FakeDynamicContentAccessor( [
			'business_info' => [ 'phone' => 'cms-value' ],
		] )
	);

	$source = new DynamicContentSource();
	$ctx    = new BindingContext();

	expect( $source->resolve( $ctx, [ 'token' => 'business_info.phone' ] ) )->toBe( 'host-value' );
} );

it( 'falls through to cms-framework for slugs the host registry does not own', function () {
	VisualEditor::registerDynamicContentSource( [
		'slug'        => 'business',
		'cardinality' => 'singleton',
		'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
		'resolver'    => fn () => [ 'name' => 'Host Acme' ],
	] );

	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor',
		new FakeDynamicContentAccessor( [
			'team' => [
				[ 'name' => 'Alice', 'role' => 'CTO' ],
			],
		] )
	);

	$source = new DynamicContentSource();
	$ctx    = new BindingContext();

	expect( $source->resolve( $ctx, [ 'token' => 'business.name' ] ) )->toBe( 'Host Acme' );
	expect( $source->resolve( $ctx, [ 'token' => 'team[0].name' ] ) )->toBe( 'Alice' );
} );

it( 'lists host fields via availableFields() with no cms-framework installed', function () {
	VisualEditor::registerDynamicContentSource( [
		'slug'        => 'business',
		'label'       => 'Business',
		'cardinality' => 'singleton',
		'fields'      => [
			[ 'slug' => 'name',  'label' => 'Name',  'type' => 'text' ],
			[ 'slug' => 'phone', 'label' => 'Phone', 'type' => 'phone' ],
		],
		'resolver'    => fn () => [],
	] );

	$source = new DynamicContentSource();
	$fields = $source->availableFields( '' );

	$keys = collect( $fields )->pluck( 'key' )->all();
	expect( $keys )->toContain( 'business.name', 'business.phone' );

	$nameEntry = collect( $fields )->firstWhere( 'key', 'business.name' );
	expect( $nameEntry['meta']['origin'] )->toBe( 'host' );
	expect( $nameEntry['meta']['source_slug'] )->toBe( 'business' );
} );

it( 'unions host and cms-framework fields, with host winning on collision', function () {
	VisualEditor::registerDynamicContentSource( [
		'slug'        => 'business_info',
		'label'       => 'Host Business',
		'cardinality' => 'singleton',
		'fields'      => [
			[ 'slug' => 'phone', 'label' => 'Phone', 'type' => 'phone' ],
		],
		'resolver'    => fn () => [],
	] );

	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Managers\\DynamicContentTypeRegistry',
		new FakeDynamicContentTypeRegistry( [
			'business_info' => [
				'name'        => 'CMS Business',
				'cardinality' => 'singleton',
				'fields'      => [
					[ 'slug' => 'phone', 'label' => 'CMS Phone', 'type' => 'phone' ],
					[ 'slug' => 'email', 'label' => 'CMS Email', 'type' => 'email' ],
				],
			],
			'team' => [
				'name'        => 'Team',
				'cardinality' => 'collection',
				'fields'      => [
					[ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ],
				],
			],
		] )
	);

	$source = new DynamicContentSource();
	$fields = $source->availableFields( '' );

	$phoneEntries = collect( $fields )->where( 'key', 'business_info.phone' )->values()->all();
	expect( $phoneEntries )->toHaveCount( 1 );
	expect( $phoneEntries[0]['label'] )->toBe( 'Host Business → Phone' );

	// The cms-framework `business_info.email` field must be shadowed by
	// the host registration entirely.
	$keys = collect( $fields )->pluck( 'key' )->all();
	expect( $keys )->not->toContain( 'business_info.email' );
	expect( $keys )->toContain( 'team.name' );
} );

it( 'lists host sources through the /dynamic-content/sources endpoint', function () {
	config()->set( 'artisanpack.visual-editor.api.middleware', [ 'auth' ] );

	$actor = TestUser::create( [
		'name'     => 'Host DC Tester',
		'email'    => 'host+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $actor );

	VisualEditor::registerDynamicContentSource( [
		'slug'        => 'business',
		'label'       => 'Business',
		'cardinality' => 'singleton',
		'description' => 'Host-registered business data',
		'fields'      => [
			[ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ],
		],
		'resolver'    => fn () => [ 'name' => 'Acme' ],
	] );

	$response = $this->getJson( '/visual-editor/api/dynamic-content/sources' );

	$response->assertOk();

	$biz = collect( $response->json( 'sources' ) )->firstWhere( 'slug', 'business' );

	expect( $biz )->not->toBeNull();
	expect( $biz['origin'] )->toBe( 'host' );
	expect( $biz['label'] )->toBe( 'Business' );
	expect( $biz['description'] )->toBe( 'Host-registered business data' );
	expect( $biz['fields'] )->toHaveCount( 1 );
	expect( $biz['fields'][0] )->toBe( [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] );
} );

it( 'merges host and cms-framework sources on the sources endpoint with host winning', function () {
	config()->set( 'artisanpack.visual-editor.api.middleware', [ 'auth' ] );

	$actor = TestUser::create( [
		'name'     => 'Host DC Tester',
		'email'    => 'host+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $actor );

	VisualEditor::registerDynamicContentSource( [
		'slug'        => 'business_info',
		'label'       => 'Host Business',
		'cardinality' => 'singleton',
		'fields'      => [ [ 'slug' => 'phone', 'label' => 'Phone', 'type' => 'phone' ] ],
		'resolver'    => fn () => [],
	] );

	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Managers\\DynamicContentTypeRegistry',
		new FakeDynamicContentTypeRegistry( [
			'business_info' => [
				'name'        => 'CMS Business',
				'cardinality' => 'singleton',
				'source'      => 'db',
				'fields'      => [ [ 'slug' => 'phone', 'label' => 'Phone', 'type' => 'phone' ] ],
			],
			'team' => [
				'name'        => 'Team',
				'cardinality' => 'collection',
				'source'      => 'db',
				'fields'      => [ [ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ] ],
			],
		] )
	);

	$response = $this->getJson( '/visual-editor/api/dynamic-content/sources' );

	$response->assertOk();

	$bySlug = collect( $response->json( 'sources' ) )->keyBy( 'slug' );

	// Host entry wins the business_info slug — only one entry for it.
	expect( $response->json( 'sources' ) )->toHaveCount( 2 );
	expect( $bySlug['business_info']['origin'] )->toBe( 'host' );
	expect( $bySlug['business_info']['label'] )->toBe( 'Host Business' );

	// cms-framework's other types still appear.
	expect( $bySlug->has( 'team' ) )->toBeTrue();
	expect( $bySlug['team']['origin'] )->toBe( 'db' );
} );
