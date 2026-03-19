<?php

/**
 * TemplatePartManager Service Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Services
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Revision;
use ArtisanPackUI\VisualEditor\Models\TemplatePart;
use ArtisanPackUI\VisualEditor\Services\TemplatePartManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );
	$this->manager = app( 'visual-editor.template-parts' );
	$this->manager->clearRegistered();
} );

it( 'registers a template part programmatically', function (): void {
	$this->manager->register( 'my-header', [
		'name' => 'My Header',
		'area' => 'header',
	] );

	$registered = $this->manager->getRegistered();

	expect( $registered )->toHaveKey( 'my-header' )
		->and( $registered['my-header']['name'] )->toBe( 'My Header' )
		->and( $registered['my-header']['area'] )->toBe( 'header' );
} );

it( 'applies defaults when registering', function (): void {
	$this->manager->register( 'minimal', [] );

	$registered = $this->manager->getRegistered();

	expect( $registered['minimal']['name'] )->toBe( 'minimal' )
		->and( $registered['minimal']['area'] )->toBe( 'custom' )
		->and( $registered['minimal']['content'] )->toBeArray()->toBeEmpty()
		->and( $registered['minimal']['status'] )->toBe( 'active' );
} );

it( 'unregisters a template part', function (): void {
	$this->manager->register( 'temp', [ 'name' => 'Temp' ] );
	$this->manager->unregister( 'temp' );

	expect( $this->manager->getRegistered() )->not->toHaveKey( 'temp' );
} );

it( 'clears all registered template parts', function (): void {
	$this->manager->register( 'one', [ 'name' => 'One' ] );
	$this->manager->register( 'two', [ 'name' => 'Two' ] );
	$this->manager->clearRegistered();

	expect( $this->manager->getRegistered() )->toBeEmpty();
} );

it( 'returns all template parts merging registered and database', function (): void {
	$this->manager->register( 'registered-only', [ 'name' => 'Registered' ] );

	TemplatePart::create( [
		'name'    => 'DB Part',
		'slug'    => 'db-part',
		'content' => [],
	] );

	$all = $this->manager->all();

	expect( $all )->toHaveKey( 'registered-only' )
		->and( $all )->toHaveKey( 'db-part' );
} );

it( 'database template parts override registered with same slug', function (): void {
	$this->manager->register( 'same-slug', [ 'name' => 'From Registry' ] );

	TemplatePart::create( [
		'name'    => 'From Database',
		'slug'    => 'same-slug',
		'content' => [],
	] );

	$all = $this->manager->all();

	expect( $all['same-slug']['name'] )->toBe( 'From Database' );
} );

it( 'returns active template parts only', function (): void {
	$this->manager->register( 'active-reg', [
		'name'   => 'Active Registered',
		'status' => 'active',
	] );

	$this->manager->register( 'draft-reg', [
		'name'   => 'Draft Registered',
		'status' => 'draft',
	] );

	TemplatePart::create( [
		'name'    => 'Active DB',
		'slug'    => 'active-db',
		'content' => [],
		'status'  => 'active',
	] );

	TemplatePart::create( [
		'name'    => 'Draft DB',
		'slug'    => 'draft-db',
		'content' => [],
		'status'  => 'draft',
	] );

	$active = $this->manager->allActive();

	expect( $active )->toHaveKey( 'active-reg' )
		->and( $active )->toHaveKey( 'active-db' )
		->and( $active )->not->toHaveKey( 'draft-reg' )
		->and( $active )->not->toHaveKey( 'draft-db' );
} );

it( 'filters template parts by area', function (): void {
	$this->manager->register( 'header-reg', [
		'name' => 'Header Registered',
		'area' => 'header',
	] );

	$this->manager->register( 'footer-reg', [
		'name' => 'Footer Registered',
		'area' => 'footer',
	] );

	TemplatePart::create( [
		'name'    => 'Header DB',
		'slug'    => 'header-db',
		'area'    => 'header',
		'content' => [],
	] );

	TemplatePart::create( [
		'name'    => 'Sidebar DB',
		'slug'    => 'sidebar-db',
		'area'    => 'sidebar',
		'content' => [],
	] );

	$headers = $this->manager->forArea( 'header' );

	expect( $headers )->toHaveKey( 'header-reg' )
		->and( $headers )->toHaveKey( 'header-db' )
		->and( $headers )->not->toHaveKey( 'footer-reg' )
		->and( $headers )->not->toHaveKey( 'sidebar-db' );
} );

it( 'resolves a database template part first', function (): void {
	$this->manager->register( 'test', [ 'name' => 'From Registry' ] );

	TemplatePart::create( [
		'name'    => 'From DB',
		'slug'    => 'test',
		'content' => [],
	] );

	$result = $this->manager->resolve( 'test' );

	expect( $result )->toBeInstanceOf( TemplatePart::class )
		->and( $result->name )->toBe( 'From DB' );
} );

it( 'resolves a registered template part when not in database', function (): void {
	$this->manager->register( 'reg-only', [ 'name' => 'Registry Only' ] );

	$result = $this->manager->resolve( 'reg-only' );

	expect( $result )->toBeArray()
		->and( $result['name'] )->toBe( 'Registry Only' );
} );

it( 'returns null for nonexistent template part', function (): void {
	expect( $this->manager->resolve( 'nonexistent' ) )->toBeNull();
} );

it( 'checks existence in registry and database', function (): void {
	$this->manager->register( 'in-registry', [ 'name' => 'In Registry' ] );

	TemplatePart::create( [
		'name'    => 'In DB',
		'slug'    => 'in-db',
		'content' => [],
	] );

	expect( $this->manager->exists( 'in-registry' ) )->toBeTrue()
		->and( $this->manager->exists( 'in-db' ) )->toBeTrue()
		->and( $this->manager->exists( 'nowhere' ) )->toBeFalse();
} );

it( 'throws when creating a template part with a duplicate slug in database', function (): void {
	TemplatePart::create( [
		'name'    => 'Existing',
		'slug'    => 'duplicate-slug',
		'content' => [],
	] );

	expect( fn () => $this->manager->create( [
		'name'    => 'Duplicate',
		'slug'    => 'duplicate-slug',
		'content' => [],
	] ) )->toThrow( RuntimeException::class );
} );

it( 'throws when creating a template part with a slug that exists in registry', function (): void {
	$this->manager->register( 'registered-slug', [ 'name' => 'Registered' ] );

	expect( fn () => $this->manager->create( [
		'name'    => 'Conflict',
		'slug'    => 'registered-slug',
		'content' => [],
	] ) )->toThrow( RuntimeException::class );
} );

it( 'throws when creating a template part with an invalid area', function (): void {
	expect( fn () => $this->manager->create( [
		'name'    => 'Bad Area',
		'slug'    => 'bad-area',
		'area'    => 'nonexistent-area',
		'content' => [],
	] ) )->toThrow( RuntimeException::class );
} );

it( 'throws when updating slug to one that already exists', function (): void {
	$partA = TemplatePart::create( [
		'name'    => 'Part A',
		'slug'    => 'part-a',
		'content' => [],
	] );

	TemplatePart::create( [
		'name'    => 'Part B',
		'slug'    => 'part-b',
		'content' => [],
	] );

	expect( fn () => $this->manager->update( $partA, [ 'slug' => 'part-b' ] ) )
		->toThrow( RuntimeException::class );
} );

it( 'throws when updating slug to one in registry', function (): void {
	$part = TemplatePart::create( [
		'name'    => 'DB Part',
		'slug'    => 'db-part',
		'content' => [],
	] );

	$this->manager->register( 'reg-slug', [ 'name' => 'Registered' ] );

	expect( fn () => $this->manager->update( $part, [ 'slug' => 'reg-slug' ] ) )
		->toThrow( RuntimeException::class );
} );

it( 'allows updating slug to same value', function (): void {
	$part = TemplatePart::create( [
		'name'    => 'Same Slug',
		'slug'    => 'same-slug',
		'content' => [],
	] );

	$updated = $this->manager->update( $part, [ 'slug' => 'same-slug', 'name' => 'Renamed' ] );

	expect( $updated->fresh()->name )->toBe( 'Renamed' );
} );

it( 'throws when duplicating to an existing slug', function (): void {
	$part = TemplatePart::create( [
		'name'    => 'Original',
		'slug'    => 'original',
		'content' => [],
	] );

	TemplatePart::create( [
		'name'    => 'Taken',
		'slug'    => 'taken-slug',
		'content' => [],
	] );

	expect( fn () => $this->manager->duplicate( $part, 'taken-slug' ) )
		->toThrow( RuntimeException::class );
} );

it( 'creates a template part in the database', function (): void {
	$part = $this->manager->create( [
		'name'    => 'Created',
		'slug'    => 'created',
		'area'    => 'header',
		'content' => [ [ 'type' => 'paragraph' ] ],
	] );

	expect( $part )->toBeInstanceOf( TemplatePart::class )
		->and( $part->exists )->toBeTrue()
		->and( $part->name )->toBe( 'Created' )
		->and( $part->area )->toBe( 'header' );
} );

it( 'updates a template part and creates revision on content change', function (): void {
	$part = TemplatePart::create( [
		'name'    => 'Original',
		'slug'    => 'original',
		'content' => [ [ 'type' => 'heading' ] ],
	] );

	$updated = $this->manager->update( $part, [
		'content' => [ [ 'type' => 'paragraph' ] ],
	] );

	$revisions = Revision::forDocument( 'template_part', $part->id )->get();

	expect( $updated->content )->toEqual( [ [ 'type' => 'paragraph' ] ] )
		->and( $revisions )->toHaveCount( 1 )
		->and( $revisions->first()->blocks )->toEqual( [ [ 'type' => 'heading' ] ] );
} );

it( 'updates a template part without revision when content unchanged', function (): void {
	$part = TemplatePart::create( [
		'name'    => 'No Rev',
		'slug'    => 'no-rev',
		'content' => [ [ 'type' => 'heading' ] ],
	] );

	$this->manager->update( $part, [
		'name' => 'Renamed',
	] );

	$revisions = Revision::forDocument( 'template_part', $part->id )->get();

	expect( $part->fresh()->name )->toBe( 'Renamed' )
		->and( $revisions )->toHaveCount( 0 );
} );

it( 'throws when updating a locked template part', function (): void {
	$part = TemplatePart::create( [
		'name'      => 'Locked',
		'slug'      => 'locked',
		'content'   => [],
		'is_locked' => true,
	] );

	expect( fn () => $this->manager->update( $part, [ 'name' => 'Changed' ] ) )
		->toThrow( RuntimeException::class );
} );

it( 'deletes a template part', function (): void {
	$part = TemplatePart::create( [
		'name'    => 'Deletable',
		'slug'    => 'deletable',
		'content' => [],
	] );

	$result = $this->manager->delete( $part );

	expect( $result )->toBeTrue()
		->and( TemplatePart::where( 'slug', 'deletable' )->exists() )->toBeFalse();
} );

it( 'throws when deleting a locked template part', function (): void {
	$part = TemplatePart::create( [
		'name'      => 'Locked Del',
		'slug'      => 'locked-del',
		'content'   => [],
		'is_locked' => true,
	] );

	expect( fn () => $this->manager->delete( $part ) )
		->toThrow( RuntimeException::class );
} );

it( 'duplicates a template part via manager', function (): void {
	$part = TemplatePart::create( [
		'name'    => 'Source',
		'slug'    => 'source',
		'area'    => 'footer',
		'content' => [ [ 'type' => 'heading' ] ],
	] );

	$duplicate = $this->manager->duplicate( $part, 'source-copy', 'Source Copy' );

	expect( $duplicate )->toBeInstanceOf( TemplatePart::class )
		->and( $duplicate->slug )->toBe( 'source-copy' )
		->and( $duplicate->name )->toBe( 'Source Copy' )
		->and( $duplicate->area )->toBe( 'footer' )
		->and( $duplicate->content )->toEqual( $part->content )
		->and( $duplicate->is_custom )->toBeTrue();
} );

it( 'locks a template part', function (): void {
	$part = TemplatePart::create( [
		'name'    => 'Unlocked',
		'slug'    => 'unlocked',
		'content' => [],
	] );

	$this->manager->lock( $part );

	expect( $part->fresh()->is_locked )->toBeTrue();
} );

it( 'unlocks a template part', function (): void {
	$part = TemplatePart::create( [
		'name'      => 'Was Locked',
		'slug'      => 'was-locked',
		'content'   => [],
		'is_locked' => true,
	] );

	$this->manager->unlock( $part );

	expect( $part->fresh()->is_locked )->toBeFalse();
} );

it( 'seeds registered template parts to database', function (): void {
	$this->manager->register( 'seed-one', [
		'name'    => 'Seed One',
		'area'    => 'header',
		'content' => [],
	] );

	$this->manager->register( 'seed-two', [
		'name'    => 'Seed Two',
		'area'    => 'footer',
		'content' => [],
	] );

	$created = $this->manager->seedRegistered();

	expect( $created )->toHaveCount( 2 )
		->and( TemplatePart::where( 'slug', 'seed-one' )->exists() )->toBeTrue()
		->and( TemplatePart::where( 'slug', 'seed-two' )->exists() )->toBeTrue();
} );

it( 'skips already existing template parts when seeding', function (): void {
	TemplatePart::create( [
		'name'    => 'Existing',
		'slug'    => 'existing',
		'content' => [],
	] );

	$this->manager->register( 'existing', [ 'name' => 'Should Skip' ] );
	$this->manager->register( 'new-one', [ 'name' => 'New One' ] );

	$created = $this->manager->seedRegistered();

	expect( $created )->toHaveCount( 1 )
		->and( $created[0]->slug )->toBe( 'new-one' );
} );
