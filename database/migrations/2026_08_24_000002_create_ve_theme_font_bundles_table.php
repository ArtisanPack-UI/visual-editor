<?php

/**
 * Create ve_theme_font_bundles table migration.
 *
 * Links a theme to a library font, recording which faces the theme
 * depends on so the bundle stays portable and can reinstall missing
 * library entries on activation.
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
		Schema::create( 've_theme_font_bundles', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'theme_slug' );
			$table->foreignId( 'font_id' )->constrained( 've_fonts' )->cascadeOnDelete();
			$table->json( 'faces' )->nullable();
			$table->timestamps();

			$table->unique( [ 'theme_slug', 'font_id' ] );
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 've_theme_font_bundles' );
	}
};
