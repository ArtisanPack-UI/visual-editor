<?php

/**
 * Create users table migration (testing only).
 *
 * This migration is for testing purposes only. Consuming applications
 * are expected to provide their own users table.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Whether this migration created the table, so `down()` never drops a
	 * `users` table owned by cms-framework or the host. Mirrors
	 * cms-framework's own `create_users_table` migration.
	 */
	private static bool $tableCreatedByThisMigration = false;

	public function up(): void
	{
		// The flag is static, so clear it first: a previous run in the same
		// process may have set it, and a skipped create must not inherit it.
		self::$tableCreatedByThisMigration = false;

		// cms-framework's own `users` migration may already have run when a
		// test co-loads it via `Tests\Concerns\WithCmsFramework`.
		if ( Schema::hasTable( 'users' ) ) {
			return;
		}

		Schema::create( 'users', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'name' );
			$table->string( 'email' )->unique();
			$table->timestamp( 'email_verified_at' )->nullable();
			$table->string( 'password' );
			$table->rememberToken();
			$table->timestamps();
		} );

		self::$tableCreatedByThisMigration = true;
	}

	public function down(): void
	{
		if ( self::$tableCreatedByThisMigration ) {
			Schema::dropIfExists( 'users' );
		}
	}
};
