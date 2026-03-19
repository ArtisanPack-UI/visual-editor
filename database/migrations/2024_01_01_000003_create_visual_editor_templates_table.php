<?php

/**
 * Create visual editor templates table.
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
	 * @return void
	 */
	public function up(): void
	{
		Schema::create( 'visual_editor_templates', function ( Blueprint $table ): void {
			$table->id();
			$table->string( 'name' );
			$table->string( 'slug' )->unique();
			$table->text( 'description' )->nullable();
			$table->string( 'type' )->default( 'page' );
			$table->string( 'for_content_type' )->nullable();
			$table->json( 'content' );
			$table->string( 'status' )->default( 'active' );
			$table->json( 'content_area_settings' )->nullable();
			$table->json( 'styles' )->nullable();
			$table->boolean( 'is_custom' )->default( false );
			$table->boolean( 'is_locked' )->default( false );
			$table->unsignedBigInteger( 'user_id' )->nullable();
			$table->timestamps();
			$table->foreign( 'user_id' )->references( 'id' )->on( 'users' )->nullOnDelete();
			$table->index( [ 'type', 'status' ] );
			$table->index( 'for_content_type' );
		} );
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists( 'visual_editor_templates' );
	}
};
