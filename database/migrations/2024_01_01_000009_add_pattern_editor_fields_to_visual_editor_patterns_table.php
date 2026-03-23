<?php

/**
 * Add pattern editor fields to visual_editor_patterns table.
 *
 * Adds description, keywords, status, and is_synced columns to support
 * the pattern editor and synced/standard pattern types.
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

return new class () extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::table( 'visual_editor_patterns', function ( Blueprint $table ): void {
			$table->text( 'description' )->nullable()->after( 'category' );
			$table->string( 'keywords' )->nullable()->after( 'description' );
			$table->string( 'status' )->default( 'active' )->after( 'keywords' );
			$table->boolean( 'is_synced' )->default( false )->after( 'status' );

			$table->index( [ 'category', 'status' ] );
		} );
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::table( 'visual_editor_patterns', function ( Blueprint $table ): void {
			$table->dropIndex( [ 'category', 'status' ] );
			$table->dropColumn( [ 'description', 'keywords', 'status', 'is_synced' ] );
		} );
	}
};
