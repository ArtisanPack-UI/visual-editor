<?php

/**
 * Part Editor Page Livewire Component Tests.
 *
 * Tests the PartEditorPage Livewire component's mount, save, and
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

use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\PartEditorPage;
use ArtisanPackUI\VisualEditor\Models\TemplatePart;
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
 * Create a test user for part editor tests.
 *
 * @return Authenticatable
 */
function createPartEditorTestUser(): Authenticatable
{
	$email = 'part-editor-' . Str::random( 8 ) . '@example.com';

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
 * Create a test template part for editor tests.
 *
 * @param array<string, mixed> $overrides Attribute overrides.
 *
 * @return TemplatePart
 */
function createEditorTestPart( array $overrides = [] ): TemplatePart
{
	return TemplatePart::create( array_merge( [
		'name'    => 'Test Part',
		'slug'    => 'test-part-' . Str::random( 6 ),
		'area'    => 'header',
		'content' => [],
		'status'  => 'active',
	], $overrides ) );
}

test( 'part editor page mounts in create mode with default settings', function (): void {
	$component = new PartEditorPage();
	$component->mount( null );

	expect( $component->isCreateMode )->toBeTrue();
	expect( $component->part )->toBeNull();
	expect( $component->initialBlocks )->toBe( [] );
	expect( $component->partSettings['name'] )->toBe( '' );
	expect( $component->partSettings['slug'] )->toBe( '' );
	expect( $component->partSettings['area'] )->toBe( 'custom' );
	expect( $component->partSettings['description'] )->toBe( '' );
	expect( $component->partSettings['status'] )->toBe( 'draft' );
} );

test( 'part editor page mounts in edit mode with existing part', function (): void {
	$part = createEditorTestPart( [ 'name' => 'Main Header', 'slug' => 'main-header' ] );

	$component = new PartEditorPage();
	$component->mount( 'main-header' );

	expect( $component->isCreateMode )->toBeFalse();
	expect( $component->part )->not->toBeNull();
	expect( $component->part->id )->toBe( $part->id );
} );

test( 'part editor page loads initial blocks from part', function (): void {
	$blocks = [
		[ 'id' => 'block-1', 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ] ],
	];

	createEditorTestPart( [ 'slug' => 'with-blocks', 'content' => $blocks ] );

	$component = new PartEditorPage();
	$component->mount( 'with-blocks' );

	expect( $component->initialBlocks )->toBe( $blocks );
} );

test( 'part editor page loads part settings from part', function (): void {
	createEditorTestPart( [
		'name'        => 'Site Footer',
		'slug'        => 'site-footer',
		'area'        => 'footer',
		'description' => 'Footer description',
		'status'      => 'draft',
	] );

	$component = new PartEditorPage();
	$component->mount( 'site-footer' );

	expect( $component->partSettings['name'] )->toBe( 'Site Footer' );
	expect( $component->partSettings['slug'] )->toBe( 'site-footer' );
	expect( $component->partSettings['area'] )->toBe( 'footer' );
	expect( $component->partSettings['description'] )->toBe( 'Footer description' );
	expect( $component->partSettings['status'] )->toBe( 'draft' );
} );

test( 'part editor page can save a new part', function (): void {
	$user = createPartEditorTestUser();
	$this->actingAs( $user );

	$component = new PartEditorPage();
	$component->mount( null );

	$component->save(
		blocks: [ [ 'id' => 'b1', 'type' => 'paragraph', 'attributes' => [] ] ],
		settings: [
			'name'        => 'New Header',
			'slug'        => 'new-header',
			'area'        => 'header',
			'description' => 'A new header part.',
			'status'      => 'active',
		],
	);

	expect( $component->isCreateMode )->toBeFalse();
	expect( $component->part )->not->toBeNull();

	$part = TemplatePart::where( 'slug', 'new-header' )->first();
	expect( $part )->not->toBeNull();
	expect( $part->name )->toBe( 'New Header' );
	expect( $part->area )->toBe( 'header' );
	expect( $part->content )->toHaveCount( 1 );
} );

test( 'part editor page can update an existing part', function (): void {
	$user = createPartEditorTestUser();
	$this->actingAs( $user );

	$part = createEditorTestPart( [ 'slug' => 'edit-me', 'name' => 'Original Name' ] );

	$component = new PartEditorPage();
	$component->mount( 'edit-me' );

	$component->save(
		blocks: [ [ 'id' => 'b1', 'type' => 'heading', 'attributes' => [] ] ],
		settings: [
			'name'        => 'Updated Name',
			'slug'        => 'edit-me',
			'area'        => 'footer',
			'description' => 'Updated desc.',
			'status'      => 'active',
		],
	);

	$part->refresh();
	expect( $part->name )->toBe( 'Updated Name' );
	expect( $part->area )->toBe( 'footer' );
	expect( $part->content )->toHaveCount( 1 );
} );

test( 'part editor page updates existing DB part when slug conflicts on create', function (): void {
	$user = createPartEditorTestUser();
	$this->actingAs( $user );

	$existing = createEditorTestPart( [ 'slug' => 'existing-slug', 'name' => 'Original' ] );

	$component = new PartEditorPage();
	$component->mount( null );

	$component->save(
		blocks: [ [ 'id' => 'b1', 'type' => 'paragraph', 'attributes' => [] ] ],
		settings: [
			'name'   => 'Updated Name',
			'slug'   => 'existing-slug',
			'area'   => 'custom',
			'status' => 'draft',
		],
	);

	// Should update the existing record, not create a new one.
	expect( TemplatePart::count() )->toBe( 1 );

	$existing->refresh();
	expect( $existing->name )->toBe( 'Updated Name' );
	expect( $existing->content )->toHaveCount( 1 );
} );

test( 'part editor page aborts with 403 for locked parts', function (): void {
	createEditorTestPart( [ 'slug' => 'locked-part', 'is_locked' => true ] );

	$component = new PartEditorPage();

	try {
		$component->mount( 'locked-part' );
		$this->fail( 'Expected HttpException was not thrown.' );
	} catch ( HttpException $e ) {
		expect( $e->getStatusCode() )->toBe( 403 );
	}
} );

test( 'part editor page aborts for nonexistent parts', function (): void {
	$component = new PartEditorPage();

	expect( fn () => $component->mount( 'nonexistent' ) )
		->toThrow( NotFoundHttpException::class );
} );

test( 'part editor create route has correct name', function (): void {
	$createUrl = route( 'visual-editor.template-parts.create' );
	$prefix    = config( 'artisanpack.visual-editor.site_editor.route_prefix', 'site-editor' );

	expect( $createUrl )->toContain( '/' . $prefix . '/parts/create' );
} );

test( 'part editor edit route has correct name', function (): void {
	$editUrl = route( 'visual-editor.template-parts.edit', [ 'slug' => 'test' ] );
	$prefix  = config( 'artisanpack.visual-editor.site_editor.route_prefix', 'site-editor' );

	expect( $editUrl )->toContain( '/' . $prefix . '/parts/test/edit' );
} );

test( 'part editor create route is forbidden without site editor permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => false );

	$user = createPartEditorTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.template-parts.create' ) )
		->assertForbidden();
} );

test( 'part editor page is forbidden without manage-parts permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );
	Gate::define( 'visual-editor.manage-parts', fn () => false );

	$user = createPartEditorTestUser();
	$this->actingAs( $user );

	$component = new PartEditorPage();

	expect( fn () => $component->mount( null ) )
		->toThrow( AuthorizationException::class );
} );
