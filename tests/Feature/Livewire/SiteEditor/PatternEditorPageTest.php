<?php

/**
 * Pattern Editor Page Livewire Component Tests.
 *
 * Tests the PatternEditorPage Livewire component's mount, save, and
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

use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\PatternEditorPage;
use ArtisanPackUI\VisualEditor\Models\Pattern;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses( RefreshDatabase::class );

/**
 * Create a test user for pattern editor tests.
 *
 * @return Authenticatable
 */
function createPatternEditorTestUser(): Authenticatable
{
	$email = 'pattern-editor-' . Str::random( 8 ) . '@example.com';

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
 * Create a test pattern for editor tests.
 *
 * @param array<string, mixed> $overrides Attribute overrides.
 *
 * @return Pattern
 */
function createEditorTestPattern( array $overrides = [] ): Pattern
{
	return Pattern::create( array_merge( [
		'name'      => 'Test Pattern',
		'slug'      => 'test-pattern-' . Str::random( 6 ),
		'blocks'    => [],
		'category'  => 'text',
		'status'    => 'active',
		'is_synced' => false,
	], $overrides ) );
}

test( 'pattern editor page mounts in create mode with default settings', function (): void {
	$component = new PatternEditorPage();
	$component->mount( null );

	expect( $component->isCreateMode )->toBeTrue();
	expect( $component->pattern )->toBeNull();
	expect( $component->initialBlocks )->toBe( [] );
	expect( $component->patternSettings['name'] )->toBe( '' );
	expect( $component->patternSettings['slug'] )->toBe( '' );
	expect( $component->patternSettings['category'] )->toBe( '' );
	expect( $component->patternSettings['description'] )->toBe( '' );
	expect( $component->patternSettings['keywords'] )->toBe( '' );
	expect( $component->patternSettings['status'] )->toBe( 'draft' );
	expect( $component->patternSettings['isSynced'] )->toBeFalse();
} );

test( 'pattern editor page mounts in edit mode with existing pattern', function (): void {
	$pattern = createEditorTestPattern( [ 'name' => 'Hero Banner', 'slug' => 'hero-banner' ] );

	$component = new PatternEditorPage();
	$component->mount( 'hero-banner' );

	expect( $component->isCreateMode )->toBeFalse();
	expect( $component->pattern )->not->toBeNull();
	expect( $component->pattern->id )->toBe( $pattern->id );
} );

test( 'pattern editor page loads initial blocks from pattern', function (): void {
	$blocks = [
		[ 'id' => 'block-1', 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ] ],
	];

	createEditorTestPattern( [ 'slug' => 'with-blocks', 'blocks' => $blocks ] );

	$component = new PatternEditorPage();
	$component->mount( 'with-blocks' );

	expect( $component->initialBlocks )->toBe( $blocks );
} );

test( 'pattern editor page loads pattern settings from pattern', function (): void {
	createEditorTestPattern( [
		'name'        => 'CTA Section',
		'slug'        => 'cta-section',
		'category'    => 'call-to-action',
		'description' => 'A call to action section.',
		'keywords'    => 'hero, banner, signup',
		'status'      => 'draft',
		'is_synced'   => true,
	] );

	$component = new PatternEditorPage();
	$component->mount( 'cta-section' );

	expect( $component->patternSettings['name'] )->toBe( 'CTA Section' );
	expect( $component->patternSettings['slug'] )->toBe( 'cta-section' );
	expect( $component->patternSettings['category'] )->toBe( 'call-to-action' );
	expect( $component->patternSettings['description'] )->toBe( 'A call to action section.' );
	expect( $component->patternSettings['keywords'] )->toBe( 'hero, banner, signup' );
	expect( $component->patternSettings['status'] )->toBe( 'draft' );
	expect( $component->patternSettings['isSynced'] )->toBeTrue();
} );

test( 'pattern editor page can save a new pattern', function (): void {
	$user = createPatternEditorTestUser();
	$this->actingAs( $user );

	$component = new PatternEditorPage();
	$component->mount( null );

	$component->save(
		blocks: [ [ 'id' => 'b1', 'type' => 'paragraph', 'attributes' => [] ] ],
		settings: [
			'name'        => 'New Hero',
			'slug'        => 'new-hero',
			'category'    => 'header',
			'description' => 'A new hero pattern.',
			'keywords'    => 'hero, banner',
			'status'      => 'active',
			'isSynced'    => false,
		],
	);

	expect( $component->isCreateMode )->toBeFalse();
	expect( $component->pattern )->not->toBeNull();

	$pattern = Pattern::where( 'slug', 'new-hero' )->first();
	expect( $pattern )->not->toBeNull();
	expect( $pattern->name )->toBe( 'New Hero' );
	expect( $pattern->category )->toBe( 'header' );
	expect( $pattern->description )->toBe( 'A new hero pattern.' );
	expect( $pattern->keywords )->toBe( 'hero, banner' );
	expect( $pattern->blocks )->toHaveCount( 1 );
	expect( $pattern->is_synced )->toBeFalse();
	expect( $pattern->user_id )->toBe( $user->id );
} );

test( 'pattern editor page can save a synced pattern', function (): void {
	$user = createPatternEditorTestUser();
	$this->actingAs( $user );

	$component = new PatternEditorPage();
	$component->mount( null );

	$component->save(
		blocks: [ [ 'id' => 'b1', 'type' => 'heading', 'attributes' => [] ] ],
		settings: [
			'name'     => 'Synced Header',
			'slug'     => 'synced-header',
			'category' => 'header',
			'status'   => 'active',
			'isSynced' => true,
		],
	);

	$pattern = Pattern::where( 'slug', 'synced-header' )->first();
	expect( $pattern )->not->toBeNull();
	expect( $pattern->is_synced )->toBeTrue();
} );

test( 'pattern editor page can update an existing pattern', function (): void {
	$user = createPatternEditorTestUser();
	$this->actingAs( $user );

	$pattern = createEditorTestPattern( [ 'slug' => 'edit-me', 'name' => 'Original Name' ] );

	$component = new PatternEditorPage();
	$component->mount( 'edit-me' );

	$component->save(
		blocks: [ [ 'id' => 'b1', 'type' => 'heading', 'attributes' => [] ] ],
		settings: [
			'name'        => 'Updated Name',
			'slug'        => 'edit-me',
			'category'    => 'footer',
			'description' => 'Updated desc.',
			'status'      => 'active',
			'isSynced'    => true,
		],
	);

	$pattern->refresh();
	expect( $pattern->name )->toBe( 'Updated Name' );
	expect( $pattern->category )->toBe( 'footer' );
	expect( $pattern->description )->toBe( 'Updated desc.' );
	expect( $pattern->is_synced )->toBeTrue();
	expect( $pattern->blocks )->toHaveCount( 1 );
} );

test( 'pattern editor page generates unique slug on collision in create mode', function (): void {
	$user = createPatternEditorTestUser();
	$this->actingAs( $user );

	$existing = createEditorTestPattern( [ 'slug' => 'existing-slug', 'name' => 'Original' ] );

	$component = new PatternEditorPage();
	$component->mount( null );

	$component->save(
		blocks: [ [ 'id' => 'b1', 'type' => 'paragraph', 'attributes' => [] ] ],
		settings: [
			'name'     => 'New Pattern',
			'slug'     => 'existing-slug',
			'category' => 'text',
			'status'   => 'draft',
		],
	);

	// Should create a new record with a unique slug, not update the existing one.
	expect( Pattern::count() )->toBe( 2 );

	$existing->refresh();
	expect( $existing->name )->toBe( 'Original' );

	expect( $component->pattern )->not->toBeNull();
	expect( $component->pattern->slug )->not->toBe( 'existing-slug' );
	expect( $component->pattern->slug )->toStartWith( 'existing-slug-' );
	expect( $component->pattern->name )->toBe( 'New Pattern' );
} );

test( 'pattern editor page aborts for nonexistent patterns', function (): void {
	$component = new PatternEditorPage();

	expect( fn () => $component->mount( 'nonexistent' ) )
		->toThrow( NotFoundHttpException::class );
} );

test( 'pattern editor create route has correct name', function (): void {
	$createUrl = route( 'visual-editor.patterns.create' );
	$prefix    = config( 'artisanpack.visual-editor.site_editor.route_prefix', 'site-editor' );

	expect( $createUrl )->toContain( '/' . $prefix . '/patterns/create' );
} );

test( 'pattern editor edit route has correct name', function (): void {
	$editUrl = route( 'visual-editor.patterns.edit', [ 'slug' => 'test' ] );
	$prefix  = config( 'artisanpack.visual-editor.site_editor.route_prefix', 'site-editor' );

	expect( $editUrl )->toContain( '/' . $prefix . '/patterns/test/edit' );
} );

test( 'pattern editor create route is forbidden without site editor permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => false );

	$user = createPatternEditorTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.patterns.create' ) )
		->assertForbidden();
} );

test( 'pattern editor page is forbidden without manage-patterns permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );
	Gate::define( 'visual-editor.manage-patterns', fn () => false );

	$user = createPatternEditorTestUser();
	$this->actingAs( $user );

	$component = new PatternEditorPage();

	expect( fn () => $component->mount( null ) )
		->toThrow( AuthorizationException::class );
} );
