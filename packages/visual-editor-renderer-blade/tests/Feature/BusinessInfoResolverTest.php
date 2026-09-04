<?php

declare( strict_types=1 );

/**
 * Feature tests for the business-info resolver + Blade partials (#761).
 *
 * Covers the full server-side contract for the four business-info blocks:
 *
 * - The resolver stamps `_resolvedBusinessInfo` from the host's
 *   `ap.visualEditor.businessInfo` filter onto every business-info block.
 * - Empty envelope defaults render an empty container gracefully.
 * - Hours block honours the special-hours window filter and drops
 *   entries outside it.
 * - Address block composes an OSM embed URL by default and a Google
 *   Maps embed URL when a Maps API key is configured.
 * - Phone and email blocks render tel: / mailto: links from the
 *   envelope.
 * - Pre-stamped `_resolvedBusinessInfo` on the saved tree wins over
 *   the resolver fallback.
 * - The resolver walks nested blocks (inside `core/group`).
 *
 * @since 1.9.0
 */

use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\BusinessInfoResolver;
use Illuminate\Support\Facades\Blade;

function businessBlockNode( string $name, array $attributes = [] ): array
{
	return [
		'clientId'    => sprintf( '%s-cid', $name ),
		'name'        => $name,
		'attributes'  => $attributes,
		'innerBlocks' => [],
	];
}

function renderBusinessTree( array $tree ): string
{
	return Blade::render(
		'<x-ve-blocks :tree="$tree" />',
		[ 'tree' => $tree ]
	);
}

beforeEach( function (): void {
	if ( function_exists( 'removeAllFilters' ) ) {
		removeAllFilters( 'ap.visualEditor.businessInfo' );
	}

	config()->set( 'artisanpack.visual-editor.business.google_maps_api_key', null );
} );

it( 'stamps _resolvedBusinessInfo on every business-info block from the filter', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', function ( array $envelope ): array {
		return array_merge( $envelope, [
			'phone' => '+1 555-000-1111',
			'email' => 'hello@example.test',
		] );
	}, 10 );

	$resolver = $this->app->make( BusinessInfoResolver::class );

	$tree = [
		businessBlockNode( 'artisanpack/business-phone' ),
		businessBlockNode( 'artisanpack/business-email' ),
	];

	$stamped = $resolver->stampTree( $tree, null );

	expect( $stamped[0]['attributes']['_resolvedBusinessInfo']['phone'] )->toBe( '+1 555-000-1111' );
	expect( $stamped[1]['attributes']['_resolvedBusinessInfo']['email'] )->toBe( 'hello@example.test' );
} );

it( 'yields the empty-envelope defaults when no filter is registered', function () {
	$resolver = $this->app->make( BusinessInfoResolver::class );

	$stamped = $resolver->stampTree( [ businessBlockNode( 'artisanpack/business-phone' ) ], null );

	$info = $stamped[0]['attributes']['_resolvedBusinessInfo'];

	expect( $info )->toBeArray();
	expect( $info['phone'] )->toBe( '' );
	expect( $info['email'] )->toBe( '' );
	expect( $info['address'] )->toBeArray();
	expect( $info['hours'] )->toBe( [] );
	expect( $info['specialHours'] )->toBe( [] );
} );

it( 'renders an empty wrapper when the phone envelope is empty (SSR-safe)', function () {
	$rendered = $this->stripGlobalStyles( renderBusinessTree( [ businessBlockNode( 'artisanpack/business-phone' ) ] ) );

	expect( $rendered )->toContain( 'ap-business-phone' );
	expect( $rendered )->not->toContain( 'tel:' );
} );

it( 'renders a tel: link when the phone envelope is populated', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', fn ( array $env ): array => array_merge( $env, [ 'phone' => '+1 (555) 123-4567' ] ), 10 );

	$rendered = $this->stripGlobalStyles( renderBusinessTree( [ businessBlockNode( 'artisanpack/business-phone' ) ] ) );

	expect( $rendered )
		->toContain( 'href="tel:+15551234567"' )
		->toContain( '+1 (555) 123-4567' );
} );

it( 'renders a mailto: link when the email envelope is a valid email', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', fn ( array $env ): array => array_merge( $env, [ 'email' => 'hello@example.test' ] ), 10 );

	$rendered = $this->stripGlobalStyles( renderBusinessTree( [ businessBlockNode( 'artisanpack/business-email' ) ] ) );

	expect( $rendered )
		->toContain( 'href="mailto:hello@example.test"' )
		->toContain( 'hello@example.test' );
} );

it( 'drops the mailto: link when the email is invalid', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', fn ( array $env ): array => array_merge( $env, [ 'email' => 'not-an-email' ] ), 10 );

	$rendered = $this->stripGlobalStyles( renderBusinessTree( [ businessBlockNode( 'artisanpack/business-email' ) ] ) );

	expect( $rendered )->not->toContain( 'mailto:' );
} );

it( 'renders the weekly hours table with day names and open/close ranges', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', fn ( array $env ): array => array_merge( $env, [
		'hours' => [
			'monday' => [ 'open' => '09:00', 'close' => '17:00' ],
			'sunday' => [ 'closed' => true ],
		],
	] ), 10 );

	$rendered = $this->stripGlobalStyles( renderBusinessTree( [ businessBlockNode( 'artisanpack/business-hours' ) ] ) );

	expect( $rendered )
		->toContain( 'Monday' )
		->toContain( '09:00' )
		->toContain( '17:00' )
		->toContain( 'Sunday' )
		->toContain( 'Closed' );
} );

it( 'renders upcoming special-hours overrides within the window', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	$soon = date( 'Y-m-d', strtotime( '+5 days' ) );
	$farFuture = date( 'Y-m-d', strtotime( '+90 days' ) );
	$past = date( 'Y-m-d', strtotime( '-1 day' ) );

	addFilter( 'ap.visualEditor.businessInfo', fn ( array $env ): array => array_merge( $env, [
		'hours'        => [
			'monday' => [ 'open' => '09:00', 'close' => '17:00' ],
		],
		'specialHours' => [
			[ 'date' => $soon,       'label' => 'Soon Holiday',  'closed' => true ],
			[ 'date' => $farFuture,  'label' => 'Far Holiday',   'closed' => true ],
			[ 'date' => $past,       'label' => 'Old Holiday',   'closed' => true ],
		],
	] ), 10 );

	$rendered = $this->stripGlobalStyles( renderBusinessTree( [
		businessBlockNode( 'artisanpack/business-hours', [ 'showSpecialHours' => true, 'specialHoursWindowDays' => 30 ] ),
	] ) );

	expect( $rendered )
		->toContain( 'Soon Holiday' )
		->not->toContain( 'Far Holiday' )
		->not->toContain( 'Old Holiday' );
} );

it( 'renders the address block with an OSM embed URL by default', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', fn ( array $env ): array => array_merge( $env, [
		'address'   => [
			'street'      => '123 Main St',
			'city'        => 'Springfield',
			'region'      => 'IL',
			'postal_code' => '62701',
			'country'     => 'US',
		],
		'latitude'  => 39.7817,
		'longitude' => -89.6501,
	] ), 10 );

	$rendered = $this->stripGlobalStyles( renderBusinessTree( [
		businessBlockNode( 'artisanpack/business-address', [ 'showMap' => true, 'mapProvider' => 'osm' ] ),
	] ) );

	expect( $rendered )
		->toContain( '123 Main St' )
		->toContain( 'Springfield' )
		->toContain( 'openstreetmap.org' )
		->not->toContain( 'google.com/maps' )
		->toContain( 'referrerpolicy="no-referrer"' )
		->not->toContain( 'no-referrer-when-downgrade' )
		->toContain( 'sandbox="allow-scripts allow-same-origin allow-popups"' );
} );

it( 'renders the address block with a Google Maps embed URL when a key is configured', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	config()->set( 'artisanpack.visual-editor.business.google_maps_api_key', 'test-key-abc' );

	addFilter( 'ap.visualEditor.businessInfo', fn ( array $env ): array => array_merge( $env, [
		'address'  => [
			'street'  => '123 Main St',
			'city'    => 'Springfield',
			'country' => 'US',
		],
		'latitude'  => 39.7817,
		'longitude' => -89.6501,
	] ), 10 );

	$rendered = $this->stripGlobalStyles( renderBusinessTree( [
		businessBlockNode( 'artisanpack/business-address', [ 'showMap' => true, 'mapProvider' => 'google' ] ),
	] ) );

	expect( $rendered )
		->toContain( 'google.com/maps/embed/v1/place' )
		->toContain( 'key=test-key-abc' );
} );

it( 'falls back to OSM when mapProvider is google but no API key is configured', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', fn ( array $env ): array => array_merge( $env, [
		'latitude'  => 39.7817,
		'longitude' => -89.6501,
	] ), 10 );

	$rendered = $this->stripGlobalStyles( renderBusinessTree( [
		businessBlockNode( 'artisanpack/business-address', [ 'showMap' => true, 'mapProvider' => 'google' ] ),
	] ) );

	expect( $rendered )
		->toContain( 'openstreetmap.org' )
		->not->toContain( 'google.com/maps' );
} );

it( 'omits the map iframe when showMap is false', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', fn ( array $env ): array => array_merge( $env, [
		'address'   => [ 'street' => '123 Main St' ],
		'latitude'  => 39.7817,
		'longitude' => -89.6501,
	] ), 10 );

	$rendered = $this->stripGlobalStyles( renderBusinessTree( [
		businessBlockNode( 'artisanpack/business-address', [ 'showMap' => false ] ),
	] ) );

	expect( $rendered )
		->toContain( '123 Main St' )
		->not->toContain( '<iframe' );
} );

it( 'passes through a host-supplied mapEmbedUrl verbatim', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', fn ( array $env ): array => array_merge( $env, [
		'address'     => [ 'street' => '123 Main St' ],
		'mapEmbedUrl' => 'https://example.test/my-embed?abc=1',
	] ), 10 );

	$rendered = $this->stripGlobalStyles( renderBusinessTree( [
		businessBlockNode( 'artisanpack/business-address', [ 'showMap' => true, 'mapProvider' => 'osm' ] ),
	] ) );

	expect( $rendered )->toContain( 'https://example.test/my-embed?abc=1' );
} );

it( 'emits no JSON-LD from any business-info block', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', fn ( array $env ): array => array_merge( $env, [
		'phone'   => '+1 555-000-1111',
		'email'   => 'hello@example.test',
		'address' => [ 'street' => '123 Main St' ],
		'hours'   => [ 'monday' => [ 'open' => '09:00', 'close' => '17:00' ] ],
	] ), 10 );

	$rendered = $this->stripGlobalStyles( renderBusinessTree( [
		businessBlockNode( 'artisanpack/business-hours' ),
		businessBlockNode( 'artisanpack/business-address' ),
		businessBlockNode( 'artisanpack/business-phone' ),
		businessBlockNode( 'artisanpack/business-email' ),
	] ) );

	expect( $rendered )
		->not->toContain( 'application/ld+json' )
		->not->toContain( 'schema.org/LocalBusiness' );
} );

it( 'respects a pre-stamped _resolvedBusinessInfo on the saved tree', function () {
	$resolver = $this->app->make( BusinessInfoResolver::class );

	$preStamped = [
		'phone' => '+1 999-999-9999',
		'email' => 'preset@example.test',
	];

	$stamped = $resolver->stampTree(
		[ businessBlockNode( 'artisanpack/business-phone', [ '_resolvedBusinessInfo' => $preStamped ] ) ],
		null
	);

	expect( $stamped[0]['attributes']['_resolvedBusinessInfo'] )->toBe( $preStamped );
} );

it( 'walks nested business-info blocks inside container blocks', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', fn ( array $env ): array => array_merge( $env, [ 'phone' => '+1 555-000-2222' ] ), 10 );

	$resolver = $this->app->make( BusinessInfoResolver::class );

	$tree = [
		[
			'name'        => 'core/group',
			'attributes'  => [],
			'innerBlocks' => [
				businessBlockNode( 'artisanpack/business-phone' ),
			],
		],
	];

	$stamped = $resolver->stampTree( $tree, null );

	$innerBlock = $stamped[0]['innerBlocks'][0];

	expect( $innerBlock['name'] )->toBe( 'artisanpack/business-phone' );
	expect( $innerBlock['attributes']['_resolvedBusinessInfo']['phone'] )->toBe( '+1 555-000-2222' );
} );

it( 'keys the per-call memo by post identity so two posts get two envelopes', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	$calls = 0;

	addFilter( 'ap.visualEditor.businessInfo', function ( array $env, $post ) use ( &$calls ): array {
		$calls++;

		// Vary the envelope per post so a leaked memo would be visible
		// in the stamped attribute.
		$phone = ( is_object( $post ) && isset( $post->phone ) ) ? (string) $post->phone : '+1 default';

		return array_merge( $env, [ 'phone' => $phone ] );
	}, 10 );

	$resolver = $this->app->make( BusinessInfoResolver::class );

	$postOne = (object) [ 'phone' => '+1 111-1111' ];
	$postTwo = (object) [ 'phone' => '+1 222-2222' ];

	$stampedOne = $resolver->stampTree( [ businessBlockNode( 'artisanpack/business-phone' ) ], $postOne );
	// stampTree() resets the memo on each top-level entry, so start
	// a fresh call for the second post here. Verify the memo is keyed
	// per-post inside a single stampTree() call by walking two nested
	// posts through buildEnvelope directly.
	$stampedTwo = $resolver->stampTree( [ businessBlockNode( 'artisanpack/business-phone' ) ], $postTwo );

	expect( $stampedOne[0]['attributes']['_resolvedBusinessInfo']['phone'] )->toBe( '+1 111-1111' );
	expect( $stampedTwo[0]['attributes']['_resolvedBusinessInfo']['phone'] )->toBe( '+1 222-2222' );
	// Two distinct posts must have caused the filter to fire twice.
	expect( $calls )->toBe( 2 );

	// And within one stampTree() call, buildEnvelope() with two
	// different posts should also fire the filter twice.
	$calls = 0;
	$resolver->stampTree( [ businessBlockNode( 'artisanpack/business-phone' ) ], null );
	expect( $resolver->buildEnvelope( $postOne )['phone'] )->toBe( '+1 111-1111' );
	expect( $resolver->buildEnvelope( $postTwo )['phone'] )->toBe( '+1 222-2222' );
	expect( $calls )->toBe( 3 ); // one for the null-post stampTree + two for the two post objects.
} );

it( 'normalizes special-hours on a pre-stamped envelope so malformed dates are dropped', function () {
	$resolver = $this->app->make( BusinessInfoResolver::class );

	$preStamped = [
		'phone'        => '+1 555-0000',
		'specialHours' => [
			[ 'date' => '2019-12-25', 'closed' => true, 'label' => 'Old' ],
			[ 'date' => 'not a date', 'open'   => 'x', 'label' => 'Garbage' ],
			[ 'date' => date( 'Y-m-d', strtotime( '+3 days' ) ), 'closed' => true, 'label' => 'Upcoming' ],
		],
	];

	$stamped = $resolver->stampTree(
		[ businessBlockNode( 'artisanpack/business-hours', [ '_resolvedBusinessInfo' => $preStamped, 'specialHoursWindowDays' => 30 ] ) ],
		null
	);

	$after = $stamped[0]['attributes']['_resolvedBusinessInfo']['specialHours'];

	expect( $after )->toBeArray();
	expect( $after )->toHaveCount( 1 );
	expect( $after[0]['label'] )->toBe( 'Upcoming' );
} );

it( 'drops special-hours entries with calendar-invalid dates (e.g. Feb 31)', function () {
	$resolver = $this->app->make( BusinessInfoResolver::class );

	$preStamped = [
		'specialHours' => [
			[ 'date' => '2026-02-31', 'closed' => true, 'label' => 'Impossible' ],
			[ 'date' => '2026-13-01', 'closed' => true, 'label' => 'BadMonth' ],
			[ 'date' => date( 'Y-m-d', strtotime( '+2 days' ) ), 'closed' => true, 'label' => 'Real' ],
		],
	];

	$stamped = $resolver->stampTree(
		[ businessBlockNode( 'artisanpack/business-hours', [ '_resolvedBusinessInfo' => $preStamped ] ) ],
		null
	);

	$after = $stamped[0]['attributes']['_resolvedBusinessInfo']['specialHours'];

	expect( $after )->toBeArray();
	expect( $after )->toHaveCount( 1 );
	expect( $after[0]['label'] )->toBe( 'Real' );
} );

it( 'renders no map iframe on the OSM branch when only an address (no coordinates) is available', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visualEditor.businessInfo', fn ( array $env ): array => array_merge( $env, [
		'address' => [
			'street' => '123 Main St',
			'city'   => 'Springfield',
		],
		// No latitude/longitude — OSM has no valid iframe target.
	] ), 10 );

	$resolver = $this->app->make( BusinessInfoResolver::class );

	$stamped = $resolver->stampTree( [
		businessBlockNode( 'artisanpack/business-address', [ 'showMap' => true, 'mapProvider' => 'osm' ] ),
	], null );

	expect( $stamped[0]['attributes']['_resolvedBusinessInfo']['mapEmbedUrl'] )->toBeNull();

	$rendered = $this->stripGlobalStyles( renderBusinessTree( [
		businessBlockNode( 'artisanpack/business-address', [ 'showMap' => true, 'mapProvider' => 'osm' ] ),
	] ) );

	expect( $rendered )
		->toContain( '123 Main St' )
		->not->toContain( '<iframe' )
		->not->toContain( 'openstreetmap.org/search' );
} );

it( 'memoizes the filter for the duration of a single stampTree call', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	$calls = 0;

	addFilter( 'ap.visualEditor.businessInfo', function ( array $env ) use ( &$calls ): array {
		$calls++;

		return array_merge( $env, [ 'phone' => '+1 555-000-3333' ] );
	}, 10 );

	$resolver = $this->app->make( BusinessInfoResolver::class );

	$resolver->stampTree( [
		businessBlockNode( 'artisanpack/business-phone' ),
		businessBlockNode( 'artisanpack/business-email' ),
		businessBlockNode( 'artisanpack/business-address' ),
		businessBlockNode( 'artisanpack/business-hours' ),
	], null );

	expect( $calls )->toBe( 1 );
} );
