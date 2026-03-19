<?php

/**
 * Template Model Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Models
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Revision;
use ArtisanPackUI\VisualEditor\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );
} );

it( 'has fillable attributes', function (): void {
	$template = new Template();

	expect( $template->getFillable() )->toContain(
		'name',
		'slug',
		'description',
		'type',
		'for_content_type',
		'content',
		'status',
		'content_area_settings',
		'styles',
		'is_custom',
		'is_locked',
		'user_id',
	);
} );

it( 'casts content to array', function (): void {
	$content = [
		[ 'type' => 'heading', 'attributes' => [ 'content' => 'Hello' ] ],
	];

	$template = Template::create( [
		'name'    => 'Test Template',
		'slug'    => 'test-template',
		'content' => $content,
	] );

	$template->refresh();

	expect( $template->content )->toBeArray()
		->and( $template->content )->toEqual( $content );
} );

it( 'casts boolean fields correctly', function (): void {
	$template = Template::create( [
		'name'      => 'Bool Test',
		'slug'      => 'bool-test',
		'content'   => [],
		'is_custom' => true,
		'is_locked' => false,
	] );

	$template->refresh();

	expect( $template->is_custom )->toBeTrue()
		->and( $template->is_locked )->toBeFalse();
} );

it( 'casts content_area_settings and styles to array', function (): void {
	$settings = [ 'max_width' => 'container', 'padding' => 'large' ];
	$styles   = [ 'background' => '#ffffff' ];

	$template = Template::create( [
		'name'                  => 'Settings Test',
		'slug'                  => 'settings-test',
		'content'               => [],
		'content_area_settings' => $settings,
		'styles'                => $styles,
	] );

	$template->refresh();

	expect( $template->content_area_settings )->toBeArray()
		->and( $template->content_area_settings )->toEqual( $settings )
		->and( $template->styles )->toBeArray()
		->and( $template->styles )->toEqual( $styles );
} );

it( 'defaults status to active', function (): void {
	$template = Template::create( [
		'name'    => 'Default Status',
		'slug'    => 'default-status',
		'content' => [],
	] );

	$template->refresh();

	expect( $template->status )->toBe( 'active' );
} );

it( 'defaults type to page', function (): void {
	$template = Template::create( [
		'name'    => 'Default Type',
		'slug'    => 'default-type',
		'content' => [],
	] );

	$template->refresh();

	expect( $template->type )->toBe( 'page' );
} );

it( 'enforces unique slug', function (): void {
	Template::create( [
		'name'    => 'First',
		'slug'    => 'unique-slug',
		'content' => [],
	] );

	expect( fn () => Template::create( [
		'name'    => 'Second',
		'slug'    => 'unique-slug',
		'content' => [],
	] ) )->toThrow( Illuminate\Database\QueryException::class );
} );

it( 'filters by type scope', function (): void {
	Template::create( [
		'name'    => 'Page Template',
		'slug'    => 'page-template',
		'content' => [],
		'type'    => 'page',
	] );

	Template::create( [
		'name'    => 'Post Template',
		'slug'    => 'post-template',
		'content' => [],
		'type'    => 'post',
	] );

	$pages = Template::byType( 'page' )->get();

	expect( $pages )->toHaveCount( 1 )
		->and( $pages->first()->name )->toBe( 'Page Template' );
} );

it( 'filters by active scope', function (): void {
	Template::create( [
		'name'    => 'Active',
		'slug'    => 'active-template',
		'content' => [],
		'status'  => 'active',
	] );

	Template::create( [
		'name'    => 'Draft',
		'slug'    => 'draft-template',
		'content' => [],
		'status'  => 'draft',
	] );

	$active = Template::active()->get();

	expect( $active )->toHaveCount( 1 )
		->and( $active->first()->name )->toBe( 'Active' );
} );

it( 'filters by draft scope', function (): void {
	Template::create( [
		'name'    => 'Active',
		'slug'    => 'active-template',
		'content' => [],
		'status'  => 'active',
	] );

	Template::create( [
		'name'    => 'Draft',
		'slug'    => 'draft-template',
		'content' => [],
		'status'  => 'draft',
	] );

	$drafts = Template::draft()->get();

	expect( $drafts )->toHaveCount( 1 )
		->and( $drafts->first()->name )->toBe( 'Draft' );
} );

it( 'filters by content type scope including null', function (): void {
	Template::create( [
		'name'             => 'Universal',
		'slug'             => 'universal',
		'content'          => [],
		'for_content_type' => null,
	] );

	Template::create( [
		'name'             => 'Post Only',
		'slug'             => 'post-only',
		'content'          => [],
		'for_content_type' => 'post',
	] );

	Template::create( [
		'name'             => 'Page Only',
		'slug'             => 'page-only',
		'content'          => [],
		'for_content_type' => 'page',
	] );

	$postTemplates = Template::forContentType( 'post' )->get();

	expect( $postTemplates )->toHaveCount( 2 )
		->and( $postTemplates->pluck( 'slug' )->toArray() )->toContain( 'universal', 'post-only' );
} );

it( 'filters by custom scope', function (): void {
	Template::create( [
		'name'      => 'Built In',
		'slug'      => 'built-in',
		'content'   => [],
		'is_custom' => false,
	] );

	Template::create( [
		'name'      => 'Custom',
		'slug'      => 'custom',
		'content'   => [],
		'is_custom' => true,
	] );

	$custom  = Template::custom()->get();
	$builtIn = Template::builtIn()->get();

	expect( $custom )->toHaveCount( 1 )
		->and( $custom->first()->name )->toBe( 'Custom' )
		->and( $builtIn )->toHaveCount( 1 )
		->and( $builtIn->first()->name )->toBe( 'Built In' );
} );

it( 'filters by user scope', function (): void {
	DB::table( 'users' )->insert( [
		[ 'id' => 1, 'name' => 'User 1', 'email' => 'user1@test.com' ],
		[ 'id' => 2, 'name' => 'User 2', 'email' => 'user2@test.com' ],
	] );

	Template::create( [
		'name'    => 'User Template',
		'slug'    => 'user-template',
		'content' => [],
		'user_id' => 1,
	] );

	Template::create( [
		'name'    => 'Other Template',
		'slug'    => 'other-template',
		'content' => [],
		'user_id' => 2,
	] );

	$templates = Template::byUser( 1 )->get();

	expect( $templates )->toHaveCount( 1 )
		->and( $templates->first()->name )->toBe( 'User Template' );
} );

it( 'creates a revision snapshot', function (): void {
	$template = Template::create( [
		'name'    => 'Revisionable',
		'slug'    => 'revisionable',
		'content' => [ [ 'type' => 'paragraph' ] ],
	] );

	$revision = $template->createRevision( null );

	expect( $revision )->toBeInstanceOf( Revision::class )
		->and( $revision->document_type )->toBe( 'template' )
		->and( $revision->document_id )->toBe( $template->id )
		->and( $revision->blocks )->toEqual( $template->content );
} );

it( 'retrieves revisions for template', function (): void {
	$template = Template::create( [
		'name'    => 'Multi Rev',
		'slug'    => 'multi-rev',
		'content' => [ [ 'type' => 'heading' ] ],
	] );

	$template->createRevision( null );
	$template->createRevision( null );

	$revisions = $template->revisions();

	expect( $revisions )->toHaveCount( 2 );
} );

it( 'duplicates a template', function (): void {
	$template = Template::create( [
		'name'                  => 'Original',
		'slug'                  => 'original',
		'description'           => 'An original template',
		'type'                  => 'page',
		'content'               => [ [ 'type' => 'paragraph' ] ],
		'content_area_settings' => [ 'max_width' => 'container' ],
		'styles'                => [ 'background' => '#fff' ],
	] );

	$duplicate = $template->duplicate( 'original-copy' );

	expect( $duplicate->slug )->toBe( 'original-copy' )
		->and( $duplicate->name )->toContain( 'Original' )
		->and( $duplicate->content )->toEqual( $template->content )
		->and( $duplicate->status )->toBe( 'draft' )
		->and( $duplicate->is_custom )->toBeTrue()
		->and( $duplicate->is_locked )->toBeFalse();
} );

it( 'duplicates with custom name', function (): void {
	$template = Template::create( [
		'name'    => 'Original',
		'slug'    => 'original',
		'content' => [],
	] );

	$duplicate = $template->duplicate( 'my-copy', 'My Custom Copy' );

	expect( $duplicate->name )->toBe( 'My Custom Copy' );
} );
