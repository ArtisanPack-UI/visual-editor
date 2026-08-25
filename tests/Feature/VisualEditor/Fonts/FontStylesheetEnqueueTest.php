<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Models\Font;
use ArtisanPackUI\VisualEditor\Fonts\Models\FontFace;
use ArtisanPackUI\VisualEditor\Fonts\Services\FontsCssGenerator;
use Illuminate\Support\Facades\Storage;
use Tests\TestUser;

beforeEach( function (): void {
	Storage::fake( 'public' );
} );

it( 'leaves the front-end stylesheet list untouched before any bundle exists', function (): void {
	expect( applyFilters( 'ap.themes.frontendStyles', [], 'my-theme' ) )->toBe( [] );
} );

it( 'appends the generated bundle to the front-end theme stylesheet list', function (): void {
	Font::factory()->has( FontFace::factory(), 'faces' )->create();
	app( FontsCssGenerator::class )->generate();

	$entries = applyFilters( 'ap.themes.frontendStyles', [], 'my-theme' );

	expect( $entries )->toHaveKey( 'visual-editor-fonts' )
		->and( $entries['visual-editor-fonts']['src'] )->toContain( 'visual-editor/fonts/fonts.css' )
		->and( $entries['visual-editor-fonts']['ver'] )->toBeInt();
} );

it( 'preserves existing entries when appending the bundle', function (): void {
	Font::factory()->has( FontFace::factory(), 'faces' )->create();
	app( FontsCssGenerator::class )->generate();

	$entries = applyFilters(
		'ap.themes.frontendStyles',
		[ 'theme-main' => [ 'src' => '/themes/x/style.css' ] ],
		'my-theme'
	);

	expect( $entries )->toHaveKey( 'theme-main' )
		->and( $entries )->toHaveKey( 'visual-editor-fonts' );
} );

it( 'enqueues the bundle into the editor canvas stylesheet endpoint', function (): void {
	$user = TestUser::create( [
		'name'     => 'Font canvas tester',
		'email'    => 'font-canvas+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );

	$font = Font::factory()->create( [ 'family' => 'Inter', 'slug' => 'inter' ] );
	FontFace::factory()->for( $font )->create( [ 'weight' => 400, 'style' => 'normal' ] );
	app( FontsCssGenerator::class )->generate();

	$body = $this->get( '/visual-editor/api/global-styles/css' )->assertOk()->getContent();

	expect( $body )
		->toContain( '@font-face' )
		->toContain( 'font-family: "Inter"' );
} );
