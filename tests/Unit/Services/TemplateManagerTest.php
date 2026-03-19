<?php

/**
 * TemplateManager Service Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Services
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Revision;
use ArtisanPackUI\VisualEditor\Models\Template;
use ArtisanPackUI\VisualEditor\Services\TemplateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );
	$this->manager = app( 'visual-editor.templates' );
	$this->manager->clearRegistered();
} );

it( 'registers a template programmatically', function (): void {
	$this->manager->register( 'my-template', [
		'name' => 'My Template',
		'type' => 'page',
	] );

	$registered = $this->manager->getRegistered();

	expect( $registered )->toHaveKey( 'my-template' )
		->and( $registered['my-template']['name'] )->toBe( 'My Template' )
		->and( $registered['my-template']['type'] )->toBe( 'page' );
} );

it( 'applies defaults when registering', function (): void {
	$this->manager->register( 'minimal', [] );

	$registered = $this->manager->getRegistered();

	expect( $registered['minimal']['name'] )->toBe( 'minimal' )
		->and( $registered['minimal']['type'] )->toBe( 'page' )
		->and( $registered['minimal']['content'] )->toBeArray()->toBeEmpty()
		->and( $registered['minimal']['status'] )->toBe( 'active' );
} );

it( 'unregisters a template', function (): void {
	$this->manager->register( 'temp', [ 'name' => 'Temp' ] );
	$this->manager->unregister( 'temp' );

	expect( $this->manager->getRegistered() )->not->toHaveKey( 'temp' );
} );

it( 'clears all registered templates', function (): void {
	$this->manager->register( 'one', [ 'name' => 'One' ] );
	$this->manager->register( 'two', [ 'name' => 'Two' ] );
	$this->manager->clearRegistered();

	expect( $this->manager->getRegistered() )->toBeEmpty();
} );

it( 'returns all templates merging registered and database', function (): void {
	$this->manager->register( 'registered-only', [ 'name' => 'Registered' ] );

	Template::create( [
		'name'    => 'DB Template',
		'slug'    => 'db-template',
		'content' => [],
	] );

	$all = $this->manager->all();

	expect( $all )->toHaveKey( 'registered-only' )
		->and( $all )->toHaveKey( 'db-template' );
} );

it( 'database templates override registered with same slug', function (): void {
	$this->manager->register( 'same-slug', [ 'name' => 'From Registry' ] );

	Template::create( [
		'name'    => 'From Database',
		'slug'    => 'same-slug',
		'content' => [],
	] );

	$all = $this->manager->all();

	expect( $all['same-slug']['name'] )->toBe( 'From Database' );
} );

it( 'returns active templates only', function (): void {
	$this->manager->register( 'active-reg', [
		'name'   => 'Active Registered',
		'status' => 'active',
	] );

	$this->manager->register( 'draft-reg', [
		'name'   => 'Draft Registered',
		'status' => 'draft',
	] );

	Template::create( [
		'name'    => 'Active DB',
		'slug'    => 'active-db',
		'content' => [],
		'status'  => 'active',
	] );

	Template::create( [
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

it( 'filters templates by content type', function (): void {
	$this->manager->register( 'universal-reg', [
		'name'             => 'Universal',
		'for_content_type' => null,
	] );

	$this->manager->register( 'post-reg', [
		'name'             => 'Post Registered',
		'for_content_type' => 'post',
	] );

	$this->manager->register( 'page-reg', [
		'name'             => 'Page Registered',
		'for_content_type' => 'page',
	] );

	$postTemplates = $this->manager->forContentType( 'post' );

	expect( $postTemplates )->toHaveKey( 'universal-reg' )
		->and( $postTemplates )->toHaveKey( 'post-reg' )
		->and( $postTemplates )->not->toHaveKey( 'page-reg' );
} );

it( 'resolves a database template first', function (): void {
	$this->manager->register( 'test', [ 'name' => 'From Registry' ] );

	Template::create( [
		'name'    => 'From DB',
		'slug'    => 'test',
		'content' => [],
	] );

	$result = $this->manager->resolve( 'test' );

	expect( $result )->toBeInstanceOf( Template::class )
		->and( $result->name )->toBe( 'From DB' );
} );

it( 'resolves a registered template when not in database', function (): void {
	$this->manager->register( 'reg-only', [ 'name' => 'Registry Only' ] );

	$result = $this->manager->resolve( 'reg-only' );

	expect( $result )->toBeArray()
		->and( $result['name'] )->toBe( 'Registry Only' );
} );

it( 'returns null for nonexistent template', function (): void {
	expect( $this->manager->resolve( 'nonexistent' ) )->toBeNull();
} );

it( 'checks existence in registry and database', function (): void {
	$this->manager->register( 'in-registry', [ 'name' => 'In Registry' ] );

	Template::create( [
		'name'    => 'In DB',
		'slug'    => 'in-db',
		'content' => [],
	] );

	expect( $this->manager->exists( 'in-registry' ) )->toBeTrue()
		->and( $this->manager->exists( 'in-db' ) )->toBeTrue()
		->and( $this->manager->exists( 'nowhere' ) )->toBeFalse();
} );

it( 'creates a template in the database', function (): void {
	$template = $this->manager->create( [
		'name'    => 'Created',
		'slug'    => 'created',
		'content' => [ [ 'type' => 'paragraph' ] ],
	] );

	expect( $template )->toBeInstanceOf( Template::class )
		->and( $template->exists )->toBeTrue()
		->and( $template->name )->toBe( 'Created' );
} );

it( 'updates a template and creates revision on content change', function (): void {
	$template = Template::create( [
		'name'    => 'Original',
		'slug'    => 'original',
		'content' => [ [ 'type' => 'heading' ] ],
	] );

	$updated = $this->manager->update( $template, [
		'content' => [ [ 'type' => 'paragraph' ] ],
	] );

	$revisions = Revision::forDocument( 'template', $template->id )->get();

	expect( $updated->content )->toEqual( [ [ 'type' => 'paragraph' ] ] )
		->and( $revisions )->toHaveCount( 1 )
		->and( $revisions->first()->blocks )->toEqual( [ [ 'type' => 'heading' ] ] );
} );

it( 'updates a template without revision when content unchanged', function (): void {
	$template = Template::create( [
		'name'    => 'No Rev',
		'slug'    => 'no-rev',
		'content' => [ [ 'type' => 'heading' ] ],
	] );

	$this->manager->update( $template, [
		'name' => 'Renamed',
	] );

	$revisions = Revision::forDocument( 'template', $template->id )->get();

	expect( $template->fresh()->name )->toBe( 'Renamed' )
		->and( $revisions )->toHaveCount( 0 );
} );

it( 'throws when updating a locked template', function (): void {
	$template = Template::create( [
		'name'      => 'Locked',
		'slug'      => 'locked',
		'content'   => [],
		'is_locked' => true,
	] );

	expect( fn () => $this->manager->update( $template, [ 'name' => 'Changed' ] ) )
		->toThrow( RuntimeException::class );
} );

it( 'deletes a template', function (): void {
	$template = Template::create( [
		'name'    => 'Deletable',
		'slug'    => 'deletable',
		'content' => [],
	] );

	$result = $this->manager->delete( $template );

	expect( $result )->toBeTrue()
		->and( Template::where( 'slug', 'deletable' )->exists() )->toBeFalse();
} );

it( 'throws when deleting a locked template', function (): void {
	$template = Template::create( [
		'name'      => 'Locked Del',
		'slug'      => 'locked-del',
		'content'   => [],
		'is_locked' => true,
	] );

	expect( fn () => $this->manager->delete( $template ) )
		->toThrow( RuntimeException::class );
} );

it( 'duplicates a template via manager', function (): void {
	$template = Template::create( [
		'name'    => 'Source',
		'slug'    => 'source',
		'content' => [ [ 'type' => 'heading' ] ],
	] );

	$duplicate = $this->manager->duplicate( $template, 'source-copy', 'Source Copy' );

	expect( $duplicate )->toBeInstanceOf( Template::class )
		->and( $duplicate->slug )->toBe( 'source-copy' )
		->and( $duplicate->name )->toBe( 'Source Copy' )
		->and( $duplicate->content )->toEqual( $template->content )
		->and( $duplicate->is_custom )->toBeTrue();
} );

it( 'locks a template', function (): void {
	$template = Template::create( [
		'name'    => 'Unlocked',
		'slug'    => 'unlocked',
		'content' => [],
	] );

	$this->manager->lock( $template );

	expect( $template->fresh()->is_locked )->toBeTrue();
} );

it( 'unlocks a template', function (): void {
	$template = Template::create( [
		'name'      => 'Was Locked',
		'slug'      => 'was-locked',
		'content'   => [],
		'is_locked' => true,
	] );

	$this->manager->unlock( $template );

	expect( $template->fresh()->is_locked )->toBeFalse();
} );

it( 'seeds registered templates to database', function (): void {
	$this->manager->register( 'seed-one', [
		'name'    => 'Seed One',
		'content' => [],
	] );

	$this->manager->register( 'seed-two', [
		'name'    => 'Seed Two',
		'content' => [],
	] );

	$created = $this->manager->seedRegistered();

	expect( $created )->toHaveCount( 2 )
		->and( Template::where( 'slug', 'seed-one' )->exists() )->toBeTrue()
		->and( Template::where( 'slug', 'seed-two' )->exists() )->toBeTrue();
} );

it( 'skips already existing templates when seeding', function (): void {
	Template::create( [
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
