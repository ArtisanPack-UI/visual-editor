<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Http\Controllers\EntitySearchController;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\TestBlockContentModel;
use Tests\Fixtures\TestBlockContentPagePolicy;
use Tests\Fixtures\TestBlockContentPolicy;
use Tests\Fixtures\TestG3Policy;
use Tests\Fixtures\TestLinkableContentModel;
use Tests\Fixtures\TestLinkablePageModel;
use Tests\Fixtures\TestPublishedOnlyPolicy;
use Tests\TestUser;

/*
 * #1042 — the all-types search behind the block editor's link selector,
 * plus the permission and resource-source changes it brought to the
 * typed search the Navigation picker uses.
 */

/**
 * Registers the given resources and rebuilds the resolver that reads them.
 *
 * @param  array<string, string>  $resources  Slug → model class map.
 */
function useLinkSearchResources( array $resources ): void
{
	config()->set( 'artisanpack.visual-editor.resources', $resources );

	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();
}

function actingAsLinkSearchUser(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Link Search Tester',
		'email'    => 'link-search+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

/**
 * Creates a record with a public URL.
 */
function linkableRecord( string $title, string $status = 'published' ): TestLinkableContentModel
{
	return TestLinkableContentModel::create( [
		'title'  => $title,
		'slug'   => strtolower( str_replace( ' ', '-', $title ) ),
		'status' => $status,
	] );
}

beforeEach( function (): void {
	useLinkSearchResources( [
		'pages'  => TestLinkableContentModel::class,
		'guides' => TestLinkablePageModel::class,
	] );

	Gate::policy( TestLinkableContentModel::class, TestPublishedOnlyPolicy::class );
	Gate::policy( TestLinkablePageModel::class, TestBlockContentPagePolicy::class );
	Gate::policy( TestBlockContentModel::class, TestBlockContentPolicy::class );
} );

it( 'requires authentication', function (): void {
	$this->getJson( '/visual-editor/api/search/links?q=about' )->assertUnauthorized();
} );

it( 'searches every registered resource and returns public URLs', function (): void {
	actingAsLinkSearchUser();

	$page  = linkableRecord( 'About Us' );
	$guide = TestLinkablePageModel::create( [ 'title' => 'About Guides' ] );

	linkableRecord( 'Contact' );

	$this->getJson( '/visual-editor/api/search/links?q=about' )
		->assertOk()
		->assertExactJson( [ 'data' => [
			[ 'type' => 'guide', 'id' => $guide->id, 'title' => 'About Guides', 'url' => url( "/guides/{$guide->id}" ) ],
			[ 'type' => 'page', 'id' => $page->id, 'title' => 'About Us', 'url' => url( '/about-us' ) ],
		] ] );
} );

it( 'searches resources contributed through the filter', function (): void {
	actingAsLinkSearchUser();

	$callback = static fn ( array $resources ): array => [ 'pages' => TestLinkableContentModel::class ] + $resources;

	addFilter( 'ap.visualEditor.resources', $callback );
	useLinkSearchResources( [] );

	linkableRecord( 'From The Filter' );

	$this->getJson( '/visual-editor/api/search/links?q=filter' )
		->assertOk()
		->assertJsonPath( 'data.0.title', 'From The Filter' );

	removeFilter( 'ap.visualEditor.resources', $callback );
} );

it( 'leaves out records with no public URL', function (): void {
	actingAsLinkSearchUser();

	TestLinkableContentModel::create( [ 'title' => 'No Url', 'status' => 'published' ] );

	$this->getJson( '/visual-editor/api/search/links?q=url' )
		->assertOk()
		->assertJsonPath( 'data', [] );
} );

it( 'lets a host supply a URL through the filter', function (): void {
	actingAsLinkSearchUser();

	$record   = TestLinkableContentModel::create( [ 'title' => 'No Url', 'status' => 'published' ] );
	$callback = static fn ( ?string $url, TestLinkableContentModel $row, string $slug ): string => "https://example.com/{$slug}/{$row->id}";

	addFilter( 'ap.visualEditor.search.resourceUrl', $callback );

	$this->getJson( '/visual-editor/api/search/links?q=url' )
		->assertOk()
		->assertJsonPath( 'data.0.url', "https://example.com/pages/{$record->id}" );

	removeFilter( 'ap.visualEditor.search.resourceUrl', $callback );
} );

it( 'hides records the user cannot view', function (): void {
	actingAsLinkSearchUser();

	linkableRecord( 'Secret Draft', 'draft' );
	linkableRecord( 'Secret Launch' );

	$this->getJson( '/visual-editor/api/search/links?q=secret' )
		->assertOk()
		->assertJsonCount( 1, 'data' )
		->assertJsonPath( 'data.0.title', 'Secret Launch' );
} );

it( 'shows drafts to users who can view them', function (): void {
	actingAsLinkSearchUser();

	Gate::policy( TestLinkableContentModel::class, TestG3Policy::class );

	linkableRecord( 'Visible Draft', 'draft' );

	$this->getJson( '/visual-editor/api/search/links?q=visible' )
		->assertOk()
		->assertJsonPath( 'data.0.title', 'Visible Draft' );
} );

it( 'still fills the list when the first matches are hidden', function (): void {
	actingAsLinkSearchUser();

	foreach ( range( 1, 30 ) as $number ) {
		linkableRecord( sprintf( 'Aardvark %02d', $number ), 'draft' );
	}

	linkableRecord( 'Aardvark Zebra' );

	$this->getJson( '/visual-editor/api/search/links?q=aardvark' )
		->assertOk()
		->assertJsonPath( 'data.0.title', 'Aardvark Zebra' );
} );

it( 'applies the editor scope of the resource model', function (): void {
	actingAsLinkSearchUser();

	useLinkSearchResources( [ 'posts' => TestBlockContentModel::class ] );

	$callback = static fn ( ?string $url, TestBlockContentModel $row ): string => url( "/posts/{$row->id}" );

	addFilter( 'ap.visualEditor.search.resourceUrl', $callback );

	TestBlockContentModel::create( [ 'title' => 'Scoped Draft', 'status' => 'draft' ] );
	TestBlockContentModel::create( [ 'title' => 'Scoped Post', 'status' => 'published' ] );

	$this->getJson( '/visual-editor/api/search/links?q=scoped' )
		->assertOk()
		->assertJsonCount( 1, 'data' )
		->assertJsonPath( 'data.0.title', 'Scoped Post' );

	removeFilter( 'ap.visualEditor.search.resourceUrl', $callback );
} );

it( 'keeps every type in the list when one has many matches', function (): void {
	actingAsLinkSearchUser();

	foreach ( range( 1, 25 ) as $number ) {
		linkableRecord( sprintf( 'Alpha %02d', $number ) );
	}

	TestLinkablePageModel::create( [ 'title' => 'Zulu Alpha' ] );

	$this->getJson( '/visual-editor/api/search/links?q=alpha' )
		->assertOk()
		->assertJsonCount( 20, 'data' )
		->assertJsonPath( 'data.19.title', 'Zulu Alpha' );
} );

it( 'returns recently updated content for an empty query', function (): void {
	actingAsLinkSearchUser();

	$this->travel( -2 )->days();
	linkableRecord( 'Apple' );
	$this->travelBack();

	linkableRecord( 'Zebra' );

	$this->getJson( '/visual-editor/api/search/links' )
		->assertOk()
		->assertJsonPath( 'data.0.title', 'Zebra' )
		->assertJsonPath( 'data.1.title', 'Apple' );
} );

it( 'skips a resource it cannot use', function (): void {
	actingAsLinkSearchUser();

	useLinkSearchResources( [
		'missing' => 'Tests\\Fixtures\\NoSuchModel',
		'pages'   => TestLinkableContentModel::class,
	] );

	linkableRecord( 'Still Here' );

	$this->getJson( '/visual-editor/api/search/links?q=still' )
		->assertOk()
		->assertJsonPath( 'data.0.title', 'Still Here' );
} );

it( 'treats LIKE wildcards as literal characters', function (): void {
	actingAsLinkSearchUser();

	linkableRecord( 'Plain Title' );
	linkableRecord( '100% Done' );

	$this->getJson( '/visual-editor/api/search/links?q=' . urlencode( '%' ) )
		->assertOk()
		->assertJsonCount( 1, 'data' )
		->assertJsonPath( 'data.0.title', '100% Done' );
} );

it( 'hides records the user cannot view from a typed search', function (): void {
	actingAsLinkSearchUser();

	linkableRecord( 'Typed Draft', 'draft' );
	$published = linkableRecord( 'Typed Page' );

	$this->getJson( '/visual-editor/api/search?type=page&q=typed' )
		->assertOk()
		->assertExactJson( [ 'data' => [
			[ 'type' => 'page', 'id' => $published->id, 'title' => 'Typed Page', 'url' => url( '/typed-page' ) ],
		] ] );
} );

it( 'finds a filter-contributed resource from a typed search', function (): void {
	actingAsLinkSearchUser();

	$callback = static fn ( array $resources ): array => [ 'categories' => TestLinkableContentModel::class ] + $resources;

	addFilter( 'ap.visualEditor.resources', $callback );
	useLinkSearchResources( [] );

	linkableRecord( 'News' );

	$this->getJson( '/visual-editor/api/search?type=category&q=news' )
		->assertOk()
		->assertJsonPath( 'data.0.type', 'category' );

	removeFilter( 'ap.visualEditor.resources', $callback );
} );

it( 'looks each resource table up in the schema once per request', function (): void {
	actingAsLinkSearchUser();

	linkableRecord( 'About Us' );

	$controller = app( EntitySearchController::class );
	$request    = Request::create( '/visual-editor/api/search/links', 'GET', [ 'q' => 'about' ] );
	// One column listing per table: `pragma_table_xinfo` on SQLite.
	$isSchema   = static fn ( array $query ): bool => str_contains( $query['query'], 'pragma_table_xinfo' );

	DB::enableQueryLog();

	$controller->links( $request );

	$first = count( array_filter( DB::getQueryLog(), $isSchema ) );

	$controller->links( $request );

	$total = count( array_filter( DB::getQueryLog(), $isSchema ) );

	DB::disableQueryLog();

	// Two resources, so two listings, and none for the repeat.
	expect( $first )->toBe( 2 )
		->and( $total )->toBe( $first );
} );

it( 'stops checking the view policy once the list is full', function (): void {
	actingAsLinkSearchUser();

	// Both resources have more than enough, so the list fills in the
	// middle of a round.
	foreach ( range( 1, 60 ) as $number ) {
		linkableRecord( sprintf( 'Beta %02d', $number ) );
		TestLinkablePageModel::create( [ 'title' => sprintf( 'Beta guide %02d', $number ) ] );
	}

	$checks     = 0;
	$urlLookups = 0;
	$counter    = static function ( ?string $url ) use ( &$urlLookups ): ?string {
		$urlLookups++;

		return $url;
	};

	addFilter( 'ap.visualEditor.search.resourceUrl', $counter );

	Gate::before( function () use ( &$checks ): ?bool {
		$checks++;

		return null;
	} );

	$this->getJson( '/visual-editor/api/search/links?q=beta' )
		->assertOk()
		->assertJsonCount( 20, 'data' );

	removeFilter( 'ap.visualEditor.search.resourceUrl', $counter );

	expect( $checks )->toBe( 20 )
		->and( $urlLookups )->toBe( 20 );
} );

it( 'reads at most one page of rows per resource while the list fills', function (): void {
	actingAsLinkSearchUser();

	foreach ( range( 1, 60 ) as $number ) {
		linkableRecord( sprintf( 'Gamma %02d', $number ) );
	}

	DB::enableQueryLog();

	$this->getJson( '/visual-editor/api/search/links?q=gamma' )
		->assertOk()
		->assertJsonCount( 20, 'data' );

	$reads = array_filter( DB::getQueryLog(), static fn ( array $query ): bool => str_contains( $query['query'], 'LIKE ? ESCAPE' ) );

	DB::disableQueryLog();

	// One read per resource: the page of 20 fills the list.
	expect( $reads )->toHaveCount( 2 );

	foreach ( $reads as $read ) {
		expect( $read['query'] )->toContain( 'limit 20' );
	}
} );
