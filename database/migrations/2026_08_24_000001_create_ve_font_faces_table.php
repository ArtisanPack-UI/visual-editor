<?php

/**
 * Create ve_font_faces table migration.
 *
 * One row per weight/style variant of an installed font. Each face
 * points at a self-hosted file on the configured disk. Variable fonts
 * store their parsed axis ranges in `axes`.
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
		Schema::create( 've_font_faces', function ( Blueprint $table ) {
			$table->id();
			$table->foreignId( 'font_id' )->constrained( 've_fonts' )->cascadeOnDelete();
			$table->unsignedSmallInteger( 'weight' )->default( 400 );
			$table->string( 'style' )->default( 'normal' );
			$table->string( 'format' )->default( 'woff2' );
			$table->string( 'disk' );
			$table->string( 'path' );
			$table->unsignedBigInteger( 'file_size' )->default( 0 );
			$table->json( 'axes' )->nullable();
			$table->timestamps();

			$table->unique( [ 'font_id', 'weight', 'style' ] );
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 've_font_faces' );
	}
};
