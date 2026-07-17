<?php

declare( strict_types=1 );

use Tests\Support\FakeDynamicContentTypeRegistry;
use Tests\TestUser;

beforeEach( function () {
	config()->set( 'artisanpack.visual-editor.api.middleware', [ 'auth' ] );

	$this->actor = TestUser::create( [
		'name'     => 'DC Tester',
		'email'    => 'dc+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $this->actor );
} );

it( 'returns an empty list when cms-framework is unbound', function () {
	// Rebind to a registry with zero types.
	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Managers\\DynamicContentTypeRegistry',
		new FakeDynamicContentTypeRegistry( [] )
	);

	$response = $this->getJson( '/visual-editor/api/dynamic-content/sources' );

	$response->assertOk()->assertJsonPath( 'sources', [] );
} );

it( 'lists sources with their fields', function () {
	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Managers\\DynamicContentTypeRegistry',
		new FakeDynamicContentTypeRegistry( [
			'business_info' => [
				'name'        => 'Business Info',
				'cardinality' => 'singleton',
				'source'      => 'code',
				'fields'      => [
					[ 'slug' => 'phone', 'label' => 'Phone', 'type' => 'phone' ],
					[ 'slug' => 'logo',  'label' => 'Logo',  'type' => 'image' ],
				],
			],
			'team' => [
				'name'        => 'Team',
				'cardinality' => 'collection',
				'source'      => 'db',
				'fields'      => [
					[ 'slug' => 'name', 'label' => 'Name', 'type' => 'text' ],
				],
			],
		] )
	);

	$response = $this->getJson( '/visual-editor/api/dynamic-content/sources' );

	$response->assertOk();

	$slugs = collect( $response->json( 'sources' ) )->pluck( 'slug' )->all();
	expect( $slugs )->toContain( 'business_info', 'team' );

	$biz = collect( $response->json( 'sources' ) )->firstWhere( 'slug', 'business_info' );
	expect( $biz['cardinality'] )->toBe( 'singleton' );
	expect( $biz['origin'] )->toBe( 'code' );
	expect( $biz['fields'] )->toHaveCount( 2 );
} );
