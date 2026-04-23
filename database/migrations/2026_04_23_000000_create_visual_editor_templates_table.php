<?php

/**
 * Create visual_editor_templates table migration.
 *
 * Storage for the `wp_template` entity the B1 core-data shim addresses at
 * `/visual-editor/api/templates`. Holds the Gutenberg block tree along with
 * the raw serialized payload and the template-hierarchy metadata
 * (`slug`, `theme`, `source`, `origin`) the fallback resolver walks over.
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
		Schema::create( 'visual_editor_templates', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'slug' );
			$table->string( 'title' )->default( '' );
			$table->text( 'description' )->nullable();
			// The API envelope { raw, blocks } is stored verbatim; an empty
			// template is `{ "raw": "", "blocks": [] }` so the column is
			// never null once hydrated through the model.
			$table->json( 'content' )->nullable();
			$table->string( 'status' )->default( 'publish' )->index();
			$table->string( 'theme' );
			$table->string( 'source' )->default( 'custom' );
			$table->string( 'origin' )->nullable();
			$table->timestamps();

			// `slug` is only unique within a theme — the same `single` slug
			// can legitimately exist on two themes installed side by side.
			$table->unique( [ 'slug', 'theme' ] );
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'visual_editor_templates' );
	}
};
