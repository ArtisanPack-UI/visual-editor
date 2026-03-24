<?php

/**
 * Migration to create the visual_editor_style_presets table.
 *
 * Stores named style presets for the import/export preset library,
 * allowing users to save and quickly switch between style configurations.
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
		Schema::create( 'visual_editor_style_presets', function ( Blueprint $table ): void {
			$table->id();
			$table->string( 'name' );
			$table->string( 'slug' )->unique();
			$table->text( 'description' )->nullable();
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
		Schema::dropIfExists( 'visual_editor_style_presets' );
	}
};
