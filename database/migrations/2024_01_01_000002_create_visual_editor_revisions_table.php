<?php

/**
 * Migration to create the visual_editor_revisions table.
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
		Schema::create( 'visual_editor_revisions', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'document_type' );
			$table->unsignedBigInteger( 'document_id' );
			$table->json( 'blocks' );
			$table->unsignedBigInteger( 'user_id' )->nullable();
			$table->timestamp( 'created_at' )->nullable();

			$table->index( [ 'document_type', 'document_id' ] );
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
		Schema::dropIfExists( 'visual_editor_revisions' );
	}
};
