<?php

/**
 * Create visual_editor_patterns table migration.
 *
 * Storage for the `wp_block` entity the B1 core-data shim addresses at
 * `/visual-editor/api/patterns`. Holds the Gutenberg block tree along with
 * the raw serialized payload, plus the `synced` flag that distinguishes
 * reusable (synced) patterns from one-shot (unsynced) inserts. See
 * `docs/core-data-shim.md` §Patterns for the REST record shape and
 * `docs/plans/11-v1-expansion.md` §2.2 for the synced-vs-unsynced model.
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
		Schema::create( 'visual_editor_patterns', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'slug' )->unique();
			$table->string( 'title' )->default( '' );
			// The API envelope { raw, blocks } is stored verbatim; an empty
			// pattern is `{ "raw": "", "blocks": [] }` so the column is
			// never null once hydrated through the model.
			$table->json( 'content' )->nullable();
			// `synced: true` records are referenced by id from every
			// instance; `synced: false` records are copied into the target
			// on insert. The backend stores the flag faithfully — the
			// editor decides reference-vs-copy on insert (D5 / E3).
			$table->boolean( 'synced' )->default( false )->index();
			$table->string( 'status' )->default( 'publish' )->index();
			$table->timestamps();
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'visual_editor_patterns' );
	}
};
