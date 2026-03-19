<?php

/**
 * Create visual editor template assignments table.
 *
 * Stores content-type-to-template default assignments for the
 * template hierarchy resolution system.
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
		Schema::create( 'visual_editor_template_assignments', function ( Blueprint $table ): void {
			$table->id();
			$table->string( 'content_type' )->unique();
			$table->unsignedBigInteger( 'template_id' );
			$table->unsignedBigInteger( 'user_id' )->nullable();
			$table->timestamps();
			$table->foreign( 'template_id' )->references( 'id' )->on( 'visual_editor_templates' )->cascadeOnDelete();
			$table->foreign( 'user_id' )->references( 'id' )->on( 'users' )->nullOnDelete();
		} );
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists( 'visual_editor_template_assignments' );
	}
};
