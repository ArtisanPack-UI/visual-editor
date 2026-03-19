<?php

/**
 * TemplatePresetManager Service Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Services
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Template;
use ArtisanPackUI\VisualEditor\Models\TemplatePreset;
use ArtisanPackUI\VisualEditor\Services\TemplatePresetManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );
	$this->manager = app( 'visual-editor.template-presets' );
	$this->manager->clearRegistered();
} );

it( 'registers a preset programmatically', function (): void {
	$this->manager->register( 'my-preset', [
		'name'     => 'My Preset',
		'category' => 'blog',
	] );

	$registered = $this->manager->getRegistered();

	expect( $registered )->toHaveKey( 'my-preset' )
		->and( $registered['my-preset']['name'] )->toBe( 'My Preset' )
		->and( $registered['my-preset']['category'] )->toBe( 'blog' );
} );

it( 'applies defaults when registering', function (): void {
	$this->manager->register( 'minimal', [] );

	$registered = $this->manager->getRegistered();

	expect( $registered['minimal']['name'] )->toBe( 'minimal' )
		->and( $registered['minimal']['type'] )->toBe( 'page' )
		->and( $registered['minimal']['content'] )->toBeArray()->toBeEmpty()
		->and( $registered['minimal']['category'] )->toBeNull()
		->and( $registered['minimal']['template_parts'] )->toBeArray()->toBeEmpty();
} );

it( 'unregisters a preset', function (): void {
	$this->manager->register( 'temp', [ 'name' => 'Temp' ] );
	$this->manager->unregister( 'temp' );

	expect( $this->manager->getRegistered() )->not->toHaveKey( 'temp' );
} );

it( 'clears all registered presets', function (): void {
	$this->manager->register( 'one', [ 'name' => 'One' ] );
	$this->manager->register( 'two', [ 'name' => 'Two' ] );
	$this->manager->clearRegistered();

	expect( $this->manager->getRegistered() )->toBeEmpty();
} );

it( 'returns all presets merging registered and database', function (): void {
	$this->manager->register( 'registered-only', [ 'name' => 'Registered' ] );

	TemplatePreset::create( [
		'name'    => 'DB Preset',
		'slug'    => 'db-preset',
		'content' => [],
	] );

	$all = $this->manager->all();

	expect( $all )->toHaveKey( 'registered-only' )
		->and( $all )->toHaveKey( 'db-preset' );
} );

it( 'database presets override registered with same slug', function (): void {
	$this->manager->register( 'same-slug', [ 'name' => 'From Registry' ] );

	TemplatePreset::create( [
		'name'    => 'From Database',
		'slug'    => 'same-slug',
		'content' => [],
	] );

	$all = $this->manager->all();

	expect( $all['same-slug']['name'] )->toBe( 'From Database' );
} );

it( 'filters presets by category', function (): void {
	$this->manager->register( 'blog-one', [
		'name'     => 'Blog One',
		'category' => 'blog',
	] );

	$this->manager->register( 'marketing-one', [
		'name'     => 'Marketing One',
		'category' => 'marketing',
	] );

	TemplatePreset::create( [
		'name'     => 'Blog DB',
		'slug'     => 'blog-db',
		'category' => 'blog',
		'content'  => [],
	] );

	$blogPresets = $this->manager->forCategory( 'blog' );

	expect( $blogPresets )->toHaveKey( 'blog-one' )
		->and( $blogPresets )->toHaveKey( 'blog-db' )
		->and( $blogPresets )->not->toHaveKey( 'marketing-one' );
} );

it( 'filters presets by content type', function (): void {
	$this->manager->register( 'universal-reg', [
		'name'             => 'Universal',
		'for_content_type' => null,
	] );

	$this->manager->register( 'post-reg', [
		'name'             => 'Post Preset',
		'for_content_type' => 'post',
	] );

	$this->manager->register( 'page-reg', [
		'name'             => 'Page Preset',
		'for_content_type' => 'page',
	] );

	$postPresets = $this->manager->forContentType( 'post' );

	expect( $postPresets )->toHaveKey( 'universal-reg' )
		->and( $postPresets )->toHaveKey( 'post-reg' )
		->and( $postPresets )->not->toHaveKey( 'page-reg' );
} );

it( 'returns all available categories', function (): void {
	$this->manager->register( 'one', [ 'name' => 'One', 'category' => 'blog' ] );
	$this->manager->register( 'two', [ 'name' => 'Two', 'category' => 'marketing' ] );

	TemplatePreset::create( [
		'name'     => 'DB',
		'slug'     => 'db',
		'category' => 'portfolio',
		'content'  => [],
	] );

	$categories = $this->manager->categories();

	expect( $categories )->toContain( 'blog' )
		->and( $categories )->toContain( 'marketing' )
		->and( $categories )->toContain( 'portfolio' );
} );

it( 'resolves a database preset first', function (): void {
	$this->manager->register( 'test', [ 'name' => 'From Registry' ] );

	TemplatePreset::create( [
		'name'    => 'From DB',
		'slug'    => 'test',
		'content' => [],
	] );

	$result = $this->manager->resolve( 'test' );

	expect( $result )->toBeInstanceOf( TemplatePreset::class )
		->and( $result->name )->toBe( 'From DB' );
} );

it( 'resolves a registered preset when not in database', function (): void {
	$this->manager->register( 'reg-only', [ 'name' => 'Registry Only' ] );

	$result = $this->manager->resolve( 'reg-only' );

	expect( $result )->toBeArray()
		->and( $result['name'] )->toBe( 'Registry Only' );
} );

it( 'returns null for nonexistent preset', function (): void {
	expect( $this->manager->resolve( 'nonexistent' ) )->toBeNull();
} );

it( 'checks existence in registry and database', function (): void {
	$this->manager->register( 'in-registry', [ 'name' => 'In Registry' ] );

	TemplatePreset::create( [
		'name'    => 'In DB',
		'slug'    => 'in-db',
		'content' => [],
	] );

	expect( $this->manager->exists( 'in-registry' ) )->toBeTrue()
		->and( $this->manager->exists( 'in-db' ) )->toBeTrue()
		->and( $this->manager->exists( 'nowhere' ) )->toBeFalse();
} );

it( 'creates a preset in the database', function (): void {
	$preset = $this->manager->create( [
		'name'     => 'Created',
		'slug'     => 'created',
		'content'  => [ [ 'type' => 'paragraph' ] ],
		'category' => 'blog',
	] );

	expect( $preset )->toBeInstanceOf( TemplatePreset::class )
		->and( $preset->exists )->toBeTrue()
		->and( $preset->name )->toBe( 'Created' )
		->and( $preset->category )->toBe( 'blog' );
} );

it( 'updates a preset', function (): void {
	$preset = TemplatePreset::create( [
		'name'     => 'Original',
		'slug'     => 'original',
		'content'  => [],
		'category' => 'blog',
	] );

	$updated = $this->manager->update( $preset, [
		'name'     => 'Updated',
		'category' => 'marketing',
	] );

	expect( $updated->name )->toBe( 'Updated' )
		->and( $updated->category )->toBe( 'marketing' );
} );

it( 'deletes a preset', function (): void {
	$preset = TemplatePreset::create( [
		'name'    => 'Deletable',
		'slug'    => 'deletable',
		'content' => [],
	] );

	$result = $this->manager->delete( $preset );

	expect( $result )->toBeTrue()
		->and( TemplatePreset::where( 'slug', 'deletable' )->exists() )->toBeFalse();
} );

it( 'creates a template from a registered preset', function (): void {
	$this->manager->register( 'blog-post', [
		'name'                  => 'Blog Post',
		'category'              => 'blog',
		'content'               => [ [ 'type' => 'heading' ], [ 'type' => 'paragraph' ] ],
		'content_area_settings' => [ 'max_width' => 'container' ],
	] );

	$template = $this->manager->createTemplateFromPreset(
		'blog-post',
		'my-blog-post',
		'My Blog Post',
	);

	expect( $template )->toBeInstanceOf( Template::class )
		->and( $template->slug )->toBe( 'my-blog-post' )
		->and( $template->name )->toBe( 'My Blog Post' )
		->and( $template->content )->toEqual( [ [ 'type' => 'heading' ], [ 'type' => 'paragraph' ] ] )
		->and( $template->content_area_settings )->toEqual( [ 'max_width' => 'container' ] )
		->and( $template->is_custom )->toBeTrue()
		->and( $template->status )->toBe( 'draft' );
} );

it( 'creates a template from a database preset', function (): void {
	TemplatePreset::create( [
		'name'                  => 'Landing Page',
		'slug'                  => 'landing-page',
		'content'               => [ [ 'type' => 'columns' ] ],
		'content_area_settings' => [ 'max_width' => 'full' ],
		'category'              => 'marketing',
	] );

	$template = $this->manager->createTemplateFromPreset(
		'landing-page',
		'my-landing',
		'My Landing Page',
	);

	expect( $template )->toBeInstanceOf( Template::class )
		->and( $template->slug )->toBe( 'my-landing' )
		->and( $template->content )->toEqual( [ [ 'type' => 'columns' ] ] );
} );

it( 'returns null when creating from nonexistent preset', function (): void {
	$result = $this->manager->createTemplateFromPreset( 'nonexistent', 'slug', 'Name' );

	expect( $result )->toBeNull();
} );

it( 'applies overrides when creating template from preset', function (): void {
	$this->manager->register( 'basic', [
		'name'    => 'Basic',
		'content' => [ [ 'type' => 'paragraph' ] ],
	] );

	$template = $this->manager->createTemplateFromPreset(
		'basic',
		'custom',
		'Custom',
		[ 'status' => 'active', 'for_content_type' => 'post' ],
	);

	expect( $template->status )->toBe( 'active' )
		->and( $template->for_content_type )->toBe( 'post' );
} );

it( 'saves a template as a preset', function (): void {
	$template = Template::create( [
		'name'                  => 'My Template',
		'slug'                  => 'my-template',
		'content'               => [ [ 'type' => 'heading' ] ],
		'content_area_settings' => [ 'max_width' => 'container' ],
		'styles'                => [ 'color' => '#333' ],
		'type'                  => 'page',
	] );

	$preset = $this->manager->saveTemplateAsPreset(
		$template,
		'my-preset',
		'My Custom Preset',
		'blog',
	);

	expect( $preset )->toBeInstanceOf( TemplatePreset::class )
		->and( $preset->slug )->toBe( 'my-preset' )
		->and( $preset->name )->toBe( 'My Custom Preset' )
		->and( $preset->category )->toBe( 'blog' )
		->and( $preset->content )->toEqual( [ [ 'type' => 'heading' ] ] )
		->and( $preset->content_area_settings )->toEqual( [ 'max_width' => 'container' ] )
		->and( $preset->styles )->toEqual( [ 'color' => '#333' ] )
		->and( $preset->is_custom )->toBeTrue();
} );

it( 'seeds registered presets to database', function (): void {
	$this->manager->register( 'seed-one', [
		'name'     => 'Seed One',
		'content'  => [],
		'category' => 'blog',
	] );

	$this->manager->register( 'seed-two', [
		'name'     => 'Seed Two',
		'content'  => [],
		'category' => 'marketing',
	] );

	$created = $this->manager->seedRegistered();

	expect( $created )->toHaveCount( 2 )
		->and( TemplatePreset::where( 'slug', 'seed-one' )->exists() )->toBeTrue()
		->and( TemplatePreset::where( 'slug', 'seed-two' )->exists() )->toBeTrue();
} );

it( 'skips already existing presets when seeding', function (): void {
	TemplatePreset::create( [
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
