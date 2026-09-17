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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
		// The forward migration lets multi-format providers store one
		// row per `(weight, style, format)`. Rolling back re-creates
		// the older `(weight, style)` unique index, which would fail
		// on any install that has already gained sibling rows for the
		// same slot (WOFF2 + TTF from a Google install, typically).
		// Collapse each slot down to a single row first, preferring
		// the format the pre-#794 release expected on disk (WOFF2 for
		// self-hosted providers, then WOFF > OTF > TTF). The rows we
		// drop leak their files on disk — a downgrade is a developer
		// scenario, not a routine op, and orphan files are strictly
		// safer than deleting a file the surviving row references
		// (CodeRabbit).
		$this->collapseSiblingFormatRows();

		Schema::table( 've_font_faces', function ( Blueprint $table ) {
			$table->unique( [ 'font_id', 'weight', 'style' ] );
		} );

		Schema::table( 've_font_faces', function ( Blueprint $table ) {
			$table->dropUnique( [ 'font_id', 'weight', 'style', 'format' ] );
		} );
	}

	/**
	 * Pick one surviving row per `(font_id, weight, style)` slot and
	 * delete the rest so the re-created three-column unique index has
	 * something to enforce. Preserves the row whose format is
	 * highest-priority for the browser (which was the pre-#794
	 * release's expectation for what a family carried per slot).
	 * Logs any files it leaves behind so an operator can reconcile.
	 */
	private function collapseSiblingFormatRows(): void
	{
		$priority = [
			'woff2' => 4,
			'woff'  => 3,
			'otf'   => 2,
			'ttf'   => 1,
		];

		$rows = DB::table( 've_font_faces' )
			->select( 'id', 'font_id', 'weight', 'style', 'format', 'disk', 'path' )
			->get();

		$bestPerSlot = [];
		foreach ( $rows as $row ) {
			$slot = $row->font_id . ':' . $row->weight . ':' . $row->style;
			$rank = $priority[ strtolower( (string) $row->format ) ] ?? 0;

			if ( ! isset( $bestPerSlot[ $slot ] ) || $rank > $bestPerSlot[ $slot ]['rank'] ) {
				$bestPerSlot[ $slot ] = [ 'id' => $row->id, 'rank' => $rank ];
			}
		}

		$keepIds       = array_map( static fn ( array $entry ): int => (int) $entry['id'], $bestPerSlot );
		$discardedRows = $rows->reject( static fn ( $row ) => in_array( (int) $row->id, $keepIds, true ) );

		if ( $discardedRows->isEmpty() ) {
			return;
		}

		DB::table( 've_font_faces' )
			->whereIn( 'id', $discardedRows->pluck( 'id' )->all() )
			->delete();

		foreach ( $discardedRows as $row ) {
			Log::warning( 'Font-face format row dropped by 2026_09_17_000000 rollback; its file is left on disk.', [
				'font_id' => $row->font_id,
				'weight'  => $row->weight,
				'style'   => $row->style,
				'format'  => $row->format,
				'disk'    => $row->disk,
				'path'    => $row->path,
			] );
		}
	}
};
