<?php

/**
 * Template Editor Page Livewire Component Tests.
 *
 * Tests the TemplateEditorPage Livewire component's mount, save, and
 * authorization logic. Render tests are skipped for the full editor
 * view since the BladeUI Icons manifest is not available in the
 * package test environment; visual rendering is verified via the
 * route integration tests and manual testing.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Livewire\SiteEditor
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\TemplateEditorPage;
use ArtisanPackUI\VisualEditor\Models\Template;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses( RefreshDatabase::class );

/**
 * Create a test user for template editor tests.
 *
 * @return Authenticatable
 */
function createTemplateEditorTestUser(): Authenticatable
{
	$email = 'template-editor-' . Str::random( 8 ) . '@example.com';

	$id = DB::table( 'users' )->insertGetId( [
		'name'       => 'Editor User',
		'email'      => $email,
		'created_at' => now(),
		'updated_at' => now(),
	] );

	$user        = new class () extends Authenticatable {
		protected $table = 'users';
	};
	$user->id    = $id;
	$user->name  = 'Editor User';
	$user->email = $email;

	return $user;
}

/**
 * Create a test template for editor tests.
 *
 * @param array<string, mixed> $overrides Attribute overrides.
 *
 * @return Template
 */
function createEditorTestTemplate( array $overrides = [] ): Template
{
	return Template::create( array_merge( [
		'name'    => 'Test Template',
		'slug'    => 'test-template-' . Str::random( 6 ),
		'content' => [],
		'type'    => 'page',
		'status'  => 'active',
	], $overrides ) );
}

test( 'template editor page mounts in create mode with default settings', function (): void {
	$component = new TemplateEditorPage();
	$component->mount( null );

	expect( $component->isCreateMode )->toBeTrue();
	expect( $component->template )->toBeNull();
	expect( $component->initialBlocks )->toBe( [] );
	expect( $component->templateSettings['name'] )->toBe( '' );
	expect( $component->templateSettings['slug'] )->toBe( '' );
	expect( $component->templateSettings['type'] )->toBe( 'page' );
	expect( $component->templateSettings['contentType'] )->toBe( '' );
	expect( $component->templateSettings['description'] )->toBe( '' );
	expect( $component->templateSettings['status'] )->toBe( 'draft' );
} );

test( 'template editor page mounts in edit mode with existing template', function (): void {
	$template = createEditorTestTemplate( [ 'name' => 'Blog Page', 'slug' => 'blog-page' ] );

	$component = new TemplateEditorPage();
	$component->mount( 'blog-page' );

	expect( $component->isCreateMode )->toBeFalse();
	expect( $component->template )->not->toBeNull();
	expect( $component->template->id )->toBe( $template->id );
} );

test( 'template editor page loads initial blocks from template', function (): void {
	$blocks = [
		[ 'id' => 'block-1', 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ] ],
	];

	createEditorTestTemplate( [ 'slug' => 'with-blocks', 'content' => $blocks ] );

	$component = new TemplateEditorPage();
	$component->mount( 'with-blocks' );

	expect( $component->initialBlocks )->toBe( $blocks );
} );

test( 'template editor page loads template settings from template', function (): void {
	createEditorTestTemplate( [
		'name'             => 'Single Post',
		'slug'             => 'single-post',
		'type'             => 'post',
		'for_content_type' => 'posts',
		'description'      => 'A single post template.',
		'status'           => 'draft',
	] );

	$component = new TemplateEditorPage();
	$component->mount( 'single-post' );

	expect( $component->templateSettings['name'] )->toBe( 'Single Post' );
	expect( $component->templateSettings['slug'] )->toBe( 'single-post' );
	expect( $component->templateSettings['type'] )->toBe( 'post' );
	expect( $component->templateSettings['contentType'] )->toBe( 'posts' );
	expect( $component->templateSettings['description'] )->toBe( 'A single post template.' );
	expect( $component->templateSettings['status'] )->toBe( 'draft' );
} );

test( 'template editor page can save a new template', function (): void {
	$user = createTemplateEditorTestUser();
	$this->actingAs( $user );

	$component = new TemplateEditorPage();
	$component->mount( null );

	$component->save(
		blocks: [ [ 'id' => 'b1', 'type' => 'paragraph', 'attributes' => [] ] ],
		settings: [
			'name'        => 'New Blog',
			'slug'        => 'new-blog',
			'type'        => 'page',
			'contentType' => '',
			'description' => 'A new blog template.',
			'status'      => 'active',
		],
	);

	expect( $component->isCreateMode )->toBeFalse();
	expect( $component->template )->not->toBeNull();

	$template = Template::where( 'slug', 'new-blog' )->first();
	expect( $template )->not->toBeNull();
	expect( $template->name )->toBe( 'New Blog' );
	expect( $template->type )->toBe( 'page' );
	expect( $template->description )->toBe( 'A new blog template.' );
	expect( $template->content )->toHaveCount( 1 );
	expect( $template->is_custom )->toBeTrue();
	expect( $template->user_id )->toBe( $user->id );
} );

test( 'template editor page can update an existing template', function (): void {
	$user = createTemplateEditorTestUser();
	$this->actingAs( $user );

	$template = createEditorTestTemplate( [ 'slug' => 'edit-me', 'name' => 'Original Name' ] );

	$component = new TemplateEditorPage();
	$component->mount( 'edit-me' );

	$component->save(
		blocks: [ [ 'id' => 'b1', 'type' => 'heading', 'attributes' => [] ] ],
		settings: [
			'name'        => 'Updated Name',
			'slug'        => 'edit-me',
			'type'        => 'post',
			'contentType' => 'articles',
			'description' => 'Updated desc.',
			'status'      => 'active',
		],
	);

	$template->refresh();
	expect( $template->name )->toBe( 'Updated Name' );
	expect( $template->type )->toBe( 'post' );
	expect( $template->for_content_type )->toBe( 'articles' );
	expect( $template->description )->toBe( 'Updated desc.' );
	expect( $template->content )->toHaveCount( 1 );
} );

test( 'template editor page generates unique slug on collision in create mode', function (): void {
	$user = createTemplateEditorTestUser();
	$this->actingAs( $user );

	$existing = createEditorTestTemplate( [ 'slug' => 'existing-slug', 'name' => 'Original' ] );

	$component = new TemplateEditorPage();
	$component->mount( null );

	$component->save(
		blocks: [ [ 'id' => 'b1', 'type' => 'paragraph', 'attributes' => [] ] ],
		settings: [
			'name'   => 'New Template',
			'slug'   => 'existing-slug',
			'type'   => 'page',
			'status' => 'draft',
		],
	);

	// Should create a new record with a unique slug, not update the existing one.
	expect( Template::count() )->toBe( 2 );

	$existing->refresh();
	expect( $existing->name )->toBe( 'Original' );

	expect( $component->template )->not->toBeNull();
	expect( $component->template->slug )->not->toBe( 'existing-slug' );
	expect( $component->template->slug )->toStartWith( 'existing-slug-' );
	expect( $component->template->name )->toBe( 'New Template' );
} );

test( 'template editor page aborts for nonexistent templates', function (): void {
	$component = new TemplateEditorPage();

	expect( fn () => $component->mount( 'nonexistent' ) )
		->toThrow( NotFoundHttpException::class );
} );

test( 'template editor page aborts for locked templates', function (): void {
	createEditorTestTemplate( [ 'slug' => 'locked-tpl', 'is_locked' => true ] );

	$component = new TemplateEditorPage();

	expect( fn () => $component->mount( 'locked-tpl' ) )
		->toThrow( HttpException::class );
} );

test( 'template editor create route has correct name', function (): void {
	$createUrl = route( 'visual-editor.templates.create' );
	$prefix    = config( 'artisanpack.visual-editor.site_editor.route_prefix', 'site-editor' );

	expect( $createUrl )->toContain( '/' . $prefix . '/templates/create' );
} );

test( 'template editor edit route has correct name', function (): void {
	$editUrl = route( 'visual-editor.templates.edit', [ 'slug' => 'test' ] );
	$prefix  = config( 'artisanpack.visual-editor.site_editor.route_prefix', 'site-editor' );

	expect( $editUrl )->toContain( '/' . $prefix . '/templates/test/edit' );
} );

test( 'template editor create route is forbidden without site editor permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => false );

	$user = createTemplateEditorTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.templates.create' ) )
		->assertForbidden();
} );

test( 'template editor page is forbidden without manage-templates permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );
	Gate::define( 'visual-editor.manage-templates', fn () => false );

	$user = createTemplateEditorTestUser();
	$this->actingAs( $user );

	$component = new TemplateEditorPage();

	expect( fn () => $component->mount( null ) )
		->toThrow( AuthorizationException::class );
} );

test( 'template editor save requires authentication', function (): void {
	$component = new TemplateEditorPage();
	$component->mount( null );

	expect( fn () => $component->save( blocks: [], settings: [] ) )
		->toThrow( HttpException::class );
} );
