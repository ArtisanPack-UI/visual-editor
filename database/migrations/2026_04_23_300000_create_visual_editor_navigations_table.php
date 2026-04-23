<?php

/**
 * Create visual_editor_navigations table migration.
 *
 * Storage for the `wp_navigation` entity the B1 core-data shim addresses at
 * `/visual-editor/api/navigation`. Holds the Gutenberg block tree plus the
 * raw serialized payload for nav menus (primary, footer, etc.) along with
 * the `status` and `menu_order` fields the fallback resolver walks over.
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
		Schema::create( 'visual_editor_navigations', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'slug' )->unique();
			$table->string( 'title' )->default( '' );
			// The API envelope { raw, blocks } is stored verbatim; an empty
			// navigation is `{ "raw": "", "blocks": [] }` so the column is
			// never null once hydrated through the model.
			$table->json( 'content' )->nullable();
			$table->string( 'status' )->default( 'publish' )->index();
			$table->unsignedInteger( 'menu_order' )->default( 0 )->index();
			$table->timestamps();
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'visual_editor_navigations' );
	}
};
