<?php

/**
 * TemplatePart Model Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Models
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Revision;
use ArtisanPackUI\VisualEditor\Models\TemplatePart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );
} );

it( 'casts content to array', function (): void {
	$blocks = [
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ] ],
	];

	$part = TemplatePart::create( [
		'name'    => 'Test Header',
		'slug'    => 'test-header',
		'area'    => 'header',
		'content' => $blocks,
	] );

	$part->refresh();

	expect( $part->content )->toBeArray()
		->and( $part->content )->toEqual( $blocks );
} );

it( 'casts boolean fields correctly', function (): void {
	$part = TemplatePart::create( [
		'name'      => 'Bool Test',
		'slug'      => 'bool-test',
		'content'   => [],
		'is_custom' => true,
		'is_locked' => true,
	] );

	$part->refresh();

	expect( $part->is_custom )->toBeTrue()
		->and( $part->is_locked )->toBeTrue();
} );

it( 'has correct default attribute values', function (): void {
	$part = new TemplatePart();

	expect( $part->area )->toBe( 'custom' )
		->and( $part->status )->toBe( 'active' )
		->and( $part->is_custom )->toBeFalse()
		->and( $part->is_locked )->toBeFalse();
} );

it( 'has fillable attributes', function (): void {
	$part = new TemplatePart();

	expect( $part->getFillable() )->toContain(
		'name',
		'slug',
		'description',
		'area',
		'content',
		'status',
		'is_custom',
		'is_locked',
		'user_id',
	);
} );

it( 'enforces unique slug', function (): void {
	TemplatePart::create( [
		'name'    => 'First',
		'slug'    => 'unique-slug',
		'content' => [],
	] );

	expect( fn () => TemplatePart::create( [
		'name'    => 'Second',
		'slug'    => 'unique-slug',
		'content' => [],
	] ) )->toThrow( Illuminate\Database\QueryException::class );
} );

it( 'filters by area scope', function (): void {
	TemplatePart::create( [
		'name'    => 'Header Part',
		'slug'    => 'header-part',
		'area'    => 'header',
		'content' => [],
	] );

	TemplatePart::create( [
		'name'    => 'Footer Part',
		'slug'    => 'footer-part',
		'area'    => 'footer',
		'content' => [],
	] );

	$headers = TemplatePart::forArea( 'header' )->get();

	expect( $headers )->toHaveCount( 1 )
		->and( $headers->first()->name )->toBe( 'Header Part' );
} );

it( 'filters by active scope', function (): void {
	TemplatePart::create( [
		'name'    => 'Active Part',
		'slug'    => 'active-part',
		'content' => [],
		'status'  => 'active',
	] );

	TemplatePart::create( [
		'name'    => 'Draft Part',
		'slug'    => 'draft-part',
		'content' => [],
		'status'  => 'draft',
	] );

	$active = TemplatePart::active()->get();

	expect( $active )->toHaveCount( 1 )
		->and( $active->first()->name )->toBe( 'Active Part' );
} );

it( 'filters by draft scope', function (): void {
	TemplatePart::create( [
		'name'    => 'Active Part',
		'slug'    => 'active-part',
		'content' => [],
		'status'  => 'active',
	] );

	TemplatePart::create( [
		'name'    => 'Draft Part',
		'slug'    => 'draft-part',
		'content' => [],
		'status'  => 'draft',
	] );

	$drafts = TemplatePart::draft()->get();

	expect( $drafts )->toHaveCount( 1 )
		->and( $drafts->first()->name )->toBe( 'Draft Part' );
} );

it( 'filters by custom scope', function (): void {
	TemplatePart::create( [
		'name'      => 'Custom Part',
		'slug'      => 'custom-part',
		'content'   => [],
		'is_custom' => true,
	] );

	TemplatePart::create( [
		'name'      => 'Built-in Part',
		'slug'      => 'built-in-part',
		'content'   => [],
		'is_custom' => false,
	] );

	$custom = TemplatePart::custom()->get();

	expect( $custom )->toHaveCount( 1 )
		->and( $custom->first()->name )->toBe( 'Custom Part' );
} );

it( 'filters by built-in scope', function (): void {
	TemplatePart::create( [
		'name'      => 'Custom Part',
		'slug'      => 'custom-part',
		'content'   => [],
		'is_custom' => true,
	] );

	TemplatePart::create( [
		'name'      => 'Built-in Part',
		'slug'      => 'built-in-part',
		'content'   => [],
		'is_custom' => false,
	] );

	$builtIn = TemplatePart::builtIn()->get();

	expect( $builtIn )->toHaveCount( 1 )
		->and( $builtIn->first()->name )->toBe( 'Built-in Part' );
} );

it( 'filters by user scope', function (): void {
	DB::table( 'users' )->insert( [
		[ 'id' => 1, 'name' => 'User 1', 'email' => 'user1@test.com' ],
		[ 'id' => 2, 'name' => 'User 2', 'email' => 'user2@test.com' ],
	] );

	TemplatePart::create( [
		'name'    => 'User Part',
		'slug'    => 'user-part',
		'content' => [],
		'user_id' => 1,
	] );

	TemplatePart::create( [
		'name'    => 'Other Part',
		'slug'    => 'other-part',
		'content' => [],
		'user_id' => 2,
	] );

	$parts = TemplatePart::byUser( 1 )->get();

	expect( $parts )->toHaveCount( 1 )
		->and( $parts->first()->name )->toBe( 'User Part' );
} );

it( 'creates a revision', function (): void {
	$part = TemplatePart::create( [
		'name'    => 'Rev Part',
		'slug'    => 'rev-part',
		'content' => [ [ 'type' => 'heading' ] ],
	] );

	$revision = $part->createRevision();

	expect( $revision )->toBeInstanceOf( Revision::class )
		->and( $revision->document_type )->toBe( 'template_part' )
		->and( $revision->document_id )->toBe( $part->id )
		->and( $revision->blocks )->toEqual( [ [ 'type' => 'heading' ] ] );
} );

it( 'gets all revisions', function (): void {
	$part = TemplatePart::create( [
		'name'    => 'Multi Rev',
		'slug'    => 'multi-rev',
		'content' => [ [ 'type' => 'heading' ] ],
	] );

	$part->createRevision();
	$part->createRevision();

	$revisions = $part->revisions();

	expect( $revisions )->toHaveCount( 2 );
} );

it( 'duplicates a template part', function (): void {
	$part = TemplatePart::create( [
		'name'    => 'Original Header',
		'slug'    => 'original-header',
		'area'    => 'header',
		'content' => [ [ 'type' => 'paragraph' ] ],
	] );

	$duplicate = $part->duplicate( 'header-copy', 'Header Copy' );

	expect( $duplicate )->toBeInstanceOf( TemplatePart::class )
		->and( $duplicate->slug )->toBe( 'header-copy' )
		->and( $duplicate->name )->toBe( 'Header Copy' )
		->and( $duplicate->area )->toBe( 'header' )
		->and( $duplicate->content )->toEqual( $part->content )
		->and( $duplicate->status )->toBe( 'draft' )
		->and( $duplicate->is_custom )->toBeTrue();
} );

it( 'duplicates with default name', function (): void {
	$part = TemplatePart::create( [
		'name'    => 'My Header',
		'slug'    => 'my-header',
		'content' => [],
	] );

	$duplicate = $part->duplicate( 'my-header-copy' );

	expect( $duplicate->name )->toBe( 'My Header (Copy)' );
} );

it( 'has valid area constants', function (): void {
	expect( TemplatePart::AREAS )->toContain( 'header', 'footer', 'sidebar', 'custom' )
		->and( TemplatePart::AREAS )->toHaveCount( 4 );
} );
