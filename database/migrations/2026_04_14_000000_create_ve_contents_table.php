<?php

/**
 * Create ve_contents table migration.
 *
 * Stores block tree JSON for visual editor posts. Phase 1 minimum viable schema;
 * additional columns (revisions, templates, SEO) land in later phases.
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
		Schema::create( 've_contents', function ( Blueprint $table ) {
			$table->id();
			$table->unsignedBigInteger( 'author_id' )->nullable()->index();
			$table->string( 'title' )->default( '' );
			$table->json( 'blocks' )->nullable();
			$table->timestamps();
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 've_contents' );
	}
};
