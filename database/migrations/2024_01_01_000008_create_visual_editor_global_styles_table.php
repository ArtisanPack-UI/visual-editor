<?php

/**
 * Migration to create the visual_editor_global_styles table.
 *
 * Stores persisted global style settings (colors, typography, spacing)
 * that override config-based defaults.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Database\Migrations
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::create( 'visual_editor_global_styles', function ( Blueprint $table ): void {
			$table->id();
			$table->string( 'key' )->unique();
			$table->json( 'palette' )->nullable();
			$table->json( 'typography' )->nullable();
			$table->json( 'spacing' )->nullable();
			$table->unsignedBigInteger( 'user_id' )->nullable();
			$table->timestamps();

			$table->foreign( 'user_id' )
				->references( 'id' )
				->on( 'users' )
				->nullOnDelete();
		} );
	}

	/**
	 * Reverse the migrations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists( 'visual_editor_global_styles' );
	}
};
