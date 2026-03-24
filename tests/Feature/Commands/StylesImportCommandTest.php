<?php

/**
 * StylesImportCommand Feature Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Commands
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\GlobalStyle;
use ArtisanPackUI\VisualEditor\Models\StylePreset;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->importPath = sys_get_temp_dir() . '/ve-test-import-cmd-' . uniqid() . '.json';
} );

afterEach( function (): void {
	if ( file_exists( $this->importPath ) ) {
		unlink( $this->importPath );
	}
} );

test( 've:styles-import fails without file or preset', function (): void {
	$this->artisan( 've:styles-import' )->assertFailed();
} );

test( 've:styles-import fails for nonexistent file', function (): void {
	$this->artisan( 've:styles-import', [
		'file' => '/nonexistent/file.json',
	] )->assertFailed();
} );

test( 've:styles-import fails for invalid JSON', function (): void {
	file_put_contents( $this->importPath, '{ invalid json }' );

	$this->artisan( 've:styles-import', [
		'file' => $this->importPath,
	] )->assertFailed();
} );

test( 've:styles-import detects conflicts without --force', function (): void {
	// First, create a global style record so there are existing styles.
	$repository = app( GlobalStylesRepository::class );
	$repository->getOrCreate();

	file_put_contents( $this->importPath, json_encode( [
		'version' => '1.0',
		'name'    => 'Conflicting Import',
		'styles'  => [
			'colors' => [ [ 'name' => 'Different', 'slug' => 'different', 'color' => '#ff0000' ] ],
		],
	] ) );

	$this->artisan( 've:styles-import', [
		'file' => $this->importPath,
	] )->assertFailed();
} );

test( 've:styles-import with --force overrides conflicts', function (): void {
	$repository = app( GlobalStylesRepository::class );
	$repository->getOrCreate();

	$importPalette = [ [ 'name' => 'Forced', 'slug' => 'forced', 'color' => '#ff0000' ] ];

	file_put_contents( $this->importPath, json_encode( [
		'version' => '1.0',
		'name'    => 'Forced Import',
		'styles'  => [
			'colors' => $importPalette,
		],
	] ) );

	$this->artisan( 've:styles-import', [
		'file'    => $this->importPath,
		'--force' => true,
	] )->assertSuccessful();

	$record = GlobalStyle::byKey( GlobalStyle::DEFAULT_KEY )->first();

	expect( $record->palette )->toEqual( $importPalette );
} );

test( 've:styles-import with --only imports specific sections', function (): void {
	$defaultTypography = app( 'visual-editor.typography-presets' )->toStoreFormat();

	file_put_contents( $this->importPath, json_encode( [
		'version' => '1.0',
		'name'    => 'Selective Import',
		'styles'  => [
			'colors'     => [ [ 'name' => 'Selected', 'slug' => 'selected', 'color' => '#00ff00' ] ],
			'typography' => [ 'fontFamilies' => [ 'heading' => 'Impact' ] ],
		],
	] ) );

	$this->artisan( 've:styles-import', [
		'file'    => $this->importPath,
		'--only'  => 'colors',
		'--force' => true,
	] )->assertSuccessful();

	$record = GlobalStyle::byKey( GlobalStyle::DEFAULT_KEY )->first();

	expect( $record->palette )->toEqual( [ [ 'name' => 'Selected', 'slug' => 'selected', 'color' => '#00ff00' ] ] )
		->and( $record->typography )->toEqual( $defaultTypography );
} );

test( 've:styles-import with invalid --only fails', function (): void {
	file_put_contents( $this->importPath, json_encode( [
		'version' => '1.0',
		'styles'  => [ 'colors' => [] ],
	] ) );

	$this->artisan( 've:styles-import', [
		'file'   => $this->importPath,
		'--only' => 'invalid',
	] )->assertFailed();
} );

test( 've:styles-import with --save-preset saves without applying', function (): void {
	file_put_contents( $this->importPath, json_encode( [
		'version' => '1.0',
		'name'    => 'Save As Preset',
		'styles'  => [
			'colors' => [ [ 'name' => 'Preset', 'slug' => 'preset', 'color' => '#0000ff' ] ],
		],
	] ) );

	$this->artisan( 've:styles-import', [
		'file'          => $this->importPath,
		'--save-preset' => 'My Saved Preset',
	] )->assertSuccessful();

	$preset = StylePreset::bySlug( 'my-saved-preset' )->first();

	expect( $preset )->not->toBeNull()
		->and( $preset->name )->toBe( 'My Saved Preset' );

	// Global styles should NOT have been modified (preset saved only).
	$record = GlobalStyle::byKey( GlobalStyle::DEFAULT_KEY )->first();

	expect( $record )->toBeNull();
} );

test( 've:styles-import --list-presets shows empty message when none exist', function (): void {
	$this->artisan( 've:styles-import', [
		'--list-presets' => true,
	] )->assertSuccessful();
} );

test( 've:styles-import --list-presets shows saved presets', function (): void {
	StylePreset::create( [
		'name'    => 'Listed Preset',
		'slug'    => 'listed-preset',
		'palette' => [],
	] );

	$this->artisan( 've:styles-import', [
		'--list-presets' => true,
	] )->assertSuccessful();
} );

test( 've:styles-import --preset applies a saved preset', function (): void {
	$presetPalette = [ [ 'name' => 'Preset Applied', 'slug' => 'preset-applied', 'color' => '#aabbcc' ] ];

	StylePreset::create( [
		'name'       => 'Apply Me',
		'slug'       => 'apply-me',
		'palette'    => $presetPalette,
		'typography' => [ 'fontFamilies' => [ 'heading' => 'Verdana' ] ],
		'spacing'    => [ 'scale' => [] ],
	] );

	$this->artisan( 've:styles-import', [
		'--preset' => 'apply-me',
	] )->assertSuccessful();

	$record = GlobalStyle::byKey( GlobalStyle::DEFAULT_KEY )->first();

	expect( $record )->not->toBeNull()
		->and( $record->palette )->toEqual( $presetPalette );
} );

test( 've:styles-import --preset fails for nonexistent preset', function (): void {
	$this->artisan( 've:styles-import', [
		'--preset' => 'nonexistent',
	] )->assertFailed();
} );

test( 've:styles-import --delete-preset removes a preset', function (): void {
	StylePreset::create( [
		'name'    => 'Delete Me',
		'slug'    => 'delete-me',
		'palette' => [],
	] );

	$this->artisan( 've:styles-import', [
		'--delete-preset' => 'delete-me',
	] )->assertSuccessful();

	expect( StylePreset::bySlug( 'delete-me' )->first() )->toBeNull();
} );

test( 've:styles-import --delete-preset fails for nonexistent preset', function (): void {
	$this->artisan( 've:styles-import', [
		'--delete-preset' => 'nonexistent',
	] )->assertFailed();
} );
