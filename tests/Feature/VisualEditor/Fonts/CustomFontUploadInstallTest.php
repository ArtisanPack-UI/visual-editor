<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontFileWriteException;
use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontInstallationException;
use ArtisanPackUI\VisualEditor\Fonts\Models\Font;
use ArtisanPackUI\VisualEditor\Fonts\Models\FontFace;
use ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry;
use ArtisanPackUI\VisualEditor\Fonts\Services\FontFileWriter;
use ArtisanPackUI\VisualEditor\Fonts\Services\FontInstaller;
use ArtisanPackUI\VisualEditor\Fonts\Services\FontsCssGenerator;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\Fonts\FontBinaryFactory;

beforeEach( function (): void {
	Storage::fake( 'public' );
} );

it( 'installs an uploaded variable font, self-hosting the file and recording axes', function (): void {
	$font = app( FontInstaller::class )->installUpload( 'My Variable', [
		[ 'contents' => FontBinaryFactory::variableTtf(), 'weight' => 400, 'style' => 'normal' ],
	] );

	expect( $font->provider )->toBe( 'custom' )
		->and( $font->family )->toBe( 'My Variable' )
		->and( $font->slug )->toBe( 'my-variable' )
		->and( $font->is_variable )->toBeTrue()
		->and( $font->installed_at )->not->toBeNull()
		->and( $font->faces )->toHaveCount( 1 );

	$face = $font->faces->first();

	// The axes column round-trips through JSON, so the axis bound comes back as
	// a numeric value equal to 900 (the `.0` does not survive JSON encoding).
	expect( $face->axes )->toHaveKey( 'wght' )
		->and( (float) $face->axes['wght']['max'] )->toBe( 900.0 )
		->and( $face->format )->toBe( 'ttf' );

	Storage::disk( 'public' )->assertExists( 'visual-editor/fonts/custom/my-variable/400-normal.ttf' );
	Storage::disk( 'public' )->assertExists( 'visual-editor/fonts/fonts.css' );

	expect( app( FontsCssGenerator::class )->read() )->toContain( 'font-family: "My Variable"' );
} );

it( 'installs a static upload with no axes and does not mark the font variable', function (): void {
	$font = app( FontInstaller::class )->installUpload( 'Plain Sans', [
		[ 'contents' => FontBinaryFactory::staticTtf(), 'weight' => 400, 'style' => 'normal' ],
	] );

	expect( $font->is_variable )->toBeFalse()
		->and( $font->faces->first()->axes )->toBeNull();
} );

it( 'defaults weight, style, and format when the upload omits them', function (): void {
	$font = app( FontInstaller::class )->installUpload( 'Defaulted', [
		[ 'contents' => FontBinaryFactory::variableWoff() ],
	] );

	$face = $font->faces->first();

	expect( $face->weight )->toBe( 400 )
		->and( $face->style )->toBe( 'normal' )
		->and( $face->format )->toBe( 'woff' );

	Storage::disk( 'public' )->assertExists( 'visual-editor/fonts/custom/defaulted/400-normal.woff' );
} );

it( 'drops non-font uploads and refuses an install with no valid faces', function (): void {
	expect( fn () => app( FontInstaller::class )->installUpload( 'Bogus', [
		[ 'contents' => 'not a real font file', 'weight' => 400 ],
	] ) )->toThrow( FontInstallationException::class );

	expect( Font::query()->count() )->toBe( 0 );
} );

it( 'refuses to install when a family name produces no usable slug', function (): void {
	expect( fn () => app( FontInstaller::class )->installUpload( '  ', [
		[ 'contents' => FontBinaryFactory::variableTtf() ],
	] ) )->toThrow( FontInstallationException::class );
} );

it( 'refuses uploads when the custom provider is disabled', function (): void {
	config()->set( 'artisanpack.visual-editor.fonts.providers.custom.enabled', false );

	expect( fn () => app( FontInstaller::class )->installUpload( 'My Font', [
		[ 'contents' => FontBinaryFactory::variableTtf() ],
	] ) )->toThrow( FontInstallationException::class );
} );

it( 'merges new faces into an existing uploaded family on re-upload', function (): void {
	$installer = app( FontInstaller::class );

	$installer->installUpload( 'Merged', [
		[ 'contents' => FontBinaryFactory::variableTtf(), 'weight' => 400, 'style' => 'normal' ],
	] );
	$font = $installer->installUpload( 'Merged', [
		[ 'contents' => FontBinaryFactory::variableTtf(), 'weight' => 700, 'style' => 'normal' ],
	] );

	expect( Font::query()->count() )->toBe( 1 )
		->and( $font->faces )->toHaveCount( 2 );
} );

it( 'never downgrades a variable family when a later static face is uploaded', function (): void {
	$installer = app( FontInstaller::class );

	$installer->installUpload( 'Family', [
		[ 'contents' => FontBinaryFactory::variableTtf(), 'weight' => 400, 'style' => 'normal' ],
	] );
	$font = $installer->installUpload( 'Family', [
		[ 'contents' => FontBinaryFactory::staticTtf(), 'weight' => 700, 'style' => 'normal' ],
	] );

	expect( $font->is_variable )->toBeTrue();
} );

it( 'rolls back written files and rows when a later face write fails', function (): void {
	app()->instance( FontFileWriter::class, new class extends FontFileWriter {
		public function write( string $provider, string $slug, int $weight, string $style, string $format, string $contents ): string
		{
			if ( 700 === $weight ) {
				throw new FontFileWriteException( 'Simulated write failure.' );
			}

			return parent::write( $provider, $slug, $weight, $style, $format, $contents );
		}
	} );

	expect( fn () => app( FontInstaller::class )->installUpload( 'Rollback', [
		[ 'contents' => FontBinaryFactory::variableTtf(), 'weight' => 400, 'style' => 'normal' ],
		[ 'contents' => FontBinaryFactory::variableTtf(), 'weight' => 700, 'style' => 'normal' ],
	] ) )->toThrow( FontFileWriteException::class );

	expect( Font::query()->count() )->toBe( 0 )
		->and( FontFace::query()->count() )->toBe( 0 );

	Storage::disk( 'public' )->assertMissing( 'visual-editor/fonts/custom/rollback/400-normal.ttf' );
} );
