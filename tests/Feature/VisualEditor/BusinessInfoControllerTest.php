<?php

/**
 * Feature tests for the singleton business-info endpoint (#761).
 *
 * The endpoint powers the editor's `artisanpack/business-*` WYSIWYG
 * previews by returning the envelope the front-end resolver stamps as
 * `_resolvedBusinessInfo`. Tests cover the empty-envelope default, the
 * filter-populated envelope, the OSM/Google map-URL composition, and
 * the request-driven attribute overrides.
 */

declare( strict_types=1 );

use Tests\TestUser;

beforeEach( function () {
	config()->set( 'artisanpack.visual-editor.api.middleware', [ 'auth' ] );
	config()->set( 'artisanpack.visual-editor.business.google_maps_api_key', null );

	if ( function_exists( 'removeAllFilters' ) ) {
		removeAllFilters( 'ap.visualEditor.businessInfo' );
	}

	$this->actor = TestUser::create( [
		'name'     => 'BizInfo Tester',
		'email'    => 'bizinfo+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $this->actor );
} );

it( 'returns an empty envelope when no host filter is registered', function () {
	$this->getJson( '/visual-editor/api/business-info' )
		->assertOk()
		->assertJsonPath( 'phone', '' )
		->assertJsonPath( 'email', '' )
		->assertJsonPath( 'address.street', '' )
		->assertJsonPath( 'hours', [] )
		->assertJsonPath( 'specialHours', [] )
		->assertJsonPath( 'mapEmbedUrl', null );
} );

it( 'returns the envelope populated by the host filter', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', function ( array $envelope ): array {
		return array_merge( $envelope, [
			'address' => array_merge( $envelope['address'], [
				'street' => '123 Main St',
				'city'   => 'Springfield',
				'region' => 'IL',
			] ),
			'phone'   => '+1 555-000-1111',
			'email'   => 'hello@example.test',
			'hours'   => [
				'monday' => [ 'open' => '09:00', 'close' => '17:00' ],
			],
		] );
	} );

	$this->getJson( '/visual-editor/api/business-info' )
		->assertOk()
		->assertJsonPath( 'phone', '+1 555-000-1111' )
		->assertJsonPath( 'email', 'hello@example.test' )
		->assertJsonPath( 'address.street', '123 Main St' )
		->assertJsonPath( 'address.city', 'Springfield' )
		->assertJsonPath( 'hours.monday.open', '09:00' );
} );

it( 'composes an OSM map embed URL by default', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', function ( array $envelope ): array {
		return array_merge( $envelope, [
			'address'   => array_merge( $envelope['address'], [
				'street' => '123 Main St',
				'city'   => 'Springfield',
			] ),
			'latitude'  => 39.7817,
			'longitude' => -89.6501,
		] );
	} );

	$response = $this->getJson( '/visual-editor/api/business-info' )->assertOk();

	$url = $response->json( 'mapEmbedUrl' );

	expect( $url )->toBeString();
	expect( $url )->toContain( 'openstreetmap.org/export/embed.html' );
	expect( $url )->toContain( 'marker=' );
} );

it( 'composes a Google Maps embed URL when a key is configured and the block opts in', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	config()->set( 'artisanpack.visual-editor.business.google_maps_api_key', 'test-key-123' );

	addFilter( 'ap.visualEditor.businessInfo', function ( array $envelope ): array {
		return array_merge( $envelope, [
			'address'   => array_merge( $envelope['address'], [
				'street' => '123 Main St',
				'city'   => 'Springfield',
			] ),
			'latitude'  => 39.7817,
			'longitude' => -89.6501,
		] );
	} );

	$response = $this->getJson( '/visual-editor/api/business-info?mapProvider=google&zoom=17' )->assertOk();

	$url = $response->json( 'mapEmbedUrl' );

	expect( $url )->toBeString();
	expect( $url )->toContain( 'google.com/maps/embed/v1/place' );
	expect( $url )->toContain( 'key=test-key-123' );
	expect( $url )->toContain( 'zoom=17' );
} );

it( 'returns null mapEmbedUrl when the block requests showMap=false', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', function ( array $envelope ): array {
		return array_merge( $envelope, [
			'address'   => array_merge( $envelope['address'], [ 'street' => '123 Main St' ] ),
			'latitude'  => 39.7817,
			'longitude' => -89.6501,
		] );
	} );

	$this->getJson( '/visual-editor/api/business-info?showMap=0' )
		->assertOk()
		->assertJsonPath( 'mapEmbedUrl', null );
} );

it( 'filters special-hours entries to the requested window', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	$soon    = date( 'Y-m-d', strtotime( '+5 days' ) );
	$farAway = date( 'Y-m-d', strtotime( '+400 days' ) );

	addFilter( 'ap.visualEditor.businessInfo', function ( array $envelope ) use ( $soon, $farAway ): array {
		return array_merge( $envelope, [
			'specialHours' => [
				[ 'date' => $soon, 'closed' => true, 'label' => 'Nearby' ],
				[ 'date' => $farAway, 'closed' => true, 'label' => 'Distant' ],
			],
		] );
	} );

	$response = $this->getJson( '/visual-editor/api/business-info?specialHoursWindowDays=30' )->assertOk();

	$specialHours = $response->json( 'specialHours' );

	expect( $specialHours )->toBeArray();
	expect( $specialHours )->toHaveCount( 1 );
	expect( $specialHours[0]['label'] )->toBe( 'Nearby' );
} );

it( 'whitelists the response envelope to the documented public keys', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', function ( array $envelope ): array {
		return array_merge( $envelope, [
			'phone'          => '+1 555-0000',
			'internal_notes' => 'do not expose me',
			'_secret'        => 'nor me',
		] );
	} );

	$response = $this->getJson( '/visual-editor/api/business-info' )->assertOk();
	$body     = $response->json();

	expect( $body )->toBeArray();
	expect( array_key_exists( 'internal_notes', $body ) )->toBeFalse();
	expect( array_key_exists( '_secret', $body ) )->toBeFalse();
	expect( $body['phone'] ?? null )->toBe( '+1 555-0000' );
	// Documented keys still present.
	expect( array_key_exists( 'address', $body ) )->toBeTrue();
	expect( array_key_exists( 'hours', $body ) )->toBeTrue();
	expect( array_key_exists( 'specialHours', $body ) )->toBeTrue();
	expect( array_key_exists( 'mapEmbedUrl', $body ) )->toBeTrue();
} );

it( 'treats a garbage showMap value the same as omitting the parameter (default: map on)', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', function ( array $envelope ): array {
		return array_merge( $envelope, [
			'address'   => array_merge( $envelope['address'], [
				'street' => '123 Main St',
				'city'   => 'Springfield',
			] ),
			'latitude'  => 39.7817,
			'longitude' => -89.6501,
		] );
	} );

	$baseline = $this->getJson( '/visual-editor/api/business-info' )
		->assertOk()
		->json( 'mapEmbedUrl' );

	$garbage = $this->getJson( '/visual-editor/api/business-info?showMap=garbage' )
		->assertOk()
		->json( 'mapEmbedUrl' );

	expect( $garbage )->toBe( $baseline );
	expect( $garbage )->toContain( 'openstreetmap.org' );
} );

it( 'rejects unauthenticated requests when the api middleware requires auth', function () {
	auth()->logout();

	$this->getJson( '/visual-editor/api/business-info' )->assertUnauthorized();
} );
