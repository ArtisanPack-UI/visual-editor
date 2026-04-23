<?php

/**
 * Create visual_editor_pattern_category pivot table migration.
 *
 * Pivot joining `visual_editor_patterns` to `visual_editor_pattern_categories`.
 * A composite primary key on `(pattern_id, pattern_category_id)` keeps each
 * (pattern, category) pair to a single row, and `ON DELETE CASCADE` on both
 * sides keeps the pivot from stranding orphan rows when either side is
 * deleted.
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
		Schema::create( 'visual_editor_pattern_category', function ( Blueprint $table ) {
			$table->foreignId( 'pattern_id' )
				->constrained( 'visual_editor_patterns' )
				->cascadeOnDelete();

			$table->foreignId( 'pattern_category_id' )
				->constrained( 'visual_editor_pattern_categories' )
				->cascadeOnDelete();

			$table->primary( [ 'pattern_id', 'pattern_category_id' ] );
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'visual_editor_pattern_category' );
	}
};
