<?php

/**
 * Global Styles Repository Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Services
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\GlobalStyle;
use ArtisanPackUI\VisualEditor\Models\Revision;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

test( 'get returns null when no record exists', function (): void {
	$repository = app( GlobalStylesRepository::class );

	expect( $repository->get() )->toBeNull();
} );

test( 'getOrCreate creates a record with defaults when none exists', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$record = $repository->getOrCreate();

	expect( $record )->toBeInstanceOf( GlobalStyle::class );
	expect( $record->key )->toBe( GlobalStyle::DEFAULT_KEY );
	expect( $record->palette )->toBeArray();
	expect( $record->palette )->not->toBeEmpty();
	expect( $record->typography )->toBeArray();
	expect( $record->spacing )->toBeArray();
} );

test( 'getOrCreate returns existing record', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$first  = $repository->getOrCreate();
	$second = $repository->getOrCreate();

	expect( $first->id )->toBe( $second->id );
} );

test( 'save persists style data and creates a revision', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$newPalette = [ [ 'name' => 'Custom', 'slug' => 'custom', 'color' => '#ff0000' ] ];

	$record = $repository->save( [
		'palette' => $newPalette,
	] );

	expect( $record->palette )->toEqual( $newPalette );

	$revisionCount = Revision::forDocument( GlobalStyle::REVISION_DOCUMENT_TYPE, $record->id )->count();

	expect( $revisionCount )->toBe( 1 );
} );

test( 'save only updates provided keys', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$initial            = $repository->getOrCreate();
	$originalTypography = $initial->typography;

	$repository->save( [
		'palette' => [ [ 'name' => 'Changed', 'slug' => 'changed', 'color' => '#111111' ] ],
	] );

	$updated = $repository->get();

	expect( $updated->palette[0]['slug'] )->toBe( 'changed' );
	expect( $updated->typography )->toEqual( $originalTypography );
} );

test( 'save stores user_id when provided', function (): void {
	$userId = Illuminate\Support\Facades\DB::table( 'users' )->insertGetId( [
		'name'       => 'Style User',
		'email'      => 'style-user@example.com',
		'created_at' => now(),
		'updated_at' => now(),
	] );

	$repository = app( GlobalStylesRepository::class );

	$record = $repository->save( [ 'palette' => [] ], $userId );

	expect( $record->user_id )->toBe( $userId );
} );

test( 'resetToDefaults restores config defaults and creates revision', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$repository->save( [
		'palette' => [ [ 'name' => 'Custom', 'slug' => 'custom', 'color' => '#ff0000' ] ],
	] );

	$record = $repository->resetToDefaults();

	$defaultPalette = app( 'visual-editor.color-palette' )->toStoreFormat();

	expect( $record->palette )->toEqual( $defaultPalette );

	$revisionCount = Revision::forDocument( GlobalStyle::REVISION_DOCUMENT_TYPE, $record->id )->count();

	expect( $revisionCount )->toBe( 2 );
} );

test( 'restoreRevision reverts to a previous state', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$original = [ [ 'name' => 'Original', 'slug' => 'original', 'color' => '#aaaaaa' ] ];

	$repository->save( [ 'palette' => $original ] );

	$record        = $repository->get();
	$revisions     = $repository->getRevisions();
	$firstRevision = $revisions->first();

	$repository->save( [ 'palette' => [ [ 'name' => 'Changed', 'slug' => 'changed', 'color' => '#bbbbbb' ] ] ] );

	$restored = $repository->restoreRevision( $firstRevision->id );

	expect( $restored )->not->toBeNull();
} );

test( 'restoreRevision returns null for invalid revision id', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$result = $repository->restoreRevision( 99999 );

	expect( $result )->toBeNull();
} );

test( 'getRevisions returns empty collection when no record exists', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$revisions = $repository->getRevisions();

	expect( $revisions )->toBeEmpty();
} );

test( 'getRevisions respects limit parameter', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$repository->save( [ 'palette' => [] ] );
	$repository->save( [ 'palette' => [] ] );
	$repository->save( [ 'palette' => [] ] );

	$revisions = $repository->getRevisions( 2 );

	expect( $revisions )->toHaveCount( 2 );
} );

test( 'getPalette returns config defaults when no record exists', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$palette = $repository->getPalette();

	$expected = app( 'visual-editor.color-palette' )->toStoreFormat();

	expect( $palette )->toEqual( $expected );
} );

test( 'getPalette returns database value when record exists', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$custom = [ [ 'name' => 'Custom', 'slug' => 'custom', 'color' => '#ff0000' ] ];
	$repository->save( [ 'palette' => $custom ] );

	$palette = $repository->getPalette();

	expect( $palette )->toEqual( $custom );
} );

test( 'getTypography returns config defaults when no record exists', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$typography = $repository->getTypography();

	$expected = app( 'visual-editor.typography-presets' )->toStoreFormat();

	expect( $typography )->toEqual( $expected );
} );

test( 'getSpacing returns config defaults when no record exists', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$spacing = $repository->getSpacing();

	$expected = app( 'visual-editor.spacing-scale' )->toStoreFormat();

	expect( $spacing )->toEqual( $expected );
} );
