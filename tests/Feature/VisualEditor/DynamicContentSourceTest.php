<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\DynamicContentSource;
use Tests\Support\FakeDynamicContentAccessor;
use Tests\Support\FakeDynamicContentTypeRegistry;

beforeEach( function () {
	$this->source = new DynamicContentSource();

	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor',
		new FakeDynamicContentAccessor( [
			'business_info' => [
				'phone' => '(555) 123-4567',
				'email' => 'hi@example.com',
				'logo'  => 42,
			],
			'team' => [
				[ 'name' => 'Alice', 'role' => 'CTO' ],
				[ 'name' => 'Bob',   'role' => 'Ops' ],
			],
		] )
	);
} );

it( 'reports its canonical name', function () {
	expect( $this->source->name() )->toBe( 'dynamic_content' );
} );

it( 'returns null for an empty or missing token arg', function () {
	$ctx = new BindingContext();

	expect( $this->source->resolve( $ctx, [] ) )->toBeNull();
	expect( $this->source->resolve( $ctx, [ 'token' => '' ] ) )->toBeNull();
} );

it( 'resolves a singleton field token', function () {
	$ctx = new BindingContext();

	expect( $this->source->resolve( $ctx, [ 'token' => 'business_info.phone' ] ) )->toBe( '(555) 123-4567' );
	expect( $this->source->resolve( $ctx, [ 'token' => 'business_info.logo' ] ) )->toBe( 42 );
} );

it( 'resolves an explicit collection index', function () {
	$ctx = new BindingContext();

	expect( $this->source->resolve( $ctx, [ 'token' => 'team[0].name' ] ) )->toBe( 'Alice' );
	expect( $this->source->resolve( $ctx, [ 'token' => 'team[1].role' ] ) )->toBe( 'Ops' );
} );

it( 'returns null for a missing token', function () {
	$ctx = new BindingContext();

	expect( $this->source->resolve( $ctx, [ 'token' => 'business_info.nope' ] ) )->toBeNull();
	expect( $this->source->resolve( $ctx, [ 'token' => 'unknown.source' ] ) )->toBeNull();
	expect( $this->source->resolve( $ctx, [ 'token' => 'team[42].name' ] ) )->toBeNull();
} );

it( 'applies the loop-index scope from extras', function () {
	$ctx = new BindingContext( null, [], [ DynamicContentSource::EXTRAS_INDEX_KEY => [ 'team' => 1 ] ] );

	// Bare `team.name` should resolve as team[1].name because the loop
	// scope pushed index 1 for the `team` source.
	expect( $this->source->resolve( $ctx, [ 'token' => 'team.name' ] ) )->toBe( 'Bob' );
} );

it( 'ignores the loop-index scope when the token has an explicit index', function () {
	$ctx = new BindingContext( null, [], [ DynamicContentSource::EXTRAS_INDEX_KEY => [ 'team' => 1 ] ] );

	// Explicit `team[0].name` wins over the loop scope's index=1.
	expect( $this->source->resolve( $ctx, [ 'token' => 'team[0].name' ] ) )->toBe( 'Alice' );
} );

it( 'applies the mailto: scheme when args.scheme is email', function () {
	$ctx = new BindingContext();

	expect( $this->source->resolve( $ctx, [
		'token'  => 'business_info.email',
		'scheme' => 'mailto',
	] ) )->toBe( 'mailto:hi@example.com' );
} );

it( 'applies the tel: scheme when args.scheme is tel and strips formatting', function () {
	$ctx = new BindingContext();

	expect( $this->source->resolve( $ctx, [
		'token'  => 'business_info.phone',
		'scheme' => 'tel',
	] ) )->toBe( 'tel:5551234567' );
} );

it( 'does not double-prefix an already-schemed value', function () {
	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor',
		new FakeDynamicContentAccessor( [
			'business_info' => [ 'email' => 'mailto:already@example.com' ],
		] )
	);

	$ctx = new BindingContext();

	expect( $this->source->resolve( $ctx, [
		'token'  => 'business_info.email',
		'scheme' => 'mailto',
	] ) )->toBe( 'mailto:already@example.com' );
} );

it( 'nulls out unsafe URL schemes so javascript:/data: values never leak to href', function () {
	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor',
		new FakeDynamicContentAccessor( [
			'business_info' => [
				'evil'      => 'javascript:alert(document.cookie)',
				'evil_data' => 'data:text/html,<script>alert(1)</script>',
				'evil_file' => 'file:///etc/passwd',
			],
		] )
	);

	$ctx = new BindingContext();

	// URL scheme: value is already schemed and the scheme is unsafe →
	// resolver returns null, letting the empty-value policy kick in.
	expect( $this->source->resolve( $ctx, [ 'token' => 'business_info.evil' ] ) )->toBeNull();
	expect( $this->source->resolve( $ctx, [ 'token' => 'business_info.evil_data' ] ) )->toBeNull();
	expect( $this->source->resolve( $ctx, [ 'token' => 'business_info.evil_file' ] ) )->toBeNull();

	// Even when the binding declares scheme:'mailto', an unsafe existing
	// scheme takes precedence — we still return null rather than
	// prefixing "mailto:javascript:…".
	expect( $this->source->resolve( $ctx, [
		'token'  => 'business_info.evil',
		'scheme' => 'mailto',
	] ) )->toBeNull();
} );

it( 'returns null for an unschemed value bound with an unrecognized scheme', function () {
	$ctx = new BindingContext();

	// scheme='url' isn't one of the concrete prefixers — the value has
	// no scheme of its own, so we return null rather than shipping
	// whatever the field held into an href.
	expect( $this->source->resolve( $ctx, [
		'token'  => 'business_info.phone',
		'scheme' => 'url',
	] ) )->toBeNull();
} );

it( 'enumerates fields from the registered types', function () {
	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Managers\\DynamicContentTypeRegistry',
		new FakeDynamicContentTypeRegistry( [
			'business_info' => [
				'name'        => 'Business Info',
				'cardinality' => 'singleton',
				'fields'      => [
					[ 'slug' => 'phone', 'label' => 'Phone', 'type' => 'phone' ],
					[ 'slug' => 'email', 'label' => 'Email', 'type' => 'email' ],
				],
			],
			'team' => [
				'name'        => 'Team',
				'cardinality' => 'collection',
				'fields'      => [
					[ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ],
					[ 'slug' => 'role', 'label' => 'Role', 'type' => 'text' ],
				],
			],
		] )
	);

	$fields = $this->source->availableFields( '' );

	$keys = collect( $fields )->pluck( 'key' )->all();

	expect( $keys )->toContain( 'business_info.phone', 'business_info.email', 'team.name', 'team.role' );

	$phoneEntry = collect( $fields )->firstWhere( 'key', 'business_info.phone' );
	expect( $phoneEntry['meta']['source_slug'] )->toBe( 'business_info' );
	expect( $phoneEntry['meta']['cardinality'] )->toBe( 'singleton' );
} );
