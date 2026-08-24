<?php

/**
 * Create ve_fonts table migration.
 *
 * The Font Library's top-level record: one row per installed font
 * family, keyed by the provider it came from. Weight/style variants
 * live in `ve_font_faces`; theme-scoped selections live in
 * `ve_theme_font_bundles`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create( 've_fonts', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'provider' );
			$table->string( 'family' );
			$table->string( 'slug' );
			$table->boolean( 'is_variable' )->default( false );
			$table->string( 'license' )->nullable();
			$table->string( 'source_url' )->nullable();
			$table->timestamp( 'installed_at' )->nullable();
			$table->timestamps();

			$table->unique( [ 'provider', 'slug' ] );
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 've_fonts' );
	}
};
