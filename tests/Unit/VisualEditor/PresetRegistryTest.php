<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PresetRegistry;

it( 'returns empty append-mode lists when no presets are configured', function () {
	config()->set( 'artisanpack.visual-editor.presets', [] );

	expect( PresetRegistry::fromConfig() )->toBe( [
		'palette'      => [ 'mode' => 'append', 'entries' => [] ],
		'fontSizes'    => [ 'mode' => 'append', 'entries' => [] ],
		'fontFamilies' => [ 'mode' => 'append', 'entries' => [] ],
		'spacingSizes' => [ 'mode' => 'append', 'entries' => [] ],
	] );
} );

it( 'normalises a bare palette entry list as append mode', function () {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		[ 'slug' => 'brand-navy', 'name' => 'Brand Navy', 'color' => '#0a2540' ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette'] )->toBe( [
		'mode'    => 'append',
		'entries' => [
			[ 'slug' => 'brand-navy', 'name' => 'Brand Navy', 'color' => '#0a2540' ],
		],
	] );
} );

it( 'honours the explicit replace mode wrapper', function () {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		'mode'    => 'replace',
		'entries' => [
			[ 'slug' => 'ink', 'name' => 'Ink', 'color' => '#111' ],
		],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette']['mode'] )->toBe( 'replace' )
		->and( $presets['palette']['entries'] )->toBe( [
			[ 'slug' => 'ink', 'name' => 'Ink', 'color' => '#111' ],
		] );
} );

it( 'falls back to append when the mode value is unknown', function () {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		'mode'    => 'merge',
		'entries' => [
			[ 'slug' => 'brand', 'color' => '#000' ],
		],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette']['mode'] )->toBe( 'append' );
} );

it( 'derives a title-cased name from the slug when none is given', function () {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		[ 'slug' => 'brand_navy', 'color' => '#0a2540' ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette']['entries'] )->toBe( [
		[ 'slug' => 'brand_navy', 'name' => 'Brand Navy', 'color' => '#0a2540' ],
	] );
} );

it( 'drops palette entries with an invalid slug', function () {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		[ 'slug' => 'bad slug', 'color' => '#000' ],
		[ 'slug' => 'good', 'color' => '#111' ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette']['entries'] )->toBe( [
		[ 'slug' => 'good', 'name' => 'Good', 'color' => '#111' ],
	] );
} );

it( 'drops palette entries with a suspicious color value', function () {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		[ 'slug' => 'x', 'color' => 'red<script>' ],
		[ 'slug' => 'y', 'color' => 'oklch(0.5 0.1 200)' ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette']['entries'] )->toBe( [
		// The `oklch(...)` entry survives because there is a space
		// inside `oklch(...)`; whitespace also trips the guard so this
		// entry is dropped alongside the `<script>` one. Only the
		// entries that pass the guard land in the result.
	] );
} );

it( 'accepts hex-only colors and non-whitespace CSS values', function () {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		[ 'slug' => 'a', 'color' => '#abc' ],
		[ 'slug' => 'b', 'color' => '#aabbcc' ],
		[ 'slug' => 'c', 'color' => '#aabbccdd' ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( array_column( $presets['palette']['entries'], 'slug' ) )
		->toBe( [ 'a', 'b', 'c' ] );
} );

it( 'deduplicates palette entries whose slug collapses to the same value', function () {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		[ 'slug' => 'Brand', 'color' => '#111' ],
		[ 'slug' => ' brand ', 'color' => '#222' ],
		[ 'slug' => 'other', 'color' => '#333' ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( array_column( $presets['palette']['entries'], 'slug' ) )
		->toBe( [ 'brand', 'other' ] );
} );

it( 'normalises font_sizes, font_families, and spacing_sizes entries', function () {
	config()->set( 'artisanpack.visual-editor.presets', [
		'font_sizes'    => [
			[ 'slug' => 'display', 'name' => 'Display', 'size' => '48px' ],
		],
		'font_families' => [
			[ 'slug' => 'brand', 'name' => 'Brand', 'fontFamily' => 'Inter, sans-serif' ],
		],
		'spacing_sizes' => [
			[ 'slug' => 'gutter', 'name' => 'Gutter', 'size' => '2rem' ],
		],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['fontSizes']['entries'] )->toBe( [
		[ 'slug' => 'display', 'name' => 'Display', 'size' => '48px' ],
	] );
	expect( $presets['fontFamilies']['entries'] )->toBe( [
		[ 'slug' => 'brand', 'name' => 'Brand', 'fontFamily' => 'Inter, sans-serif' ],
	] );
	expect( $presets['spacingSizes']['entries'] )->toBe( [
		[ 'slug' => 'gutter', 'name' => 'Gutter', 'size' => '2rem' ],
	] );
} );

it( 'accepts snake_case font_family alongside camelCase for font families', function () {
	config()->set( 'artisanpack.visual-editor.presets.font_families', [
		[ 'slug' => 'brand', 'font_family' => 'Inter, sans-serif' ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['fontFamilies']['entries'] )->toBe( [
		[ 'slug' => 'brand', 'name' => 'Brand', 'fontFamily' => 'Inter, sans-serif' ],
	] );
} );

it( 'drops entries missing the value key required for their list', function () {
	config()->set( 'artisanpack.visual-editor.presets', [
		'palette'       => [ [ 'slug' => 'a' ] ],
		'font_sizes'    => [ [ 'slug' => 'a' ] ],
		'font_families' => [ [ 'slug' => 'a' ] ],
		'spacing_sizes' => [ [ 'slug' => 'a' ] ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette']['entries'] )->toBe( [] );
	expect( $presets['fontSizes']['entries'] )->toBe( [] );
	expect( $presets['fontFamilies']['entries'] )->toBe( [] );
	expect( $presets['spacingSizes']['entries'] )->toBe( [] );
} );

it( 'ignores non-array entries inside a list', function () {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		'not-an-array',
		[ 'slug' => 'good', 'color' => '#111' ],
		42,
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette']['entries'] )->toBe( [
		[ 'slug' => 'good', 'name' => 'Good', 'color' => '#111' ],
	] );
} );

it( 'returns empty append lists when a preset key is not an array', function () {
	config()->set( 'artisanpack.visual-editor.presets.palette', 'nope' );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette'] )->toBe( [ 'mode' => 'append', 'entries' => [] ] );
} );

it( 'treats a replace wrapper with an empty entries list as a valid replace payload', function () {
	// The PHP layer preserves the mode as-is so the JS merge helper can
	// implement the "empty-replace keeps defaults" fallback — a change to
	// this contract would silently break the JS side.
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		'mode'    => 'replace',
		'entries' => [],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette'] )->toBe( [ 'mode' => 'replace', 'entries' => [] ] );
} );

it( 'returns the whole config record even when only one list is populated', function () {
	config()->set( 'artisanpack.visual-editor.presets', [
		'palette' => [ [ 'slug' => 'x', 'color' => '#111' ] ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( array_keys( $presets ) )
		->toBe( [ 'palette', 'fontSizes', 'fontFamilies', 'spacingSizes' ] );
	expect( $presets['fontSizes'] )->toBe( [ 'mode' => 'append', 'entries' => [] ] );
} );
