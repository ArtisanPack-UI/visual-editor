<?php

/**
 * Template Listing Page Livewire Component Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Livewire\SiteEditor
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\TemplateListingPage;
use ArtisanPackUI\VisualEditor\Models\Template;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses( RefreshDatabase::class );

/**
 * Create a test user for template listing tests.
 *
 * @return Authenticatable
 */
function createTemplateListingTestUser(): Authenticatable
{
	$id = DB::table( 'users' )->insertGetId( [
		'name'       => 'Test User',
		'email'      => 'template-listing-test@example.com',
		'created_at' => now(),
		'updated_at' => now(),
	] );

	$user        = new class () extends Authenticatable {
		protected $table = 'users';
	};
	$user->id    = $id;
	$user->name  = 'Test User';
	$user->email = 'template-listing-test@example.com';

	return $user;
}

/**
 * Create a test template.
 *
 * @param array<string, mixed> $overrides Attribute overrides.
 *
 * @return Template
 */
function createTestTemplate( array $overrides = [] ): Template
{
	return Template::create( array_merge( [
		'name'    => 'Test Template',
		'slug'    => 'test-template-' . Illuminate\Support\Str::random( 6 ),
		'type'    => 'page',
		'content' => [],
		'status'  => 'active',
	], $overrides ) );
}

test( 'template listing page renders successfully', function (): void {
	Livewire::test( TemplateListingPage::class )
		->assertSuccessful()
		->assertSee( __( 'visual-editor::ve.template_listing_title' ) );
} );

test( 'template listing page displays description', function (): void {
	Livewire::test( TemplateListingPage::class )
		->assertSee( __( 'visual-editor::ve.template_listing_description' ) );
} );

test( 'template listing page shows templates', function (): void {
	$template = createTestTemplate( [ 'name' => 'My Page Template' ] );

	Livewire::test( TemplateListingPage::class )
		->assertSee( 'My Page Template' );
} );

test( 'template listing page can search templates', function (): void {
	createTestTemplate( [ 'name' => 'Blog Layout' ] );
	createTestTemplate( [ 'name' => 'Landing Page' ] );

	Livewire::test( TemplateListingPage::class )
		->set( 'search', 'Blog' )
		->assertSee( 'Blog Layout' )
		->assertDontSee( 'Landing Page' );
} );

test( 'template listing page can filter by status', function (): void {
	createTestTemplate( [ 'name' => 'Active Template', 'status' => 'active' ] );
	createTestTemplate( [ 'name' => 'Draft Template', 'status' => 'draft' ] );

	Livewire::test( TemplateListingPage::class )
		->set( 'filterStatus', 'draft' )
		->assertSee( 'Draft Template' )
		->assertDontSee( 'Active Template' );
} );

test( 'template listing page can filter by type', function (): void {
	createTestTemplate( [ 'name' => 'Page Template', 'type' => 'page' ] );
	createTestTemplate( [ 'name' => 'Post Template', 'type' => 'post' ] );

	Livewire::test( TemplateListingPage::class )
		->set( 'filterType', 'post' )
		->assertSee( 'Post Template' )
		->assertDontSee( 'Page Template' );
} );

test( 'template listing page can sort by name', function (): void {
	createTestTemplate( [ 'name' => 'Alpha Template' ] );
	createTestTemplate( [ 'name' => 'Zeta Template' ] );

	Livewire::test( TemplateListingPage::class )
		->assertSeeInOrder( [ 'Alpha Template', 'Zeta Template' ] );
} );

test( 'template listing page can toggle sort direction', function (): void {
	Livewire::test( TemplateListingPage::class )
		->assertSet( 'sortDirection', 'asc' )
		->call( 'sort', 'name' )
		->assertSet( 'sortDirection', 'desc' )
		->call( 'sort', 'name' )
		->assertSet( 'sortDirection', 'asc' );
} );

test( 'template listing page can switch view mode', function (): void {
	Livewire::test( TemplateListingPage::class )
		->assertSet( 'viewMode', 'table' )
		->call( 'setViewMode', 'grid' )
		->assertSet( 'viewMode', 'grid' )
		->call( 'setViewMode', 'table' )
		->assertSet( 'viewMode', 'table' );
} );

test( 'template listing page rejects invalid view mode', function (): void {
	Livewire::test( TemplateListingPage::class )
		->call( 'setViewMode', 'invalid' )
		->assertSet( 'viewMode', 'table' );
} );

test( 'template listing page can duplicate a template', function (): void {
	$template = createTestTemplate( [ 'name' => 'Original Template' ] );

	Livewire::test( TemplateListingPage::class )
		->call( 'duplicate', $template->id )
		->assertDispatched( 've-template-duplicated' );

	expect( Template::count() )->toBe( 2 );
} );

test( 'template listing page can delete a template', function (): void {
	$template = createTestTemplate();

	Livewire::test( TemplateListingPage::class )
		->call( 'delete', $template->id )
		->assertDispatched( 've-template-deleted' );

	expect( Template::count() )->toBe( 0 );
} );

test( 'template listing page cannot delete locked template', function (): void {
	$template = createTestTemplate( [ 'is_locked' => true ] );

	Livewire::test( TemplateListingPage::class )
		->call( 'delete', $template->id );

	expect( Template::count() )->toBe( 1 );
} );

test( 'template listing page can toggle lock', function (): void {
	$template = createTestTemplate( [ 'is_locked' => false ] );

	Livewire::test( TemplateListingPage::class )
		->call( 'toggleLock', $template->id );

	expect( $template->fresh()->is_locked )->toBeTrue();
} );

test( 'template listing page can bulk delete', function (): void {
	$t1 = createTestTemplate( [ 'name' => 'Template 1' ] );
	$t2 = createTestTemplate( [ 'name' => 'Template 2' ] );
	$t3 = createTestTemplate( [ 'name' => 'Template 3', 'is_locked' => true ] );

	Livewire::test( TemplateListingPage::class )
		->set( 'selected', [ $t1->id, $t2->id, $t3->id ] )
		->call( 'bulkDelete' )
		->assertDispatched( 've-templates-bulk-deleted' )
		->assertSet( 'selected', [] );

	// Locked template should not be deleted
	expect( Template::count() )->toBe( 1 );
	expect( Template::first()->id )->toBe( $t3->id );
} );

test( 'template listing page can bulk change status', function (): void {
	$t1 = createTestTemplate( [ 'name' => 'Template 1', 'status' => 'active' ] );
	$t2 = createTestTemplate( [ 'name' => 'Template 2', 'status' => 'active' ] );

	Livewire::test( TemplateListingPage::class )
		->set( 'selected', [ $t1->id, $t2->id ] )
		->call( 'bulkChangeStatus', 'draft' )
		->assertDispatched( 've-templates-bulk-status-changed' )
		->assertSet( 'selected', [] );

	expect( $t1->fresh()->status )->toBe( 'draft' );
	expect( $t2->fresh()->status )->toBe( 'draft' );
} );

test( 'template listing page rejects invalid bulk status', function (): void {
	$template = createTestTemplate( [ 'status' => 'active' ] );

	Livewire::test( TemplateListingPage::class )
		->set( 'selected', [ $template->id ] )
		->call( 'bulkChangeStatus', 'invalid' );

	expect( $template->fresh()->status )->toBe( 'active' );
} );

test( 'template listing page getColumns returns expected columns', function (): void {
	$component = new TemplateListingPage();
	$columns   = $component->getColumns();

	expect( $columns )->toBeArray();
	expect( array_column( $columns, 'key' ) )->toContain( 'name', 'type', 'status', 'updated_at' );
} );

test( 'template listing page columns are filterable via hook', function (): void {
	if ( ! function_exists( 'addFilter' ) ) {
		$this->markTestSkipped( 'Hooks package not available.' );
	}

	$callback = function ( array $columns ): array {
		$columns[] = [ 'key' => 'custom', 'label' => 'Custom', 'sortable' => false ];

		return $columns;
	};

	addFilter( 've.listing.columns', $callback );

	try {
		$component = new TemplateListingPage();
		$columns   = $component->getColumns();

		$custom = collect( $columns )->firstWhere( 'key', 'custom' );
		expect( $custom )->not->toBeNull();
	} finally {
		removeFilter( 've.listing.columns', $callback );
	}
} );

test( 'template listing route is accessible with permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );

	$user = createTemplateListingTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.templates' ) )
		->assertSuccessful();
} );

test( 'template listing route is forbidden without permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => false );

	$user = createTemplateListingTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.templates' ) )
		->assertForbidden();
} );

test( 'template listing route has correct name', function (): void {
	$routeUrl = route( 'visual-editor.templates' );
	$prefix   = config( 'artisanpack.visual-editor.site_editor.route_prefix', 'site-editor' );

	expect( $routeUrl )->toContain( '/' . $prefix . '/templates' );
} );
