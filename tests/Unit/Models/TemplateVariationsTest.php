<?php

/**
 * Template Variations Unit Tests.
 *
 * Tests template variation relationships, inheritance,
 * and resolution logic.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Models
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );
	$this->manager = app( 'visual-editor.templates' );
	$this->manager->clearRegistered();
} );

it( 'creates a variation of a template', function (): void {
	$parent = Template::create( [
		'name'                  => 'Blog Post',
		'slug'                  => 'blog-post',
		'content'               => [ [ 'type' => 'heading' ] ],
		'content_area_settings' => [ 'max_width' => 'container' ],
		'styles'                => [ 'color' => '#333' ],
	] );

	$variation = $parent->createVariation( 'blog-post-sidebar', 'Blog Post with Sidebar' );

	expect( $variation )->toBeInstanceOf( Template::class )
		->and( $variation->slug )->toBe( 'blog-post-sidebar' )
		->and( $variation->name )->toBe( 'Blog Post with Sidebar' )
		->and( $variation->parent_id )->toBe( $parent->id )
		->and( $variation->is_custom )->toBeTrue()
		->and( $variation->status )->toBe( 'draft' );
} );

it( 'creates a variation with overrides', function (): void {
	$parent = Template::create( [
		'name'                  => 'Base',
		'slug'                  => 'base',
		'content'               => [ [ 'type' => 'heading' ] ],
		'content_area_settings' => [ 'max_width' => 'container', 'padding' => 'large' ],
	] );

	$variation = $parent->createVariation( 'base-wide', 'Base Wide', [
		'content_area_settings' => [ 'max_width' => 'full', 'padding' => 'none' ],
		'status'                => 'active',
	] );

	expect( $variation->content_area_settings )->toEqual( [ 'max_width' => 'full', 'padding' => 'none' ] )
		->and( $variation->status )->toBe( 'active' )
		->and( $variation->parent_id )->toBe( $parent->id );
} );

it( 'identifies a variation correctly', function (): void {
	$parent = Template::create( [
		'name'    => 'Parent',
		'slug'    => 'parent',
		'content' => [],
	] );

	$variation = $parent->createVariation( 'child', 'Child' );

	expect( $parent->isVariation() )->toBeFalse()
		->and( $variation->isVariation() )->toBeTrue();
} );

it( 'loads parent relationship', function (): void {
	$parent = Template::create( [
		'name'    => 'Parent',
		'slug'    => 'parent',
		'content' => [],
	] );

	$variation = $parent->createVariation( 'child', 'Child' );

	$loadedParent = $variation->parent;

	expect( $loadedParent )->toBeInstanceOf( Template::class )
		->and( $loadedParent->id )->toBe( $parent->id );
} );

it( 'loads variations relationship', function (): void {
	$parent = Template::create( [
		'name'    => 'Parent',
		'slug'    => 'parent',
		'content' => [],
	] );

	$parent->createVariation( 'var-one', 'Variation One' );
	$parent->createVariation( 'var-two', 'Variation Two' );

	$variations = $parent->variations;

	expect( $variations )->toHaveCount( 2 );
} );

it( 'scopes base templates only', function (): void {
	$parent = Template::create( [
		'name'    => 'Parent',
		'slug'    => 'parent',
		'content' => [],
	] );

	$parent->createVariation( 'child', 'Child' );

	$baseTemplates = Template::baseTemplates()->get();

	expect( $baseTemplates )->toHaveCount( 1 )
		->and( $baseTemplates->first()->slug )->toBe( 'parent' );
} );

it( 'scopes variations of a specific parent', function (): void {
	$parentA = Template::create( [
		'name'    => 'Parent A',
		'slug'    => 'parent-a',
		'content' => [],
	] );

	$parentB = Template::create( [
		'name'    => 'Parent B',
		'slug'    => 'parent-b',
		'content' => [],
	] );

	$parentA->createVariation( 'var-a1', 'Variation A1' );
	$parentA->createVariation( 'var-a2', 'Variation A2' );
	$parentB->createVariation( 'var-b1', 'Variation B1' );

	$variationsOfA = Template::variationsOf( $parentA->id )->get();

	expect( $variationsOfA )->toHaveCount( 2 );
} );

it( 'resolves own content for base templates', function (): void {
	$template = Template::create( [
		'name'    => 'Base',
		'slug'    => 'base',
		'content' => [ [ 'type' => 'heading' ], [ 'type' => 'paragraph' ] ],
	] );

	expect( $template->resolveContent() )->toEqual( [
		[ 'type' => 'heading' ],
		[ 'type' => 'paragraph' ],
	] );
} );

it( 'resolves own content for variation with content', function (): void {
	$parent = Template::create( [
		'name'    => 'Parent',
		'slug'    => 'parent',
		'content' => [ [ 'type' => 'heading' ] ],
	] );

	$variation = $parent->createVariation( 'child', 'Child', [
		'content' => [ [ 'type' => 'paragraph' ] ],
	] );

	expect( $variation->resolveContent() )->toEqual( [ [ 'type' => 'paragraph' ] ] );
} );

it( 'falls back to parent content for variation without content', function (): void {
	$parent = Template::create( [
		'name'    => 'Parent',
		'slug'    => 'parent',
		'content' => [ [ 'type' => 'heading' ], [ 'type' => 'image' ] ],
	] );

	$variation = $parent->createVariation( 'child', 'Child', [
		'content' => [],
	] );

	expect( $variation->resolveContent() )->toEqual( [
		[ 'type' => 'heading' ],
		[ 'type' => 'image' ],
	] );
} );

it( 'merges content area settings from parent for variations', function (): void {
	$parent = Template::create( [
		'name'                  => 'Parent',
		'slug'                  => 'parent',
		'content'               => [],
		'content_area_settings' => [ 'max_width' => 'container', 'padding' => 'large' ],
	] );

	$variation = $parent->createVariation( 'child', 'Child', [
		'content_area_settings' => [ 'padding' => 'none' ],
	] );

	$resolved = $variation->resolveContentAreaSettings();

	expect( $resolved )->toEqual( [
		'max_width' => 'container',
		'padding'   => 'none',
	] );
} );

it( 'merges styles from parent for variations', function (): void {
	$parent = Template::create( [
		'name'    => 'Parent',
		'slug'    => 'parent',
		'content' => [],
		'styles'  => [ 'color' => '#333', 'font' => 'serif' ],
	] );

	$variation = $parent->createVariation( 'child', 'Child', [
		'styles' => [ 'color' => '#666' ],
	] );

	$resolved = $variation->resolveStyles();

	expect( $resolved )->toEqual( [
		'color' => '#666',
		'font'  => 'serif',
	] );
} );

it( 'creates a variation via the template manager', function (): void {
	$parent = Template::create( [
		'name'    => 'Source',
		'slug'    => 'source',
		'content' => [ [ 'type' => 'heading' ] ],
	] );

	$variation = $this->manager->createVariation( $parent, 'source-wide', 'Source Wide' );

	expect( $variation )->toBeInstanceOf( Template::class )
		->and( $variation->slug )->toBe( 'source-wide' )
		->and( $variation->parent_id )->toBe( $parent->id )
		->and( $variation->is_custom )->toBeTrue();
} );

it( 'gets variations of a template via the manager', function (): void {
	$parent = Template::create( [
		'name'    => 'Parent',
		'slug'    => 'parent',
		'content' => [],
	] );

	$parent->createVariation( 'var-1', 'Var 1' );
	$parent->createVariation( 'var-2', 'Var 2' );

	$variations = $this->manager->variationsOf( $parent->id );

	expect( $variations )->toHaveCount( 2 );
} );

it( 'gets all base templates via the manager', function (): void {
	$parent = Template::create( [
		'name'    => 'Parent',
		'slug'    => 'parent',
		'content' => [],
	] );

	$parent->createVariation( 'child', 'Child' );

	$baseTemplates = $this->manager->allBaseTemplates();

	expect( $baseTemplates )->toHaveKey( 'parent' )
		->and( $baseTemplates )->not->toHaveKey( 'child' );
} );

it( 'defaults user_id to null for variations', function (): void {
	$userId = DB::table( 'users' )->insertGetId( [
		'name'  => 'Parent Owner',
		'email' => 'parent-owner@example.com',
	] );

	$parent = Template::create( [
		'name'    => 'Parent',
		'slug'    => 'parent-uid',
		'content' => [],
		'user_id' => $userId,
	] );

	$variation = $parent->createVariation( 'child-uid', 'Child' );

	expect( $variation->user_id )->toBeNull();
} );

it( 'allows user_id override for variations', function (): void {
	$userId = DB::table( 'users' )->insertGetId( [
		'name'  => 'Variation Owner',
		'email' => 'variation-owner@example.com',
	] );

	$parent = Template::create( [
		'name'    => 'Parent',
		'slug'    => 'parent-uid-override',
		'content' => [],
	] );

	$variation = $parent->createVariation( 'child-uid-override', 'Child', [
		'user_id' => $userId,
	] );

	expect( $variation->user_id )->toBe( $userId );
} );

it( 'strips forbidden overrides in manager createVariation', function (): void {
	$parent = Template::create( [
		'name'    => 'Parent',
		'slug'    => 'parent-strip',
		'content' => [],
	] );

	$variation = $this->manager->createVariation( $parent, 'child-strip', 'Child', [
		'parent_id' => 9999,
		'id'        => 9999,
		'status'    => 'active',
	] );

	expect( $variation->parent_id )->toBe( $parent->id )
		->and( $variation->id )->not->toBe( 9999 )
		->and( $variation->status )->toBe( 'active' );
} );

it( 'resolves content recursively through multi-level variations', function (): void {
	$grandparent = Template::create( [
		'name'    => 'Grandparent',
		'slug'    => 'grandparent',
		'content' => [ [ 'type' => 'heading' ], [ 'type' => 'paragraph' ] ],
	] );

	$parent = $grandparent->createVariation( 'parent-multi', 'Parent', [
		'content' => [],
	] );

	$child = $parent->createVariation( 'child-multi', 'Child', [
		'content' => [],
	] );

	expect( $child->resolveContent() )->toEqual( [
		[ 'type' => 'heading' ],
		[ 'type' => 'paragraph' ],
	] );
} );

it( 'resolves settings recursively through multi-level variations', function (): void {
	$grandparent = Template::create( [
		'name'                  => 'Grandparent',
		'slug'                  => 'grandparent-settings',
		'content'               => [],
		'content_area_settings' => [ 'max_width' => 'container', 'padding' => 'large' ],
		'styles'                => [ 'color' => '#000', 'font' => 'serif' ],
	] );

	$parent = $grandparent->createVariation( 'parent-settings', 'Parent', [
		'content_area_settings' => [ 'padding' => 'medium' ],
		'styles'                => [ 'font' => 'sans-serif' ],
	] );

	$child = $parent->createVariation( 'child-settings', 'Child', [
		'content_area_settings' => [ 'max_width' => 'full' ],
		'styles'                => [ 'color' => '#333' ],
	] );

	expect( $child->resolveContentAreaSettings() )->toEqual( [
		'max_width' => 'full',
		'padding'   => 'medium',
	] )
		->and( $child->resolveStyles() )->toEqual( [
			'color' => '#333',
			'font'  => 'sans-serif',
		] );
} );

it( 'sanitizes forbidden overrides in model createVariation', function (): void {
	$parent = Template::create( [
		'name'    => 'Parent',
		'slug'    => 'parent-model-sanitize',
		'content' => [],
	] );

	$variation = $parent->createVariation( 'child-model-sanitize', 'Child', [
		'parent_id' => 9999,
		'id'        => 9999,
	] );

	expect( $variation->parent_id )->toBe( $parent->id )
		->and( $variation->id )->not->toBe( 9999 );
} );

it( 'materializes resolved fields and nulls parent_id when parent is deleted', function (): void {
	$parent = Template::create( [
		'name'                  => 'Parent',
		'slug'                  => 'parent',
		'content'               => [ [ 'type' => 'heading' ] ],
		'content_area_settings' => [ 'max_width' => 'container', 'padding' => 'large' ],
		'styles'                => [ 'color' => '#333' ],
	] );

	$variation = $parent->createVariation( 'child', 'Child', [
		'content_area_settings' => [ 'padding' => 'none' ],
	] );

	expect( $variation->content )->toBeEmpty();

	$parent->delete();

	$variation->refresh();

	expect( $variation->parent_id )->toBeNull()
		->and( $variation->isVariation() )->toBeFalse()
		->and( $variation->content )->toEqual( [ [ 'type' => 'heading' ] ] )
		->and( $variation->content_area_settings )->toEqual( [
			'max_width' => 'container',
			'padding'   => 'none',
		] )
		->and( $variation->styles )->toEqual( [ 'color' => '#333' ] );
} );

it( 'handles cycle detection in resolveContent', function (): void {
	$a = Template::create( [
		'name'    => 'A',
		'slug'    => 'cycle-a',
		'content' => [],
	] );

	$b = $a->createVariation( 'cycle-b', 'B' );

	// Manually create a cycle: A -> B -> A
	Template::withoutEvents( function () use ( $a, $b ): void {
		$a->update( [ 'parent_id' => $b->id ] );
	} );

	$a->refresh();

	expect( $a->resolveContent() )->toBeEmpty()
		->and( $a->resolveContentAreaSettings() )->toBeEmpty()
		->and( $a->resolveStyles() )->toBeEmpty();
} );

it( 'creates variation with empty content to preserve inheritance', function (): void {
	$parent = Template::create( [
		'name'                  => 'Parent',
		'slug'                  => 'parent-inherit',
		'content'               => [ [ 'type' => 'heading' ] ],
		'content_area_settings' => [ 'max_width' => 'container' ],
		'styles'                => [ 'font' => 'serif' ],
	] );

	$variation = $parent->createVariation( 'child-inherit', 'Child' );

	expect( $variation->content )->toBeEmpty()
		->and( $variation->content_area_settings )->toBeEmpty()
		->and( $variation->styles )->toBeEmpty()
		->and( $variation->resolveContent() )->toEqual( [ [ 'type' => 'heading' ] ] )
		->and( $variation->resolveContentAreaSettings() )->toEqual( [ 'max_width' => 'container' ] )
		->and( $variation->resolveStyles() )->toEqual( [ 'font' => 'serif' ] );
} );
