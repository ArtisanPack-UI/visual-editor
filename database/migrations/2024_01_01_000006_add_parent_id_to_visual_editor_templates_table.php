<?php

/**
 * Add parent_id column to visual editor templates table.
 *
 * Enables template variations by allowing templates to reference
 * a parent template they inherit from.
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
		Schema::table( 'visual_editor_templates', function ( Blueprint $table ): void {
			$table->unsignedBigInteger( 'parent_id' )->nullable()->after( 'is_locked' );
			$table->foreign( 'parent_id' )->references( 'id' )->on( 'visual_editor_templates' )->nullOnDelete();
			$table->index( 'parent_id' );
		} );
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::table( 'visual_editor_templates', function ( Blueprint $table ): void {
			$table->dropForeign( [ 'parent_id' ] );
			$table->dropIndex( [ 'parent_id' ] );
			$table->dropColumn( 'parent_id' );
		} );
	}
};
