<?php

/**
 * Creates the fixture tables used by M3 persistence-layer tests (testing only).
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
		Schema::create( 'test_block_content_models', function ( Blueprint $table ) {
			$table->id();
			$table->unsignedBigInteger( 'author_id' )->nullable()->index();
			$table->string( 'title' )->default( '' );
			$table->string( 'slug' )->nullable();
			$table->text( 'excerpt' )->nullable();
			$table->unsignedBigInteger( 'featured_image_id' )->nullable();
			$table->string( 'status' )->default( 'draft' )->index();
			$table->json( 'content' )->nullable();
			$table->timestamps();
		} );

		// Pages fixture is intentionally minimal — its PageResource test
		// asserts that page-only fields (parent / menu_order / template)
		// stay absent from the response when the model doesn't declare
		// them. Don't add those columns here.
		Schema::create( 'test_block_content_pages', function ( Blueprint $table ) {
			$table->id();
			$table->unsignedBigInteger( 'author_id' )->nullable()->index();
			$table->string( 'title' )->default( '' );
			$table->json( 'body' )->nullable();
			$table->timestamps();
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'test_block_content_pages' );
		Schema::dropIfExists( 'test_block_content_models' );
	}
};
