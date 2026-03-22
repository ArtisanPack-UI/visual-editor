<?php

/**
 * Global Style Model Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Models
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\GlobalStyle;
use ArtisanPackUI\VisualEditor\Models\Revision;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

test( 'global style can be created with key and style data', function (): void {
	$record = GlobalStyle::create( [
		'key'        => 'default',
		'palette'    => [ [ 'name' => 'Primary', 'slug' => 'primary', 'color' => '#3b82f6' ] ],
		'typography' => [ 'fontFamilies' => [ 'heading' => 'sans-serif' ] ],
		'spacing'    => [ 'scale' => [], 'blockGap' => 'md' ],
	] );

	expect( $record->id )->toBeInt();
	expect( $record->key )->toBe( 'default' );
	expect( $record->palette )->toBeArray();
	expect( $record->palette[0]['slug'] )->toBe( 'primary' );
	expect( $record->typography )->toBeArray();
	expect( $record->spacing )->toBeArray();
} );

test( 'global style enforces unique key constraint', function (): void {
	GlobalStyle::create( [ 'key' => 'default' ] );

	GlobalStyle::create( [ 'key' => 'default' ] );
} )->throws( Illuminate\Database\QueryException::class );

test( 'global style byKey scope filters correctly', function (): void {
	GlobalStyle::create( [ 'key' => 'default' ] );
	GlobalStyle::create( [ 'key' => 'alternate' ] );

	$result = GlobalStyle::byKey( 'default' )->first();

	expect( $result )->not->toBeNull();
	expect( $result->key )->toBe( 'default' );
} );

test( 'global style casts palette, typography, and spacing as arrays', function (): void {
	$record = GlobalStyle::create( [
		'key'        => 'default',
		'palette'    => [ [ 'name' => 'Primary', 'slug' => 'primary', 'color' => '#3b82f6' ] ],
		'typography' => [ 'fontFamilies' => [] ],
		'spacing'    => [ 'scale' => [] ],
	] );

	$fresh = $record->fresh();

	expect( $fresh->palette )->toBeArray();
	expect( $fresh->typography )->toBeArray();
	expect( $fresh->spacing )->toBeArray();
} );

test( 'global style can create a revision', function (): void {
	$record = GlobalStyle::create( [
		'key'     => 'default',
		'palette' => [ [ 'name' => 'Primary', 'slug' => 'primary', 'color' => '#3b82f6' ] ],
	] );

	$revision = $record->createRevision();

	expect( $revision )->toBeInstanceOf( Revision::class );
	expect( $revision->document_type )->toBe( GlobalStyle::REVISION_DOCUMENT_TYPE );
	expect( $revision->document_id )->toBe( $record->id );
	expect( $revision->blocks['palette'] )->toEqual( $record->palette );
} );

test( 'global style can restore from a revision', function (): void {
	$record = GlobalStyle::create( [
		'key'        => 'default',
		'palette'    => [ [ 'name' => 'Original', 'slug' => 'original', 'color' => '#000000' ] ],
		'typography' => null,
		'spacing'    => null,
	] );

	$revision = $record->createRevision();

	$record->update( [
		'palette' => [ [ 'name' => 'Changed', 'slug' => 'changed', 'color' => '#ffffff' ] ],
	] );

	$record->restoreFromRevision( $revision );

	$fresh = $record->fresh();

	expect( $fresh->palette[0]['slug'] )->toBe( 'original' );
} );

test( 'global style revisions relationship returns revisions in descending order', function (): void {
	$record = GlobalStyle::create( [ 'key' => 'default' ] );

	$record->createRevision();
	$record->createRevision();

	$revisions = $record->revisions;

	expect( $revisions )->toHaveCount( 2 );
	expect( $revisions->first()->created_at->gte( $revisions->last()->created_at ) )->toBeTrue();
} );

test( 'global style prunes old revisions beyond maximum', function (): void {
	config( [ 'artisanpack.visual-editor.persistence.max_revisions' => 3 ] );

	$record = GlobalStyle::create( [
		'key'     => 'default',
		'palette' => [],
	] );

	for ( $i = 0; $i < 5; $i++ ) {
		$record->createRevision();
	}

	$count = Revision::forDocument( GlobalStyle::REVISION_DOCUMENT_TYPE, $record->id )->count();

	expect( $count )->toBe( 3 );
} );

test( 'global style allows nullable palette, typography, and spacing', function (): void {
	$record = GlobalStyle::create( [
		'key'        => 'default',
		'palette'    => null,
		'typography' => null,
		'spacing'    => null,
	] );

	$fresh = $record->fresh();

	expect( $fresh->palette )->toBeNull();
	expect( $fresh->typography )->toBeNull();
	expect( $fresh->spacing )->toBeNull();
} );
