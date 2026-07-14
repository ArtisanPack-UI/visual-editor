<?php

/**
 * Fixture table for the #619 applied-template endpoint tests. Kept separate
 * from `test_block_content_pages` so the PageResource contract test can
 * continue asserting that `template` stays absent when a resource model
 * doesn't declare it.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create( 'test_applied_template_pages', function ( Blueprint $table ) {
			$table->id();
			$table->unsignedBigInteger( 'author_id' )->nullable()->index();
			$table->string( 'title' )->default( '' );
			$table->string( 'template' )->nullable();
			$table->json( 'content' )->nullable();
			$table->timestamps();
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 'test_applied_template_pages' );
	}
};
