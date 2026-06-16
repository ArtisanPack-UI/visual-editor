<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Support\FlexSupport;

/**
 * #595 — flex serializer parity with the shared fixtures.
 *
 * Every fixture in `resources/js/visual-editor/blocks/_shared/
 * flex-controls/fixtures.json` is replayed through `FlexSupport` and
 * the class lists must match byte-exact. Keeps the Blade + JS
 * serializers in lockstep without a build step.
 */

it( 'matches shared fixtures byte-for-byte', function ( string $name, $input, array $expected ) {
	$support = app( FlexSupport::class );
	$result  = $support->serialize( $input );

	$expectedClasses = array_merge( $expected[ 'containerClasses' ], $expected[ 'itemClasses' ] );

	expect( $result[ 'classes' ] )->toEqual( $expectedClasses )
		->and( $result[ 'arbitraryRules' ] )->toEqual( $expected[ 'arbitraryRules' ] );
} )->with( function () {
	$path = __DIR__ . '/../../../../resources/js/visual-editor/blocks/_shared/flex-controls/fixtures.json';
	$json = file_get_contents( $path );
	if ( false === $json ) {
		throw new RuntimeException( "Failed to read fixtures file: {$path}" );
	}
	$decoded = json_decode( $json, true );
	if ( null === $decoded || JSON_ERROR_NONE !== json_last_error() ) {
		throw new RuntimeException( 'Failed to decode fixtures JSON: ' . json_last_error_msg() );
	}
	$fixtures = $decoded[ 'fixtures' ] ?? [];

	$cases = [];
	foreach ( $fixtures as $fixture ) {
		$cases[ $fixture[ 'name' ] ] = [
			$fixture[ 'name' ],
			$fixture[ 'input' ] ?? null,
			$fixture[ 'expected' ],
		];
	}

	return $cases;
} );

it( 'returns empty result for null input', function () {
	$support = app( FlexSupport::class );
	$result  = $support->serialize( null );

	expect( $result[ 'classes' ] )->toEqual( [] )
		->and( $result[ 'arbitraryRules' ] )->toEqual( [] );
} );
