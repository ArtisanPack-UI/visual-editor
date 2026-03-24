<?php

/**
 * Pattern Listing Page Livewire Component Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Livewire\SiteEditor
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\PatternListingPage;
use ArtisanPackUI\VisualEditor\Models\Pattern;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses( RefreshDatabase::class );

/**
 * Create a test user for pattern listing tests.
 *
 * @return Authenticatable
 */
function createPatternListingTestUser(): Authenticatable
{
	$email = 'pattern-listing-test-' . Str::random( 8 ) . '@example.com';

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
 * Create a test pattern.
 *
 * @param array<string, mixed> $overrides Attribute overrides.
 *
 * @return Pattern
 */
function createTestPattern( array $overrides = [] ): Pattern
{
	return Pattern::create( array_merge( [
		'name'     => 'Test Pattern',
		'slug'     => 'test-pattern-' . Str::random( 6 ),
		'blocks'   => [],
		'category' => 'layout',
	], $overrides ) );
}

test( 'pattern listing page renders successfully', function (): void {
	Livewire::test( PatternListingPage::class )
		->assertSuccessful()
		->assertSee( __( 'visual-editor::ve.pattern_listing_title' ) );
} );

test( 'pattern listing page displays description', function (): void {
	Livewire::test( PatternListingPage::class )
		->assertSee( __( 'visual-editor::ve.pattern_listing_description' ) );
} );

test( 'pattern listing page shows patterns', function (): void {
	createTestPattern( [ 'name' => 'Hero Section' ] );

	Livewire::test( PatternListingPage::class )
		->assertSee( 'Hero Section' );
} );

test( 'pattern listing page can search patterns', function (): void {
	createTestPattern( [ 'name' => 'Hero Section' ] );
	createTestPattern( [ 'name' => 'Footer CTA' ] );

	Livewire::test( PatternListingPage::class )
		->set( 'search', 'Hero' )
		->assertSee( 'Hero Section' )
		->assertDontSee( 'Footer CTA' );
} );

test( 'pattern listing page can filter by category', function (): void {
	createTestPattern( [ 'name' => 'Layout Pattern', 'category' => 'layout' ] );
	createTestPattern( [ 'name' => 'Text Pattern', 'category' => 'text' ] );

	Livewire::test( PatternListingPage::class )
		->set( 'filterCategory', 'text' )
		->assertSee( 'Text Pattern' )
		->assertDontSee( 'Layout Pattern' );
} );

test( 'pattern listing page can sort by name', function (): void {
	createTestPattern( [ 'name' => 'Alpha Pattern' ] );
	createTestPattern( [ 'name' => 'Zeta Pattern' ] );

	Livewire::test( PatternListingPage::class )
		->assertSeeInOrder( [ 'Alpha Pattern', 'Zeta Pattern' ] );
} );

test( 'pattern listing page can toggle sort direction', function (): void {
	Livewire::test( PatternListingPage::class )
		->assertSet( 'sortDirection', 'asc' )
		->call( 'sort', 'name' )
		->assertSet( 'sortDirection', 'desc' )
		->call( 'sort', 'name' )
		->assertSet( 'sortDirection', 'asc' );
} );

test( 'pattern listing page can switch view mode', function (): void {
	Livewire::test( PatternListingPage::class )
		->assertSet( 'viewMode', 'table' )
		->call( 'setViewMode', 'grid' )
		->assertSet( 'viewMode', 'grid' )
		->call( 'setViewMode', 'table' )
		->assertSet( 'viewMode', 'table' );
} );

test( 'pattern listing page rejects invalid view mode', function (): void {
	Livewire::test( PatternListingPage::class )
		->call( 'setViewMode', 'invalid' )
		->assertSet( 'viewMode', 'table' );
} );

test( 'pattern listing page can duplicate a pattern', function (): void {
	$pattern = createTestPattern( [ 'name' => 'Original Pattern' ] );

	Livewire::test( PatternListingPage::class )
		->call( 'duplicate', $pattern->id )
		->assertDispatched( 've-pattern-duplicated' );

	expect( Pattern::count() )->toBe( 2 );
} );

test( 'pattern listing page can delete a pattern', function (): void {
	$pattern = createTestPattern();

	Livewire::test( PatternListingPage::class )
		->call( 'delete', $pattern->id )
		->assertDispatched( 've-pattern-deleted' );

	expect( Pattern::count() )->toBe( 0 );
} );

test( 'pattern listing page can bulk delete', function (): void {
	$p1 = createTestPattern( [ 'name' => 'Pattern 1' ] );
	$p2 = createTestPattern( [ 'name' => 'Pattern 2' ] );

	Livewire::test( PatternListingPage::class )
		->set( 'selected', [ $p1->id, $p2->id ] )
		->call( 'bulkDelete' )
		->assertDispatched( 've-patterns-bulk-deleted' )
		->assertSet( 'selected', [] );

	expect( Pattern::count() )->toBe( 0 );
} );

test( 'pattern listing page getColumns returns expected columns', function (): void {
	$component = new PatternListingPage();
	$columns   = $component->getColumns();

	expect( $columns )->toBeArray();
	expect( array_column( $columns, 'key' ) )->toContain( 'name', 'category', 'updated_at' );
} );

test( 'pattern listing page columns are filterable via hook', function (): void {
	if ( ! function_exists( 'addFilter' ) ) {
		$this->markTestSkipped( 'Hooks package not available.' );
	}

	$callback = function ( array $columns ): array {
		$columns[] = [ 'key' => 'custom', 'label' => 'Custom', 'sortable' => false ];

		return $columns;
	};

	addFilter( 've.listing.columns', $callback );

	try {
		$component = new PatternListingPage();
		$columns   = $component->getColumns();

		$custom = collect( $columns )->firstWhere( 'key', 'custom' );
		expect( $custom )->not->toBeNull();
	} finally {
		removeFilter( 've.listing.columns', $callback );
	}
} );

test( 'pattern listing route is accessible with permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );

	$user = createPatternListingTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.patterns' ) )
		->assertSuccessful();
} );

test( 'pattern listing route is forbidden without permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => false );

	$user = createPatternListingTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.patterns' ) )
		->assertForbidden();
} );

test( 'pattern listing route has correct name', function (): void {
	$routeUrl = route( 'visual-editor.patterns' );
	$prefix   = config( 'artisanpack.visual-editor.site_editor.route_prefix', 'site-editor' );

	expect( $routeUrl )->toContain( '/' . $prefix . '/patterns' );
} );
