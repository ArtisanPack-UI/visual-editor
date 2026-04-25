<?php

/**
 * Add location column to visual_editor_navigations migration.
 *
 * Adds the menu-location assignment slot D4 writes to from the site editor's
 * Navigation section. The slug names a location declared in
 * `config/artisanpack.visual-editor.navigation.locations` and is the
 * authoritative source the {@see \ArtisanPackUI\VisualEditor\Services\MenuLocationResolver}
 * consults before falling back to the config-driven `primary_id` /
 * first-published lookup chain.
 *
 * The column is nullable + indexed: most navs sit at no location until the
 * editor explicitly assigns one, and the resolver's `forLocation` lookup is
 * an indexed `where`.
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
		Schema::table( 'visual_editor_navigations', function ( Blueprint $table ) {
			$table->string( 'location' )->nullable()->index();
		} );
	}

	public function down(): void
	{
		Schema::table( 'visual_editor_navigations', function ( Blueprint $table ) {
			$table->dropIndex( [ 'location' ] );
			$table->dropColumn( 'location' );
		} );
	}
};
