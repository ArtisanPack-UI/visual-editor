<?php

/**
 * RouteThrottle tests — per-route rate-limit buckets for the API throttles.
 *
 * @since 1.13.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Support\RouteThrottle;

it( 'builds a prefixed throttle from the configured value', function ( mixed $configured, ?string $expected ) {
	expect( RouteThrottle::middleware( $configured, '60,1', 've-x' ) )->toBe( $expected );
} )->with( [
	'max and decay'    => [ '30,5', 'throttle:30,5,ve-x' ],
	'spaces'           => [ ' 30 , 5 ', 'throttle:30,5,ve-x' ],
	'bare max'         => [ '30', 'throttle:30,1,ve-x' ],
	'integer'          => [ 30, 'throttle:30,1,ve-x' ],
	'own prefix'       => [ '30,5,custom', 'throttle:30,5,custom' ],
	'named limiter'    => [ 'editor-limits', 'throttle:editor-limits' ],
	'empty string'     => [ '', 'throttle:60,1,ve-x' ],
	'whitespace'       => [ '  ', 'throttle:60,1,ve-x' ],
	'null'             => [ null, 'throttle:60,1,ve-x' ],
	'array'            => [ [ 30, 1 ], 'throttle:60,1,ve-x' ],
	'true'             => [ true, 'throttle:60,1,ve-x' ],
	'false (disabled)' => [ false, null ],
] );

it( 'returns a middleware list that is empty when throttling is disabled', function () {
	expect( RouteThrottle::middlewareList( '10,1', '60,1', 've-x' ) )->toBe( [ 'throttle:10,1,ve-x' ] )
		->and( RouteThrottle::middlewareList( false, '60,1', 've-x' ) )->toBe( [] );
} );
