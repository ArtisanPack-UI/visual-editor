<?php

/**
 * Extend the `ve_font_faces` unique key to include `format`.
 *
 * Before #794 the installer wrote a single face row per weight/style
 * (its container format was recorded but not part of the unique
 * identity). Once providers can supply multiple formats side-by-side
 * for the same weight/style (WOFF2 for the browser, TTF for the OG
 * image generator), the old `(font_id, weight, style)` unique key
 * would collapse the second write onto the first — losing the
 * server-readable variant on every install.
 *
 * The migration widens the unique index to `(font_id, weight, style,
 * format)` so multiple format rows coexist. The old three-column
 * index is dropped first because index names on some drivers collide
 * even when the column list differs; recreating a fresh
 * four-column index keeps SQLite/MySQL/Postgres consistent.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.12.0
 */

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		// Add the four-column unique index *before* dropping the
		// three-column one so MySQL always has an index satisfying
		// the `font_id` foreign-key constraint. Dropping first would
		// error with "needed in a foreign key constraint" on MySQL
		// (SQLite/Postgres don't care about the order, but the
		// portable path is add-then-drop).
		Schema::table( 've_font_faces', function ( Blueprint $table ) {
			$table->unique( [ 'font_id', 'weight', 'style', 'format' ] );
		} );

		Schema::table( 've_font_faces', function ( Blueprint $table ) {
			$table->dropUnique( [ 'font_id', 'weight', 'style' ] );
		} );
	}

	public function down(): void
	{
		// Same order concern in reverse: bring back the three-column
		// unique index first so the FK stays covered, then drop the
		// four-column one.
		Schema::table( 've_font_faces', function ( Blueprint $table ) {
			$table->unique( [ 'font_id', 'weight', 'style' ] );
		} );

		Schema::table( 've_font_faces', function ( Blueprint $table ) {
			$table->dropUnique( [ 'font_id', 'weight', 'style', 'format' ] );
		} );
	}
};
