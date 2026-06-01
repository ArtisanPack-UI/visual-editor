<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\States\StateAttributeMigrator;

function makeStateMigrator(): StateAttributeMigrator
{
	return new StateAttributeMigrator();
}

it( 'returns the scalar unchanged when promoting to idle on a scalar', function () {
	expect( makeStateMigrator()->promote( 'red', 'idle', 'blue' ) )->toBe( 'blue' );
} );

it( 'promotes a scalar to a stateful object on first non-idle override', function () {
	$result = makeStateMigrator()->promote( 'red', 'hover', 'blue' );

	expect( $result )->toBe( [ 'idle' => 'red', 'hover' => 'blue' ] );
} );

it( 'merges the new override into an existing stateful object', function () {
	$result = makeStateMigrator()->promote(
		[ 'idle' => 'red', 'hover' => 'blue' ],
		'focus',
		'green',
	);

	expect( $result )->toBe( [ 'idle' => 'red', 'hover' => 'blue', 'focus' => 'green' ] );
} );

it( 'overwrites an existing slot at the same state', function () {
	$result = makeStateMigrator()->promote(
		[ 'idle' => 'red', 'hover' => 'blue' ],
		'hover',
		'orange',
	);

	expect( $result )->toBe( [ 'idle' => 'red', 'hover' => 'orange' ] );
} );

it( 'demotes a stateful object back to scalar when only idle is set', function () {
	$result = makeStateMigrator()->demote( [ 'idle' => 'red', 'hover' => null ] );

	expect( $result )->toBe( 'red' );
} );

it( 'leaves stateful objects with multiple defined slots untouched', function () {
	$attribute = [ 'idle' => 'red', 'hover' => 'blue' ];

	expect( makeStateMigrator()->demote( $attribute ) )->toBe( $attribute );
} );

it( 'clears a single state and demotes if it was the last override', function () {
	$result = makeStateMigrator()->clear(
		[ 'idle' => 'red', 'hover' => 'blue' ],
		'hover',
	);

	expect( $result )->toBe( 'red' );
} );

it( 'clears a state without demoting when other overrides remain', function () {
	$result = makeStateMigrator()->clear(
		[ 'idle' => 'red', 'hover' => 'blue', 'focus' => 'green' ],
		'hover',
	);

	expect( $result )->toBe( [ 'idle' => 'red', 'focus' => 'green' ] );
} );
