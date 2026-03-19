<?php

/**
 * Create visual editor template parts table.
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
		Schema::create( 'visual_editor_template_parts', function ( Blueprint $table ): void {
			$table->id();
			$table->string( 'name' );
			$table->string( 'slug' )->unique();
			$table->text( 'description' )->nullable();
			$table->string( 'area' )->default( 'custom' );
			$table->json( 'content' );
			$table->string( 'status' )->default( 'active' );
			$table->boolean( 'is_custom' )->default( false );
			$table->boolean( 'is_locked' )->default( false );
			$table->unsignedBigInteger( 'user_id' )->nullable();
			$table->timestamps();
			$table->foreign( 'user_id' )->references( 'id' )->on( 'users' )->nullOnDelete();
			$table->index( [ 'area', 'status' ] );
		} );
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists( 'visual_editor_template_parts' );
	}
};
