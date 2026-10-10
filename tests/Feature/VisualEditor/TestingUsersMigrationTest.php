<?php

/**
 * Testing-only `create_users_table` migration — its static "created by
 * this migration" flag must not leak from one run into the next.
 *
 * @since 1.13.0
 */

declare( strict_types=1 );

use Illuminate\Support\Facades\Schema;

it( 'does not drop a users table it skipped creating, even after an earlier run created one', function () {
	$migration = require __DIR__ . '/../../../database/migrations/testing/2026_04_14_000000_create_users_table.php';

	// Simulate an earlier run in the same process that created the table.
	$flag = new ReflectionProperty( $migration, 'tableCreatedByThisMigration' );
	$flag->setValue( null, true );

	expect( Schema::hasTable( 'users' ) )->toBeTrue();

	// The table already exists, so this run skips the create...
	$migration->up();

	// ...and so must leave the table alone on the way down.
	$migration->down();

	expect( Schema::hasTable( 'users' ) )->toBeTrue()
		->and( $flag->getValue() )->toBeFalse();
} );
