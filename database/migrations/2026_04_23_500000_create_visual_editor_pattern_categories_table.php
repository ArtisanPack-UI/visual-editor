<?php

/**
 * Create visual_editor_pattern_categories table migration.
 *
 * Lookup table backing the many-to-many category relationship between
 * patterns (`wp_block`) and their categories. Categories are addressed by
 * slug in the REST payload (`categories: ["featured", "pricing"]`) and
 * auto-created on first use from store / update requests, matching the
 * B2 fixture shape.
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
		Schema::create( 'visual_editor_pattern_categories', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'slug' )->unique();
			$table->string( 'name' )->default( '' );
			$table->timestamps();
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'visual_editor_pattern_categories' );
	}
};
