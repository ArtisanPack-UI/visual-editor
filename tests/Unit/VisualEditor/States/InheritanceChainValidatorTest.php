<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\States\InheritanceChainValidator;

it( 'accepts a chain that terminates at idle', function () {
	$validator = new InheritanceChainValidator();

	$validator->assertAcyclic( [
		'idle'  => [ 'inheritsFrom' => null ],
		'hover' => [ 'inheritsFrom' => 'idle' ],
		'focus' => [ 'inheritsFrom' => 'idle' ],
	] );

	expect( true )->toBeTrue(); // No exception thrown.
} );

it( 'rejects a direct cycle', function () {
	( new InheritanceChainValidator() )->assertAcyclic( [
		'idle' => [ 'inheritsFrom' => null ],
		'a'    => [ 'inheritsFrom' => 'b' ],
		'b'    => [ 'inheritsFrom' => 'a' ],
	] );
} )->throws( InvalidArgumentException::class, 'circular' );

it( 'rejects an inheritsFrom that points at an unregistered state', function () {
	( new InheritanceChainValidator() )->assertAcyclic( [
		'idle' => [ 'inheritsFrom' => null ],
		'a'    => [ 'inheritsFrom' => 'missing' ],
	] );
} )->throws( InvalidArgumentException::class, 'inherits' );

it( 'rejects a self-referencing state', function () {
	( new InheritanceChainValidator() )->assertAcyclic( [
		'idle' => [ 'inheritsFrom' => null ],
		'a'    => [ 'inheritsFrom' => 'a' ],
	] );
} )->throws( InvalidArgumentException::class, 'circular' );
