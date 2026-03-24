<?php

/**
 * Style Import/Export Service Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Services
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\GlobalStyle;
use ArtisanPackUI\VisualEditor\Models\StylePreset;
use ArtisanPackUI\VisualEditor\Services\StyleImportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

// --- Export Tests ---

test( 'export returns structured array with all sections by default', function (): void {
	$service = app( StyleImportExportService::class );

	$result = $service->export( 'Test Export' );

	expect( $result )->toHaveKey( 'version' )
		->and( $result['version'] )->toBe( StyleImportExportService::EXPORT_VERSION )
		->and( $result )->toHaveKey( 'name' )
		->and( $result['name'] )->toBe( 'Test Export' )
		->and( $result )->toHaveKey( 'exportedAt' )
		->and( $result )->toHaveKey( 'styles' )
		->and( $result['styles'] )->toHaveKey( 'colors' )
		->and( $result['styles'] )->toHaveKey( 'typography' )
		->and( $result['styles'] )->toHaveKey( 'spacing' );
} );

test( 'export with sections filter only includes specified sections', function (): void {
	$service = app( StyleImportExportService::class );

	$result = $service->export( 'Partial Export', [ 'colors' ] );

	expect( $result['styles'] )->toHaveKey( 'colors' )
		->and( $result['styles'] )->not->toHaveKey( 'typography' )
		->and( $result['styles'] )->not->toHaveKey( 'spacing' );
} );

test( 'exportJson returns valid JSON string', function (): void {
	$service = app( StyleImportExportService::class );

	$json = $service->exportJson( 'JSON Export' );

	$decoded = json_decode( $json, true );

	expect( $decoded )->toBeArray()
		->and( $decoded['name'] )->toBe( 'JSON Export' );
} );

test( 'exportToFile writes JSON to disk', function (): void {
	$service = app( StyleImportExportService::class );
	$path    = sys_get_temp_dir() . '/ve-test-export-' . uniqid() . '.json';

	$result = $service->exportToFile( $path, 'File Export' );

	expect( $result )->toBeTrue()
		->and( file_exists( $path ) )->toBeTrue();

	$contents = json_decode( file_get_contents( $path ), true );

	expect( $contents['name'] )->toBe( 'File Export' );

	unlink( $path );
} );

// --- Validation Tests ---

test( 'validateJson accepts valid import data', function (): void {
	$service = app( StyleImportExportService::class );

	$json = json_encode( [
		'version' => '1.0',
		'name'    => 'Test',
		'styles'  => [
			'colors' => [ [ 'name' => 'Primary', 'slug' => 'primary', 'color' => '#3b82f6' ] ],
		],
	] );

	$result = $service->validateJson( $json );

	expect( $result['valid'] )->toBeTrue()
		->and( $result['errors'] )->toBeEmpty()
		->and( $result['data'] )->not->toBeNull();
} );

test( 'validateJson rejects invalid JSON', function (): void {
	$service = app( StyleImportExportService::class );

	$result = $service->validateJson( '{ invalid json }' );

	expect( $result['valid'] )->toBeFalse()
		->and( $result['errors'] )->not->toBeEmpty()
		->and( $result['data'] )->toBeNull();
} );

test( 'validateJson rejects missing version', function (): void {
	$service = app( StyleImportExportService::class );

	$json = json_encode( [
		'styles' => [ 'colors' => [] ],
	] );

	$result = $service->validateJson( $json );

	expect( $result['valid'] )->toBeFalse();
} );

test( 'validateJson rejects missing styles', function (): void {
	$service = app( StyleImportExportService::class );

	$json = json_encode( [
		'version' => '1.0',
		'name'    => 'Test',
	] );

	$result = $service->validateJson( $json );

	expect( $result['valid'] )->toBeFalse();
} );

test( 'validateJson rejects styles with no sections', function (): void {
	$service = app( StyleImportExportService::class );

	$json = json_encode( [
		'version' => '1.0',
		'styles'  => [],
	] );

	$result = $service->validateJson( $json );

	expect( $result['valid'] )->toBeFalse();
} );

test( 'validateJson rejects non-array colors section', function (): void {
	$service = app( StyleImportExportService::class );

	$json = json_encode( [
		'version' => '1.0',
		'styles'  => [ 'colors' => 'not-an-array' ],
	] );

	$result = $service->validateJson( $json );

	expect( $result['valid'] )->toBeFalse();
} );

test( 'validateJson rejects explicit null section values', function (): void {
	$service = app( StyleImportExportService::class );

	$json = json_encode( [
		'version' => '1.0',
		'styles'  => [ 'colors' => null ],
	] );

	$result = $service->validateJson( $json );

	expect( $result['valid'] )->toBeFalse();
} );

test( 'validateFile returns error for missing file', function (): void {
	$service = app( StyleImportExportService::class );

	$result = $service->validateFile( '/nonexistent/path.json' );

	expect( $result['valid'] )->toBeFalse()
		->and( $result['errors'] )->not->toBeEmpty();
} );

test( 'validateFile validates a valid file', function (): void {
	$service = app( StyleImportExportService::class );
	$path    = sys_get_temp_dir() . '/ve-test-validate-' . uniqid() . '.json';

	file_put_contents( $path, json_encode( [
		'version' => '1.0',
		'name'    => 'Test',
		'styles'  => [
			'colors' => [ [ 'name' => 'Primary', 'slug' => 'primary', 'color' => '#3b82f6' ] ],
		],
	] ) );

	$result = $service->validateFile( $path );

	expect( $result['valid'] )->toBeTrue();

	unlink( $path );
} );

// --- Conflict Detection Tests ---

test( 'detectConflicts returns empty when no conflicts exist', function (): void {
	$service = app( StyleImportExportService::class );

	$exportData = $service->export( 'Current' );

	$conflicts = $service->detectConflicts( $exportData );

	expect( $conflicts )->toBeEmpty();
} );

test( 'detectConflicts respects sections filter', function (): void {
	$service = app( StyleImportExportService::class );

	$importData = [
		'styles' => [
			'colors'     => [ [ 'name' => 'Different', 'slug' => 'different', 'color' => '#ff0000' ] ],
			'typography' => [ 'fontFamilies' => [ 'heading' => 'Impact' ] ],
		],
	];

	$conflicts = $service->detectConflicts( $importData, GlobalStyle::DEFAULT_KEY, [ 'colors' ] );

	expect( $conflicts )->toHaveKey( 'colors' )
		->and( $conflicts )->not->toHaveKey( 'typography' );
} );

test( 'import with no matching sections skips revision', function (): void {
	$service = app( StyleImportExportService::class );

	$importData = [
		'version' => '1.0',
		'styles'  => [
			'colors' => [ [ 'name' => 'Custom', 'slug' => 'custom', 'color' => '#ff0000' ] ],
		],
	];

	$record = $service->import( $importData, [ 'spacing' ] );

	expect( $record )->toBeInstanceOf( GlobalStyle::class );

	$revisionCount = ArtisanPackUI\VisualEditor\Models\Revision::forDocument(
		GlobalStyle::REVISION_DOCUMENT_TYPE,
		$record->id,
	)->count();

	expect( $revisionCount )->toBe( 0 );
} );

test( 'detectConflicts detects changed sections', function (): void {
	$service = app( StyleImportExportService::class );

	$importData = [
		'styles' => [
			'colors' => [ [ 'name' => 'Different', 'slug' => 'different', 'color' => '#ff0000' ] ],
		],
	];

	$conflicts = $service->detectConflicts( $importData );

	expect( $conflicts )->toHaveKey( 'colors' )
		->and( $conflicts['colors'] )->toHaveKey( 'current' )
		->and( $conflicts['colors'] )->toHaveKey( 'imported' );
} );

// --- Import Tests ---

test( 'import applies all sections by default', function (): void {
	$service = app( StyleImportExportService::class );

	$palette = [ [ 'name' => 'Custom', 'slug' => 'custom', 'color' => '#ff0000' ] ];

	$importData = [
		'version' => '1.0',
		'styles'  => [
			'colors' => $palette,
		],
	];

	$record = $service->import( $importData );

	expect( $record )->toBeInstanceOf( GlobalStyle::class )
		->and( $record->palette )->toEqual( $palette );
} );

test( 'import with sections filter only applies specified sections', function (): void {
	$service = app( StyleImportExportService::class );

	$defaultTypography = app( 'visual-editor.typography-presets' )->toStoreFormat();

	$importData = [
		'version' => '1.0',
		'styles'  => [
			'colors'     => [ [ 'name' => 'Custom', 'slug' => 'custom', 'color' => '#ff0000' ] ],
			'typography' => [ 'fontFamilies' => [ 'heading' => 'Arial' ] ],
		],
	];

	$record = $service->import( $importData, [ 'colors' ] );

	expect( $record->palette )->toEqual( $importData['styles']['colors'] )
		->and( $record->typography )->toEqual( $defaultTypography );
} );

test( 'importJson validates and imports', function (): void {
	$service = app( StyleImportExportService::class );

	$json = json_encode( [
		'version' => '1.0',
		'name'    => 'Import Test',
		'styles'  => [
			'colors' => [ [ 'name' => 'Imported', 'slug' => 'imported', 'color' => '#00ff00' ] ],
		],
	] );

	$result = $service->importJson( $json );

	expect( $result['success'] )->toBeTrue()
		->and( $result['record'] )->not->toBeNull()
		->and( $result['record']->palette )->toEqual( [
			[ 'name' => 'Imported', 'slug' => 'imported', 'color' => '#00ff00' ],
		] );
} );

test( 'importJson returns errors for invalid data', function (): void {
	$service = app( StyleImportExportService::class );

	$result = $service->importJson( 'not json' );

	expect( $result['success'] )->toBeFalse()
		->and( $result['errors'] )->not->toBeEmpty()
		->and( $result['record'] )->toBeNull();
} );

test( 'importFromFile validates and imports', function (): void {
	$service = app( StyleImportExportService::class );
	$path    = sys_get_temp_dir() . '/ve-test-import-' . uniqid() . '.json';

	file_put_contents( $path, json_encode( [
		'version' => '1.0',
		'name'    => 'File Import',
		'styles'  => [
			'colors' => [ [ 'name' => 'FromFile', 'slug' => 'from-file', 'color' => '#0000ff' ] ],
		],
	] ) );

	$result = $service->importFromFile( $path );

	expect( $result['success'] )->toBeTrue()
		->and( $result['record']->palette[0]['slug'] )->toBe( 'from-file' );

	unlink( $path );
} );

test( 'importFromFile returns errors for missing file', function (): void {
	$service = app( StyleImportExportService::class );

	$result = $service->importFromFile( '/nonexistent/path.json' );

	expect( $result['success'] )->toBeFalse()
		->and( $result['errors'] )->not->toBeEmpty();
} );

// --- Preset Tests ---

test( 'savePreset creates a new preset from current styles', function (): void {
	$service = app( StyleImportExportService::class );

	$preset = $service->savePreset( 'My Theme' );

	expect( $preset )->toBeInstanceOf( StylePreset::class )
		->and( $preset->name )->toBe( 'My Theme' )
		->and( $preset->slug )->toBe( 'my-theme' )
		->and( $preset->palette )->toBeArray()
		->and( $preset->typography )->toBeArray()
		->and( $preset->spacing )->toBeArray();
} );

test( 'savePreset updates existing preset with same slug', function (): void {
	$service = app( StyleImportExportService::class );

	$first  = $service->savePreset( 'My Theme', 'First description' );
	$second = $service->savePreset( 'My Theme', 'Updated description' );

	expect( $first->id )->toBe( $second->id )
		->and( $second->description )->toBe( 'Updated description' );
} );

test( 'saveImportAsPreset saves import data as preset without applying', function (): void {
	$service = app( StyleImportExportService::class );

	$importData = [
		'version' => '1.0',
		'name'    => 'Imported Theme',
		'styles'  => [
			'colors' => [ [ 'name' => 'Brand', 'slug' => 'brand', 'color' => '#ff6600' ] ],
		],
	];

	$preset = $service->saveImportAsPreset( $importData );

	expect( $preset->name )->toBe( 'Imported Theme' )
		->and( $preset->slug )->toBe( 'imported-theme' )
		->and( $preset->palette )->toEqual( $importData['styles']['colors'] );
} );

test( 'applyPreset applies a saved preset to global styles', function (): void {
	$service = app( StyleImportExportService::class );

	$customPalette = [ [ 'name' => 'Preset Color', 'slug' => 'preset-color', 'color' => '#aabbcc' ] ];

	StylePreset::create( [
		'name'       => 'Test Preset',
		'slug'       => 'test-preset',
		'palette'    => $customPalette,
		'typography' => [ 'fontFamilies' => [ 'heading' => 'Georgia' ] ],
		'spacing'    => [ 'scale' => [ 'md' => [ 'name' => 'Medium', 'slug' => 'md', 'value' => '2rem' ] ] ],
	] );

	$result = $service->applyPreset( 'test-preset' );

	expect( $result )->not->toBeNull()
		->and( $result->palette )->toEqual( $customPalette );
} );

test( 'applyPreset returns null for nonexistent preset', function (): void {
	$service = app( StyleImportExportService::class );

	$result = $service->applyPreset( 'nonexistent' );

	expect( $result )->toBeNull();
} );

test( 'applyPreset with sections filter only applies specified sections', function (): void {
	$service = app( StyleImportExportService::class );

	StylePreset::create( [
		'name'       => 'Partial Preset',
		'slug'       => 'partial-preset',
		'palette'    => [ [ 'name' => 'Partial', 'slug' => 'partial', 'color' => '#112233' ] ],
		'typography' => [ 'fontFamilies' => [ 'heading' => 'Courier' ] ],
		'spacing'    => [ 'scale' => [] ],
	] );

	$result = $service->applyPreset( 'partial-preset', [ 'colors' ] );

	expect( $result )->not->toBeNull()
		->and( $result->palette )->toEqual( [ [ 'name' => 'Partial', 'slug' => 'partial', 'color' => '#112233' ] ] );
} );

test( 'listPresets returns all presets ordered by name', function (): void {
	$service = app( StyleImportExportService::class );

	StylePreset::create( [ 'name' => 'Zebra', 'slug' => 'zebra', 'palette' => [] ] );
	StylePreset::create( [ 'name' => 'Alpha', 'slug' => 'alpha', 'palette' => [] ] );

	$presets = $service->listPresets();

	expect( $presets )->toHaveCount( 2 )
		->and( $presets->first()->name )->toBe( 'Alpha' );
} );

test( 'deletePreset removes preset and returns true', function (): void {
	$service = app( StyleImportExportService::class );

	StylePreset::create( [ 'name' => 'To Delete', 'slug' => 'to-delete', 'palette' => [] ] );

	$result = $service->deletePreset( 'to-delete' );

	expect( $result )->toBeTrue()
		->and( StylePreset::bySlug( 'to-delete' )->first() )->toBeNull();
} );

test( 'deletePreset returns false for nonexistent preset', function (): void {
	$service = app( StyleImportExportService::class );

	$result = $service->deletePreset( 'nonexistent' );

	expect( $result )->toBeFalse();
} );

// --- Round-Trip Test ---

test( 'export then import round-trip preserves data', function (): void {
	$service = app( StyleImportExportService::class );

	$json = $service->exportJson( 'Round Trip' );

	$result = $service->importJson( $json );

	expect( $result['success'] )->toBeTrue();

	$exportAgain = $service->export( 'Round Trip Again' );

	$decoded = json_decode( $json, true );

	expect( $exportAgain['styles'] )->toEqual( $decoded['styles'] );
} );
