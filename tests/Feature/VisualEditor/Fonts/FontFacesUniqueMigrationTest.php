<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Models\Font;
use ArtisanPackUI\VisualEditor\Fonts\Models\FontFace;
use Illuminate\Support\Facades\DB;

/**
 * Rollback safety for the `(font_id, weight, style, format)` unique
 * key migration (#794, CodeRabbit review of PR #795).
 *
 * The forward migration lets a slot carry multiple format rows. The
 * `down()` therefore has to collapse those sibling rows before it
 * re-creates the old three-column unique index — otherwise re-adding
 * that index would fail with a uniqueness violation on any install
 * that ever ran a multi-format provider.
 */

it( 'collapses sibling format rows down to one per slot when rolled back', function (): void {
	$font = Font::factory()->create( [ 'family' => 'Inter', 'slug' => 'inter' ] );

	FontFace::factory()->for( $font )->create( [
		'weight' => 400,
		'style'  => 'normal',
		'format' => 'woff2',
		'path'   => 'visual-editor/fonts/google/inter/400-normal.woff2',
	] );
	FontFace::factory()->for( $font )->create( [
		'weight' => 400,
		'style'  => 'normal',
		'format' => 'ttf',
		'path'   => 'visual-editor/fonts/google/inter/400-normal.ttf',
	] );

	$migration = require __DIR__ . '/../../../../database/migrations/2026_09_17_000000_font_faces_unique_include_format.php';

	$migration->down();

	// After rollback: one row per slot, WOFF2 wins the priority tie.
	$survivors = DB::table( 've_font_faces' )
		->where( 'font_id', $font->id )
		->where( 'weight', 400 )
		->where( 'style', 'normal' )
		->get();

	expect( $survivors )->toHaveCount( 1 )
		->and( $survivors->first()->format )->toBe( 'woff2' );

	// The re-created three-column unique index is now enforceable —
	// attempting to insert a second row for the same slot violates it.
	expect( fn (): mixed => FontFace::query()->create( [
		'font_id'   => $font->id,
		'weight'    => 400,
		'style'     => 'normal',
		'format'    => 'ttf',
		'disk'      => 'public',
		'path'      => 'visual-editor/fonts/google/inter/400-normal-alt.ttf',
		'file_size' => 12,
	] ) )->toThrow( \Illuminate\Database\UniqueConstraintViolationException::class );

	// Restore forward state for subsequent tests.
	$migration->up();
} );
