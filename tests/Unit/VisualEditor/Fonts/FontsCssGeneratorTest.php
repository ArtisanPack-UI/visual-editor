<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Models\Font;
use ArtisanPackUI\VisualEditor\Fonts\Models\FontFace;
use ArtisanPackUI\VisualEditor\Fonts\Services\FontsCssGenerator;
use Illuminate\Support\Facades\Storage;

beforeEach( function (): void {
	Storage::fake( 'public' );
} );

it( 'builds @font-face rules and font-family presets for installed fonts', function (): void {
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

	$css = app( FontsCssGenerator::class )->build();

	expect( $css )
		->toContain( '@font-face' )
		->toContain( 'font-family: "Inter"' )
		->toContain( 'font-style: normal' )
		->toContain( 'font-weight: 400' )
		->toContain( 'format("woff2")' )
		->toContain( '--wp--preset--font-family--inter: "Inter", sans-serif' );
} );

it( 'emits truetype format for a ttf face', function (): void {
	$font = Font::factory()->create( [ 'family' => 'Custom', 'slug' => 'custom' ] );
	FontFace::factory()->for( $font )->create( [ 'format' => 'ttf' ] );

	expect( app( FontsCssGenerator::class )->build() )->toContain( 'format("truetype")' );
} );

it( 'emits each self-hosted variable face at its own weight, not the axis range', function (): void {
	$font = Font::factory()->variable()->create( [ 'family' => 'Roboto Flex', 'slug' => 'roboto-flex' ] );

	FontFace::factory()->for( $font )->create( [
		'weight' => 400,
		'style'  => 'normal',
		'axes'   => [ 'wght' => [ 'min' => 100, 'max' => 900, 'default' => 400 ] ],
	] );
	FontFace::factory()->for( $font )->create( [
		'weight' => 700,
		'style'  => 'normal',
		'axes'   => [ 'wght' => [ 'min' => 100, 'max' => 900, 'default' => 400 ] ],
	] );

	$css = app( FontsCssGenerator::class )->build();

	expect( $css )
		->toContain( 'font-weight: 400' )
		->toContain( 'font-weight: 700' )
		->not->toContain( '100 900' );
} );

it( 'neutralizes a family name that tries to break out of the CSS string', function (): void {
	$font = Font::factory()->create( [
		'family' => "Evil\n} body { display: none } @font-face { font-family: \"x</style>",
		'slug'   => 'evil',
	] );
	FontFace::factory()->for( $font )->create( [ 'weight' => 400, 'style' => 'normal' ] );

	$css = app( FontsCssGenerator::class )->build();

	// The hostile text is neutralized by staying inside the quoted string:
	// the newline that would terminate the string token is stripped, the
	// angle brackets that could close the iframe's <style> are stripped, and
	// the embedded quote is escaped — so no breakout sequence survives.
	expect( $css )
		->toContain( 'font-family: "Evil' )
		->not->toContain( '</style>' )
		->not->toContain( "Evil\n" )
		->not->toContain( "\n} body" );
} );

it( 'generates the bundle atomically and leaves no temp file behind', function (): void {
	$font = Font::factory()->create( [ 'family' => 'Inter', 'slug' => 'inter' ] );
	FontFace::factory()->for( $font )->create();

	$generator = app( FontsCssGenerator::class );
	$css       = $generator->generate();

	Storage::disk( 'public' )->assertExists( 'visual-editor/fonts/fonts.css' );

	expect( $generator->read() )->toBe( $css )
		->and( $css )->toContain( '@font-face' )
		->and( $generator->exists() )->toBeTrue();

	$temp = collect( Storage::disk( 'public' )->allFiles( 'visual-editor/fonts' ) )
		->filter( static fn ( string $file ): bool => str_ends_with( $file, '.tmp' ) );

	expect( $temp )->toBeEmpty();
} );

it( 'overwrites the previous bundle on a second generate', function (): void {
	$first = Font::factory()->create( [ 'family' => 'Inter', 'slug' => 'inter' ] );
	FontFace::factory()->for( $first )->create();

	$generator = app( FontsCssGenerator::class );
	$generator->generate();

	$second = Font::factory()->create( [ 'family' => 'Roboto', 'slug' => 'roboto' ] );
	FontFace::factory()->for( $second )->create();

	$generator->generate();

	$css = $generator->read();

	expect( $css )->toContain( 'font-family: "Inter"' )
		->and( $css )->toContain( 'font-family: "Roboto"' );
} );

it( 'degrades to an empty bundle when the disk is unavailable', function (): void {
	$generator = new FontsCssGenerator( 'this-disk-does-not-exist', 'visual-editor/fonts/fonts.css' );

	expect( $generator->read() )->toBe( '' )
		->and( $generator->exists() )->toBeFalse()
		->and( $generator->url() )->toBeNull()
		->and( $generator->version() )->toBeNull();
} );

it( 'exposes no url or version before the bundle is generated', function (): void {
	$generator = app( FontsCssGenerator::class );

	expect( $generator->exists() )->toBeFalse()
		->and( $generator->url() )->toBeNull()
		->and( $generator->version() )->toBeNull();
} );

it( 'exposes a url and version once generated', function (): void {
	Font::factory()->has( FontFace::factory(), 'faces' )->create();

	$generator = app( FontsCssGenerator::class );
	$generator->generate();

	expect( $generator->url() )->toContain( 'visual-editor/fonts/fonts.css' )
		->and( $generator->version() )->toBeInt();
} );
