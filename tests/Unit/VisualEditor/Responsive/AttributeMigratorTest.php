<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Responsive\AttributeMigrator;

it( 'leaves a scalar untouched when promoting to base', function () {
	$migrator = new AttributeMigrator();

	expect( $migrator->promote( 4, 'base', 5 ) )->toBe( 5 );
} );

it( 'promotes a scalar into the discriminated form on the first non-base override', function () {
	$migrator = new AttributeMigrator();

	expect( $migrator->promote( 4, 'md', 6 ) )->toBe( [
		'base' => 4,
		'md'   => 6,
	] );
} );

it( 'merges another override into an already-discriminated attribute', function () {
	$migrator = new AttributeMigrator();
	$start    = [ 'base' => 4, 'md' => 6 ];

	expect( $migrator->promote( $start, 'lg', 8 ) )->toBe( [
		'base' => 4,
		'md'   => 6,
		'lg'   => 8,
	] );
} );

it( 'demotes back to scalar when every override is cleared', function () {
	$migrator = new AttributeMigrator();
	$attr     = [ 'base' => 4, 'md' => null, 'lg' => null ];

	expect( $migrator->demote( $attr ) )->toBe( 4 );
} );

it( 'leaves the attribute alone when overrides remain', function () {
	$migrator = new AttributeMigrator();
	$attr     = [ 'base' => 4, 'md' => 6 ];

	expect( $migrator->demote( $attr ) )->toBe( $attr );
} );

it( 'clears a specific override and demotes when nothing else remains', function () {
	$migrator = new AttributeMigrator();
	$attr     = [ 'base' => 4, 'md' => 6 ];

	expect( $migrator->clear( $attr, 'md' ) )->toBe( 4 );
} );

it( 'clears a single override out of many and keeps the rest', function () {
	$migrator = new AttributeMigrator();
	$attr     = [ 'base' => 4, 'sm' => 1, 'md' => 6 ];

	expect( $migrator->clear( $attr, 'md' ) )->toBe( [
		'base' => 4,
		'sm'   => 1,
	] );
} );
