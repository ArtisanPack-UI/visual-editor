<?php

/**
 * StylesExportCommand Feature Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Commands
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\StylePreset;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->exportPath = sys_get_temp_dir() . '/ve-test-export-cmd-' . uniqid() . '.json';
} );

afterEach( function (): void {
	if ( file_exists( $this->exportPath ) ) {
		unlink( $this->exportPath );
	}
} );

test( 've:styles-export writes JSON to default path', function (): void {
	$defaultPath = 'styles-export.json';

	$this->artisan( 've:styles-export' )->assertSuccessful();

	if ( file_exists( $defaultPath ) ) {
		unlink( $defaultPath );
	}
} );

test( 've:styles-export writes JSON to specified output path', function (): void {
	$this->artisan( 've:styles-export', [
		'--output' => $this->exportPath,
		'--name'   => 'CLI Export',
	] )->assertSuccessful();

	expect( file_exists( $this->exportPath ) )->toBeTrue();

	$contents = json_decode( file_get_contents( $this->exportPath ), true );

	expect( $contents )->toHaveKey( 'version' )
		->and( $contents['name'] )->toBe( 'CLI Export' )
		->and( $contents['styles'] )->toHaveKey( 'colors' )
		->and( $contents['styles'] )->toHaveKey( 'typography' )
		->and( $contents['styles'] )->toHaveKey( 'spacing' );
} );

test( 've:styles-export with --only exports specific sections', function (): void {
	$this->artisan( 've:styles-export', [
		'--output' => $this->exportPath,
		'--only'   => 'colors',
	] )->assertSuccessful();

	$contents = json_decode( file_get_contents( $this->exportPath ), true );

	expect( $contents['styles'] )->toHaveKey( 'colors' )
		->and( $contents['styles'] )->not->toHaveKey( 'typography' )
		->and( $contents['styles'] )->not->toHaveKey( 'spacing' );
} );

test( 've:styles-export with invalid --only fails', function (): void {
	$this->artisan( 've:styles-export', [
		'--only' => 'invalid-section',
	] )->assertFailed();
} );

test( 've:styles-export with --preset saves as preset', function (): void {
	$this->artisan( 've:styles-export', [
		'--preset' => 'My CLI Preset',
	] )->assertSuccessful();

	$preset = StylePreset::bySlug( 'my-cli-preset' )->first();

	expect( $preset )->not->toBeNull()
		->and( $preset->name )->toBe( 'My CLI Preset' );
} );
