<?php

/**
 * Create visual_editor_template_parts table migration.
 *
 * Storage for the `wp_template_part` entity the B1 core-data shim
 * addresses at `/visual-editor/api/template-parts`. Holds the Gutenberg
 * block tree alongside the raw serialized payload plus the `area` enum
 * and `theme` scoping that pair with `slug` as the composite natural key.
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
		Schema::create( 'visual_editor_template_parts', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'slug' );
			$table->string( 'title' )->default( '' );
			// The API envelope { raw, blocks } is stored verbatim; an empty
			// part is `{ "raw": "", "blocks": [] }` so the column is never
			// null once hydrated through the model.
			$table->json( 'content' )->nullable();
			// Kept as a plain string (not an enum column) so new areas can
			// be added without a schema migration; form-request validation
			// gates the allowed values at the application layer.
			$table->string( 'area' )->default( 'uncategorized' )->index();
			$table->string( 'theme' );
			$table->timestamps();

			// `slug` is only unique within a theme — the same `header`
			// slug can legitimately exist on two themes installed side
			// by side.
			$table->unique( [ 'slug', 'theme' ] );
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'visual_editor_template_parts' );
	}
};
