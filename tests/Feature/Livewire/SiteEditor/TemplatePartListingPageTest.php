<?php

/**
 * Template Part Listing Page Livewire Component Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Livewire\SiteEditor
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\TemplatePartListingPage;
use ArtisanPackUI\VisualEditor\Models\TemplatePart;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses( RefreshDatabase::class );

/**
 * Create a test user for template part listing tests.
 *
 * @return Authenticatable
 */
function createPartListingTestUser(): Authenticatable
{
	$email = 'part-listing-test-' . Str::random( 8 ) . '@example.com';

	$id = DB::table( 'users' )->insertGetId( [
		'name'       => 'Test User',
		'email'      => $email,
		'created_at' => now(),
		'updated_at' => now(),
	] );

	$user        = new class () extends Authenticatable {
		protected $table = 'users';
	};
	$user->id    = $id;
	$user->name  = 'Test User';
	$user->email = $email;

	return $user;
}

/**
 * Create a test template part.
 *
 * @param array<string, mixed> $overrides Attribute overrides.
 *
 * @return TemplatePart
 */
function createTestPart( array $overrides = [] ): TemplatePart
{
	return TemplatePart::create( array_merge( [
		'name'    => 'Test Part',
		'slug'    => 'test-part-' . Str::random( 6 ),
		'area'    => 'header',
		'content' => [],
		'status'  => 'active',
	], $overrides ) );
}

test( 'part listing page renders successfully', function (): void {
	Livewire::test( TemplatePartListingPage::class )
		->assertSuccessful()
		->assertSee( __( 'visual-editor::ve.part_listing_title' ) );
} );

test( 'part listing page displays description', function (): void {
	Livewire::test( TemplatePartListingPage::class )
		->assertSee( __( 'visual-editor::ve.part_listing_description' ) );
} );

test( 'part listing page shows template parts', function (): void {
	createTestPart( [ 'name' => 'Main Header' ] );

	Livewire::test( TemplatePartListingPage::class )
		->assertSee( 'Main Header' );
} );

test( 'part listing page can search parts', function (): void {
	createTestPart( [ 'name' => 'Main Header' ] );
	createTestPart( [ 'name' => 'Site Footer' ] );

	Livewire::test( TemplatePartListingPage::class )
		->set( 'search', 'Header' )
		->assertSee( 'Main Header' )
		->assertDontSee( 'Site Footer' );
} );

test( 'part listing page can filter by status', function (): void {
	createTestPart( [ 'name' => 'Active Header', 'status' => 'active' ] );
	createTestPart( [ 'name' => 'Draft Sidebar', 'status' => 'draft' ] );

	Livewire::test( TemplatePartListingPage::class )
		->set( 'filterStatus', 'draft' )
		->assertSee( 'Draft Sidebar' )
		->assertDontSee( 'Active Header' );
} );

test( 'part listing page can filter by area', function (): void {
	createTestPart( [ 'name' => 'Main Header', 'area' => 'header' ] );
	createTestPart( [ 'name' => 'Left Sidebar', 'area' => 'sidebar' ] );

	Livewire::test( TemplatePartListingPage::class )
		->set( 'filterArea', 'sidebar' )
		->assertSee( 'Left Sidebar' )
		->assertDontSee( 'Main Header' );
} );

test( 'part listing page can sort by name', function (): void {
	createTestPart( [ 'name' => 'Alpha Part' ] );
	createTestPart( [ 'name' => 'Zeta Part' ] );

	Livewire::test( TemplatePartListingPage::class )
		->assertSeeInOrder( [ 'Alpha Part', 'Zeta Part' ] );
} );

test( 'part listing page can toggle sort direction', function (): void {
	Livewire::test( TemplatePartListingPage::class )
		->assertSet( 'sortDirection', 'asc' )
		->call( 'sort', 'name' )
		->assertSet( 'sortDirection', 'desc' )
		->call( 'sort', 'name' )
		->assertSet( 'sortDirection', 'asc' );
} );

test( 'part listing page can switch view mode', function (): void {
	Livewire::test( TemplatePartListingPage::class )
		->assertSet( 'viewMode', 'table' )
		->call( 'setViewMode', 'grid' )
		->assertSet( 'viewMode', 'grid' )
		->call( 'setViewMode', 'table' )
		->assertSet( 'viewMode', 'table' );
} );

test( 'part listing page rejects invalid view mode', function (): void {
	Livewire::test( TemplatePartListingPage::class )
		->call( 'setViewMode', 'invalid' )
		->assertSet( 'viewMode', 'table' );
} );

test( 'part listing page can duplicate a part', function (): void {
	$part = createTestPart( [ 'name' => 'Original Part' ] );

	Livewire::test( TemplatePartListingPage::class )
		->call( 'duplicate', $part->id )
		->assertDispatched( 've-part-duplicated' );

	expect( TemplatePart::count() )->toBe( 2 );
} );

test( 'part listing page can delete a part', function (): void {
	$part = createTestPart();

	Livewire::test( TemplatePartListingPage::class )
		->call( 'delete', $part->id )
		->assertDispatched( 've-part-deleted' );

	expect( TemplatePart::count() )->toBe( 0 );
} );

test( 'part listing page cannot delete locked part', function (): void {
	$part = createTestPart( [ 'is_locked' => true ] );

	Livewire::test( TemplatePartListingPage::class )
		->call( 'delete', $part->id );

	expect( TemplatePart::count() )->toBe( 1 );
} );

test( 'part listing page can toggle lock', function (): void {
	$part = createTestPart( [ 'is_locked' => false ] );

	Livewire::test( TemplatePartListingPage::class )
		->call( 'toggleLock', $part->id );

	expect( $part->fresh()->is_locked )->toBeTrue();
} );

test( 'part listing page can bulk delete', function (): void {
	$p1 = createTestPart( [ 'name' => 'Part 1' ] );
	$p2 = createTestPart( [ 'name' => 'Part 2' ] );
	$p3 = createTestPart( [ 'name' => 'Part 3', 'is_locked' => true ] );

	Livewire::test( TemplatePartListingPage::class )
		->set( 'selected', [ $p1->id, $p2->id, $p3->id ] )
		->call( 'bulkDelete' )
		->assertDispatched( 've-parts-bulk-deleted' )
		->assertSet( 'selected', [] );

	expect( TemplatePart::count() )->toBe( 1 );
	expect( TemplatePart::first()->id )->toBe( $p3->id );
} );

test( 'part listing page can bulk change status', function (): void {
	$p1 = createTestPart( [ 'name' => 'Part 1', 'status' => 'active' ] );
	$p2 = createTestPart( [ 'name' => 'Part 2', 'status' => 'active' ] );

	Livewire::test( TemplatePartListingPage::class )
		->set( 'selected', [ $p1->id, $p2->id ] )
		->call( 'bulkChangeStatus', 'draft' )
		->assertDispatched( 've-parts-bulk-status-changed' )
		->assertSet( 'selected', [] );

	expect( $p1->fresh()->status )->toBe( 'draft' );
	expect( $p2->fresh()->status )->toBe( 'draft' );
} );

test( 'part listing page rejects invalid bulk status', function (): void {
	$part = createTestPart( [ 'status' => 'active' ] );

	Livewire::test( TemplatePartListingPage::class )
		->set( 'selected', [ $part->id ] )
		->call( 'bulkChangeStatus', 'invalid' );

	expect( $part->fresh()->status )->toBe( 'active' );
} );

test( 'part listing page getColumns returns expected columns', function (): void {
	$component = new TemplatePartListingPage();
	$columns   = $component->getColumns();

	expect( $columns )->toBeArray();
	expect( array_column( $columns, 'key' ) )->toContain( 'name', 'area', 'status', 'updated_at' );
} );

test( 'part listing page columns are filterable via hook', function (): void {
	if ( ! function_exists( 'addFilter' ) ) {
		$this->markTestSkipped( 'Hooks package not available.' );
	}

	$callback = function ( array $columns ): array {
		$columns[] = [ 'key' => 'custom', 'label' => 'Custom', 'sortable' => false ];

		return $columns;
	};

	addFilter( 've.listing.columns', $callback );

	try {
		$component = new TemplatePartListingPage();
		$columns   = $component->getColumns();

		$custom = collect( $columns )->firstWhere( 'key', 'custom' );
		expect( $custom )->not->toBeNull();
	} finally {
		removeFilter( 've.listing.columns', $callback );
	}
} );

test( 'part listing route is accessible with permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );

	$user = createPartListingTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.template-parts' ) )
		->assertSuccessful();
} );

test( 'part listing route is forbidden without permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => false );

	$user = createPartListingTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.template-parts' ) )
		->assertForbidden();
} );

test( 'part listing route has correct name', function (): void {
	$routeUrl = route( 'visual-editor.template-parts' );
	$prefix   = config( 'artisanpack.visual-editor.site_editor.route_prefix', 'site-editor' );

	expect( $routeUrl )->toContain( '/' . $prefix . '/parts' );
} );
