<?php

/**
 * Create visual_editor_global_styles table migration.
 *
 * Storage for the `globalStyles` singleton the B1 core-data shim
 * addresses at `/visual-editor/api/global-styles`. The record is a
 * theme.json-shaped blob — `version`, `settings`, `styles` — scoped by
 * `theme` so a host app swapping themes does not bleed one site's
 * customizations into another. See `docs/global-styles.md` for the
 * pinned schema version and `docs/core-data-shim.md` §Global styles for
 * the API contract.
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
		Schema::create( 'visual_editor_global_styles', function ( Blueprint $table ) {
			$table->id();
			// Pinned theme.json schema version — a data value, not a code
			// constant. The supported version is documented in
			// `docs/global-styles.md` and enforced by the update
			// form-request against the config value.
			$table->unsignedSmallInteger( 'version' )->default( 3 );
			// `settings` holds palettes, typography presets, layout sizes.
			$table->json( 'settings' );
			// `styles` holds resolved CSS values per element / per block.
			$table->json( 'styles' );
			// Multi-theme hook: each installed theme gets its own
			// singleton. Uniqueness on `theme` enforces the singleton
			// invariant at the DB level.
			$table->string( 'theme' )->unique();
			$table->timestamps();
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'visual_editor_global_styles' );
	}
};
