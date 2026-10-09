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
	public function up(): void
	{
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
	}

	public function down(): void
	{
		Schema::dropIfExists( 'users' );
	}
};
