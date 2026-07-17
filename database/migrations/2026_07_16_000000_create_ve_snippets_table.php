<?php

/**
 * Create ve_snippets table migration.
 *
 * Stores reusable block-tree snippets referenced by the
 * `artisanpack/snippet` block. Snippets are first-class VE content —
 * their block tree can include Dynamic Content bindings, other snippet
 * placements (cycle-guarded), and dynamic-loop blocks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create( 've_snippets', function ( Blueprint $table ) {
			$table->id();
			$table->string( 'slug' )->unique();
			$table->string( 'title' )->default( '' );
			$table->json( 'blocks' )->nullable();
			$table->unsignedBigInteger( 'author_id' )->nullable()->index();
			$table->timestamps();
		} );
	}

	public function down(): void
	{
		Schema::dropIfExists( 've_snippets' );
	}
};
