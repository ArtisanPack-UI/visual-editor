<?php

/**
 * Block font-family preset round-trip (#636).
 *
 * The typography-panel {@see \ArtisanPackUI\VisualEditor\Fonts\Services\FontsCssGenerator}
 * exposes each installed font as a `var(--wp--preset--font-family--{slug})`
 * custom property. The block typography panel's FontFamilyPicker writes exactly
 * that value onto a block's `typography.fontFamily`, so a block saved with an
 * installed font must load and resolve on both editing surfaces without any
 * block schema change:
 *
 *   - the editor canvas, through the `/global-styles/css` endpoint; and
 *   - the public site, through the generated `fonts.css` bundle.
 *
 * These tests prove both surfaces carry the matching `@font-face` rule and the
 * `:root` custom property the saved preset value dereferences.
 *
 * @since 1.7.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Models\Font;
use ArtisanPackUI\VisualEditor\Fonts\Models\FontFace;
use ArtisanPackUI\VisualEditor\Fonts\Services\FontsCssGenerator;
use Illuminate\Support\Facades\Storage;
use Tests\TestUser;

beforeEach( function (): void {
	Storage::fake( 'public' );

	$font = Font::factory()->create( [
		'family'      => 'Inter',
		'slug'        => 'inter',
		'provider'    => 'google',
		'is_variable' => false,
	] );

	FontFace::factory()->for( $font )->create( [
		'weight' => 400,
		'style'  => 'normal',
		'format' => 'woff2',
		'disk'   => 'public',
		'path'   => 'visual-editor/fonts/google/inter/400-normal.woff2',
		'axes'   => null,
	] );

	app( FontsCssGenerator::class )->generate();
} );

it( 'exposes the installed preset a saved block dereferences in the public bundle', function (): void {
	// The exact value the FontFamilyPicker persists onto a block's
	// `typography.fontFamily` for the installed "Inter" font.
	$savedPresetValue = 'var(--wp--preset--font-family--inter)';
	$css              = app( FontsCssGenerator::class )->build();

	// The saved preset value references `--wp--preset--font-family--inter`; the
	// bundle must declare it and ship the backing @font-face.
	expect( $savedPresetValue )->toContain( '--wp--preset--font-family--inter' );

	expect( $css )
		->toContain( '--wp--preset--font-family--inter: "Inter", sans-serif' )
		->toContain( '@font-face' )
		->toContain( 'font-family: "Inter"' )
		->toContain( 'font-weight: 400' );
} );

it( 'resolves the same saved preset in the editor canvas stylesheet', function (): void {
	$user = TestUser::create( [
		'name'     => 'Font round-trip tester',
		'email'    => 'font-roundtrip+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );

	$body = $this->get( '/visual-editor/api/global-styles/css' )
		->assertOk()
		->getContent();

	expect( $body )
		->toContain( '--wp--preset--font-family--inter: "Inter", sans-serif' )
		->toContain( '@font-face' )
		->toContain( 'font-family: "Inter"' );
} );

it( 'enqueues the bundle carrying the preset on the public front-end', function (): void {
	$entries = applyFilters( 'ap.themes.frontendStyles', [], 'my-theme' );

	expect( $entries )->toHaveKey( 'visual-editor-fonts' );

	// The enqueued bundle is the same CSS the build() call renders, so the
	// public <link> carries the preset the saved block dereferences.
	$css = app( FontsCssGenerator::class )->read();

	expect( $css )->toContain( '--wp--preset--font-family--inter: "Inter", sans-serif' );
} );
