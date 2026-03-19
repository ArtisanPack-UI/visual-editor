<?php

/**
 * TemplateAssignmentManager Service Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Services
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Template;
use ArtisanPackUI\VisualEditor\Models\TemplateAssignment;
use ArtisanPackUI\VisualEditor\Services\TemplateAssignmentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );
	$this->manager = app( 'visual-editor.template-assignments' );
} );

it( 'assigns a template to a content type', function (): void {
	$template = Template::create( [
		'name'    => 'Blog Template',
		'slug'    => 'blog-template',
		'content' => [],
	] );

	$assignment = $this->manager->assign( 'post', $template->id );

	expect( $assignment )->toBeInstanceOf( TemplateAssignment::class )
		->and( $assignment->content_type )->toBe( 'post' )
		->and( $assignment->template_id )->toBe( $template->id );
} );

it( 'assigns with a user id', function (): void {
	DB::table( 'users' )->insert( [
		'id'    => 1,
		'name'  => 'Admin',
		'email' => 'admin@test.com',
	] );

	$template = Template::create( [
		'name'    => 'Blog Template',
		'slug'    => 'blog-template',
		'content' => [],
	] );

	$assignment = $this->manager->assign( 'post', $template->id, 1 );

	expect( $assignment->user_id )->toBe( 1 );
} );

it( 'updates existing assignment when reassigning', function (): void {
	$template1 = Template::create( [
		'name'    => 'Template 1',
		'slug'    => 'template-1',
		'content' => [],
	] );

	$template2 = Template::create( [
		'name'    => 'Template 2',
		'slug'    => 'template-2',
		'content' => [],
	] );

	$this->manager->assign( 'post', $template1->id );
	$assignment = $this->manager->assign( 'post', $template2->id );

	expect( $assignment->template_id )->toBe( $template2->id )
		->and( TemplateAssignment::where( 'content_type', 'post' )->count() )->toBe( 1 );
} );

it( 'throws when assigning nonexistent template', function (): void {
	expect( fn () => $this->manager->assign( 'post', 9999 ) )
		->toThrow( InvalidArgumentException::class );
} );

it( 'unassigns a content type', function (): void {
	$template = Template::create( [
		'name'    => 'Template',
		'slug'    => 'template',
		'content' => [],
	] );

	$this->manager->assign( 'post', $template->id );

	$result = $this->manager->unassign( 'post' );

	expect( $result )->toBeTrue()
		->and( TemplateAssignment::where( 'content_type', 'post' )->exists() )->toBeFalse();
} );

it( 'returns false when unassigning nonexistent assignment', function (): void {
	expect( $this->manager->unassign( 'nonexistent' ) )->toBeFalse();
} );

it( 'gets default template for a content type', function (): void {
	$template = Template::create( [
		'name'    => 'Blog Default',
		'slug'    => 'blog-default',
		'content' => [],
	] );

	$this->manager->assign( 'post', $template->id );

	$default = $this->manager->defaultFor( 'post' );

	expect( $default )->toBeInstanceOf( Template::class )
		->and( $default->id )->toBe( $template->id );
} );

it( 'returns null for content type with no default', function (): void {
	expect( $this->manager->defaultFor( 'post' ) )->toBeNull();
} );

it( 'resolves page-specific template first', function (): void {
	$contentTypeTemplate = Template::create( [
		'name'    => 'Content Type Default',
		'slug'    => 'content-type-default',
		'content' => [],
	] );

	$pageTemplate = Template::create( [
		'name'    => 'Page Specific',
		'slug'    => 'page-specific',
		'content' => [],
	] );

	$this->manager->assign( 'post', $contentTypeTemplate->id );

	$resolved = $this->manager->resolveTemplate( 'post', $pageTemplate->id );

	expect( $resolved )->toBeInstanceOf( Template::class )
		->and( $resolved->slug )->toBe( 'page-specific' );
} );

it( 'resolves content type default when no page template', function (): void {
	$template = Template::create( [
		'name'    => 'Post Default',
		'slug'    => 'post-default',
		'content' => [],
	] );

	$this->manager->assign( 'post', $template->id );

	$resolved = $this->manager->resolveTemplate( 'post' );

	expect( $resolved )->toBeInstanceOf( Template::class )
		->and( $resolved->slug )->toBe( 'post-default' );
} );

it( 'resolves site default when no content type assignment', function (): void {
	$templateManager = app( 'visual-editor.templates' );
	$templateManager->clearRegistered();
	$templateManager->register( 'blank', [
		'name'    => 'Blank',
		'content' => [],
	] );

	$resolved = $this->manager->resolveTemplate( 'post' );

	expect( $resolved )->toBeArray()
		->and( $resolved['name'] )->toBe( 'Blank' );
} );

it( 'returns null when no template found at any level', function (): void {
	$templateManager = app( 'visual-editor.templates' );
	$templateManager->clearRegistered();

	config( [ 'artisanpack.visual-editor.templates.default_template' => 'nonexistent' ] );

	$resolved = $this->manager->resolveTemplate( 'post' );

	expect( $resolved )->toBeNull();
} );

it( 'skips invalid page template and falls back to content type default', function (): void {
	$template = Template::create( [
		'name'    => 'Content Type Default',
		'slug'    => 'content-type-default',
		'content' => [],
	] );

	$this->manager->assign( 'post', $template->id );

	$resolved = $this->manager->resolveTemplate( 'post', 9999 );

	expect( $resolved )->toBeInstanceOf( Template::class )
		->and( $resolved->slug )->toBe( 'content-type-default' );
} );

it( 'validates compatible template assignment', function (): void {
	$template = Template::create( [
		'name'             => 'Universal Template',
		'slug'             => 'universal',
		'content'          => [],
		'for_content_type' => null,
	] );

	expect( $this->manager->validateAssignment( $template->id, 'post' ) )->toBeTrue();
} );

it( 'validates template matching content type', function (): void {
	$template = Template::create( [
		'name'             => 'Post Template',
		'slug'             => 'post-template',
		'content'          => [],
		'for_content_type' => 'post',
	] );

	expect( $this->manager->validateAssignment( $template->id, 'post' ) )->toBeTrue();
} );

it( 'rejects incompatible template assignment', function (): void {
	$template = Template::create( [
		'name'             => 'Page Only Template',
		'slug'             => 'page-only',
		'content'          => [],
		'for_content_type' => 'page',
	] );

	expect( $this->manager->validateAssignment( $template->id, 'post' ) )->toBeFalse();
} );

it( 'returns false for nonexistent template validation', function (): void {
	expect( $this->manager->validateAssignment( 9999, 'post' ) )->toBeFalse();
} );

it( 'bulk assigns a template to entities', function (): void {
	DB::statement( 'CREATE TABLE test_posts (id INTEGER PRIMARY KEY, title TEXT, template_id INTEGER)' );
	DB::table( 'test_posts' )->insert( [
		[ 'id' => 1, 'title' => 'Post 1', 'template_id' => null ],
		[ 'id' => 2, 'title' => 'Post 2', 'template_id' => null ],
		[ 'id' => 3, 'title' => 'Post 3', 'template_id' => null ],
	] );

	$template = Template::create( [
		'name'    => 'Bulk Template',
		'slug'    => 'bulk-template',
		'content' => [],
	] );

	// Create a simple anonymous model class for testing.
	$modelClass = get_class( new class() extends Illuminate\Database\Eloquent\Model {
		public $timestamps = false;

		protected $table = 'test_posts';
	} );

	$count = $this->manager->bulkAssign( $template->id, $modelClass, [ 1, 2 ] );

	expect( $count )->toBe( 2 );

	$updated = DB::table( 'test_posts' )->whereIn( 'id', [ 1, 2 ] )->get();

	foreach ( $updated as $post ) {
		expect( $post->template_id )->toBe( $template->id );
	}

	$untouched = DB::table( 'test_posts' )->where( 'id', 3 )->first();

	expect( $untouched->template_id )->toBeNull();
} );

it( 'throws when bulk assigning nonexistent template', function (): void {
	expect( fn () => $this->manager->bulkAssign( 9999, 'App\\Models\\Post', [ 1, 2 ] ) )
		->toThrow( InvalidArgumentException::class );
} );

it( 'gets all assignments', function (): void {
	$template1 = Template::create( [
		'name'    => 'Template 1',
		'slug'    => 'template-1',
		'content' => [],
	] );

	$template2 = Template::create( [
		'name'    => 'Template 2',
		'slug'    => 'template-2',
		'content' => [],
	] );

	$this->manager->assign( 'post', $template1->id );
	$this->manager->assign( 'page', $template2->id );

	$assignments = $this->manager->allAssignments();

	expect( $assignments )->toHaveCount( 2 );
} );

it( 'gets assignment for specific content type', function (): void {
	$template = Template::create( [
		'name'    => 'Post Template',
		'slug'    => 'post-template',
		'content' => [],
	] );

	$this->manager->assign( 'post', $template->id );

	$assignment = $this->manager->getAssignment( 'post' );

	expect( $assignment )->toBeInstanceOf( TemplateAssignment::class )
		->and( $assignment->content_type )->toBe( 'post' );
} );

it( 'returns null for nonexistent assignment', function (): void {
	expect( $this->manager->getAssignment( 'nonexistent' ) )->toBeNull();
} );
