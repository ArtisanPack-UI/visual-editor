<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Models\Font;
use ArtisanPackUI\VisualEditor\Fonts\Models\FontFace;
use ArtisanPackUI\VisualEditor\Fonts\Models\ThemeFontBundle;
use Illuminate\Database\QueryException;

it( 'creates a font with faces and exposes the relationship', function () {
	$font = Font::factory()
		->has( FontFace::factory()->count( 3 ), 'faces' )
		->create();

	expect( $font->faces )->toHaveCount( 3 )
		->and( $font->faces->first() )->toBeInstanceOf( FontFace::class )
		->and( $font->faces->first()->font->is( $font ) )->toBeTrue();
} );

it( 'casts font attributes correctly', function () {
	$font = Font::factory()->variable()->create( [ 'installed_at' => now() ] );

	expect( $font->is_variable )->toBeTrue()
		->and( $font->installed_at )->toBeInstanceOf( \Illuminate\Support\Carbon::class );
} );

it( 'casts font face axes to an array', function () {
	$face = FontFace::factory()->variable()->create();

	expect( $face->axes )->toBeArray()
		->and( $face->axes )->toHaveKey( 'wght' )
		->and( $face->weight )->toBeInt()
		->and( $face->file_size )->toBeInt();
} );

it( 'enforces the unique provider/slug constraint on fonts', function () {
	Font::factory()->create( [ 'provider' => 'google', 'slug' => 'inter' ] );

	Font::factory()->create( [ 'provider' => 'google', 'slug' => 'inter' ] );
} )->throws( QueryException::class );

it( 'allows the same slug across different providers', function () {
	Font::factory()->create( [ 'provider' => 'google', 'slug' => 'inter' ] );
	$second = Font::factory()->create( [ 'provider' => 'bunny', 'slug' => 'inter' ] );

	expect( $second->exists )->toBeTrue()
		->and( Font::query()->where( 'slug', 'inter' )->count() )->toBe( 2 );
} );

it( 'cascade deletes faces when the font is deleted', function () {
	$font = Font::factory()
		->has( FontFace::factory()->count( 2 ), 'faces' )
		->create();

	$font->delete();

	expect( FontFace::query()->where( 'font_id', $font->id )->count() )->toBe( 0 );
} );

it( 'links a theme font bundle to its font', function () {
	$font   = Font::factory()->create();
	$bundle = ThemeFontBundle::factory()->create( [ 'font_id' => $font->id ] );

	expect( $bundle->font->is( $font ) )->toBeTrue()
		->and( $bundle->faces )->toBeArray()
		->and( $font->themeBundles )->toHaveCount( 1 );
} );

it( 'cascade deletes theme bundles when the font is deleted', function () {
	$font = Font::factory()
		->has( ThemeFontBundle::factory()->count( 2 ), 'themeBundles' )
		->create();

	$font->delete();

	expect( ThemeFontBundle::query()->where( 'font_id', $font->id )->count() )->toBe( 0 );
} );

it( 'enforces the unique theme/font constraint on bundles', function () {
	$font = Font::factory()->create();

	ThemeFontBundle::factory()->create( [ 'theme_slug' => 'aurora', 'font_id' => $font->id ] );
	ThemeFontBundle::factory()->create( [ 'theme_slug' => 'aurora', 'font_id' => $font->id ] );
} )->throws( QueryException::class );
