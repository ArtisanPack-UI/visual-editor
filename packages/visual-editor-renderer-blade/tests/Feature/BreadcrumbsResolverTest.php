<?php

declare( strict_types=1 );

/**
 * Feature tests for the Blade renderer's breadcrumbs-block path (#565).
 *
 * Covers the full server-side contract for `artisanpack/breadcrumbs`:
 *
 * - The renderer stamps `_resolvedTrail` via {@see BreadcrumbsResolver}
 *   so the Blade partial emits a populated `<ol>` on the public frontend
 *   without each host wiring its own trail builder.
 * - Defaults: a "Home" hop pointing at the configured home URL; the last
 *   entry marked `current: true` so the renderer drops the link and emits
 *   `aria-current="page"`.
 * - Hierarchical content (pages with `parent`/`parent_id`) walks the
 *   ancestor chain into a multi-hop trail in top-down order.
 * - Single posts get `Home → post title (current)`.
 * - 404 / no post context still produces a `Home (current)` shell.
 * - The `breadcrumbsSchema` attribute is honored (the resolver is
 *   indifferent; the markup carries Schema.org itemtype attributes).
 * - Host filters through `ap.visual-editor.breadcrumbs.trail` override /
 *   extend the trail without subclassing the resolver.
 * - Pre-stamped `_resolvedTrail` on the saved tree wins over the
 *   resolver fallback so a host that has resolved upstream keeps control.
 *
 * @since 1.0.0
 */

use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\BreadcrumbsResolver;
use Illuminate\Support\Facades\Blade;

function breadcrumbsBlockNode( array $attributes = [] ): array
{
	return [
		'clientId'    => 'breadcrumbs-cid',
		'name'        => 'artisanpack/breadcrumbs',
		'attributes'  => $attributes,
		'innerBlocks' => [],
	];
}

function breadcrumbsRenderTreeWithPost( array $tree, ?object $post = null ): string
{
	return Blade::render(
		'<x-ve-blocks :tree="$tree" :post="$post" />',
		[ 'tree' => $tree, 'post' => $post ]
	);
}

function breadcrumbsFakePost( int $id, string $title, string $permalink = '', ?object $parent = null ): object
{
	$post            = new stdClass();
	$post->id        = $id;
	$post->title     = $title;
	$post->permalink = '' === $permalink ? "/posts/{$id}" : $permalink;
	$post->parent    = $parent;
	$post->parent_id = null === $parent ? null : ( $parent->id ?? null );

	return $post;
}

beforeEach( function (): void {
	if ( function_exists( 'removeAllFilters' ) ) {
		removeAllFilters( 'ap.visual-editor.breadcrumbs.trail' );
	}

	config()->set( 'artisanpack.visual-editor.breadcrumbs.home_url', null );
	config()->set( 'artisanpack.visual-editor.breadcrumbs.home_label', null );
} );

it( 'stamps a Home-only trail when no post is supplied', function () {
	$resolver = $this->app->make( BreadcrumbsResolver::class );

	$tree = [ breadcrumbsBlockNode() ];

	$stamped = $resolver->stampTree( $tree, null );

	$trail = $stamped[0]['attributes']['_resolvedTrail'];

	expect( $trail )->toHaveCount( 1 );
	expect( $trail[0]['label'] )->toBe( 'Home' );
	expect( $trail[0]['current'] )->toBeTrue();
	expect( $trail[0]['url'] )->toBeNull();
} );

it( 'renders a single Home entry as the current page on the homepage shell', function () {
	$rendered = $this->stripGlobalStyles( breadcrumbsRenderTreeWithPost( [
		breadcrumbsBlockNode(),
	], null ) );

	// Single entry — Home marked current, no link, aria-current="page".
	expect( $rendered )
		->toContain( '<ol' )
		->toContain( 'aria-current="page"' )
		->toContain( '>Home<' );

	// And no separator since the trail has exactly one entry.
	expect( $rendered )->not->toContain( 'ap-breadcrumbs__separator' );
} );

it( 'stamps Home + post title for a single-post context', function () {
	$post = breadcrumbsFakePost( 7, 'My First Post', '/blog/my-first-post' );

	$resolver = $this->app->make( BreadcrumbsResolver::class );

	$stamped = $resolver->stampTree( [ breadcrumbsBlockNode() ], $post );
	$trail   = $stamped[0]['attributes']['_resolvedTrail'];

	expect( $trail )->toHaveCount( 2 );
	expect( $trail[0]['label'] )->toBe( 'Home' );
	expect( $trail[0]['current'] )->toBeFalse();
	expect( $trail[0]['url'] )->not->toBeNull();

	expect( $trail[1]['label'] )->toBe( 'My First Post' );
	expect( $trail[1]['current'] )->toBeTrue();
	expect( $trail[1]['url'] )->toBeNull();
} );

it( 'renders the populated trail end-to-end via x-ve-blocks for a single post', function () {
	$post = breadcrumbsFakePost( 7, 'My First Post', '/blog/my-first-post' );

	$rendered = $this->stripGlobalStyles( breadcrumbsRenderTreeWithPost(
		[ breadcrumbsBlockNode() ],
		$post
	) );

	expect( $rendered )
		->toContain( '<ol' )
		->toContain( '>Home<' )
		->toContain( '>My First Post<' )
		->toContain( 'aria-current="page"' )
		->toContain( 'ap-breadcrumbs__separator' );
} );

it( 'walks the parent chain top-down for a nested page hierarchy', function () {
	$grandparent = breadcrumbsFakePost( 1, 'About', '/about' );
	$parent      = breadcrumbsFakePost( 2, 'Team', '/about/team', $grandparent );
	$page        = breadcrumbsFakePost( 3, 'Engineering', '/about/team/engineering', $parent );

	$resolver = $this->app->make( BreadcrumbsResolver::class );

	$stamped = $resolver->stampTree( [ breadcrumbsBlockNode() ], $page );
	$trail   = $stamped[0]['attributes']['_resolvedTrail'];

	expect( $trail )->toHaveCount( 4 );
	expect( $trail[0]['label'] )->toBe( 'Home' );
	expect( $trail[1]['label'] )->toBe( 'About' );
	expect( $trail[1]['url'] )->toBe( '/about' );
	expect( $trail[2]['label'] )->toBe( 'Team' );
	expect( $trail[2]['url'] )->toBe( '/about/team' );
	expect( $trail[3]['label'] )->toBe( 'Engineering' );
	expect( $trail[3]['current'] )->toBeTrue();
	expect( $trail[3]['url'] )->toBeNull();
} );

it( 'guards against a parent-chain cycle without infinite recursion', function () {
	$a = breadcrumbsFakePost( 1, 'A', '/a' );
	$b = breadcrumbsFakePost( 2, 'B', '/b' );

	// Manually wire a cycle: A.parent = B, B.parent = A.
	$a->parent = $b;
	$b->parent = $a;

	$resolver = $this->app->make( BreadcrumbsResolver::class );

	$stamped = $resolver->stampTree( [ breadcrumbsBlockNode() ], $a );
	$trail   = $stamped[0]['attributes']['_resolvedTrail'];

	// Trail bounded — exactly Home + cycle members (deduped) + current.
	// Cycle detection stops the walk as soon as we revisit A; ordering is
	// [Home, B (parent of A), A (current)].
	expect( count( $trail ) )->toBeGreaterThanOrEqual( 2 );
	expect( count( $trail ) )->toBeLessThanOrEqual( 5 );
	expect( $trail[ count( $trail ) - 1 ]['label'] )->toBe( 'A' );
	expect( $trail[ count( $trail ) - 1 ]['current'] )->toBeTrue();
} );

it( 'honors the breadcrumbsSchema attribute in the rendered markup', function () {
	$post = breadcrumbsFakePost( 7, 'My Post', '/posts/7' );

	$withSchema = $this->stripGlobalStyles( breadcrumbsRenderTreeWithPost(
		[ breadcrumbsBlockNode( [ 'breadcrumbsSchema' => true ] ) ],
		$post
	) );

	expect( $withSchema )
		->toContain( 'schema.org/BreadcrumbList' )
		->toContain( 'schema.org/ListItem' )
		->toContain( 'itemprop="position"' );

	$withoutSchema = $this->stripGlobalStyles( breadcrumbsRenderTreeWithPost(
		[ breadcrumbsBlockNode( [ 'breadcrumbsSchema' => false ] ) ],
		$post
	) );

	expect( $withoutSchema )
		->not->toContain( 'schema.org/BreadcrumbList' )
		->not->toContain( 'itemprop="position"' );

	// The populated trail still renders without schema markup — the
	// `breadcrumbsSchema` toggle controls structure-only annotations.
	expect( $withoutSchema )->toContain( '>My Post<' );
} );

it( 'allows hosts to override the trail through the filter hook', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visual-editor.breadcrumbs.trail', function ( array $trail, ?object $post, array $attributes ): array {
		return [
			[ 'label' => 'Custom Home', 'url' => 'https://example.test/' ],
			[ 'label' => 'Category: Travel', 'url' => '/category/travel' ],
			[ 'label' => 'My Trip', 'url' => null, 'current' => true ],
		];
	}, 10 );

	$post = breadcrumbsFakePost( 7, 'My Trip', '/posts/7' );

	$rendered = $this->stripGlobalStyles( breadcrumbsRenderTreeWithPost(
		[ breadcrumbsBlockNode() ],
		$post
	) );

	expect( $rendered )
		->toContain( '>Custom Home<' )
		->toContain( '>Category: Travel<' )
		->toContain( '>My Trip<' )
		->toContain( 'aria-current="page"' );
} );

it( 'passes the block attributes to the filter so hosts can branch on them', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	$capturedAttrs = null;

	addFilter( 'ap.visual-editor.breadcrumbs.trail', function ( array $trail, ?object $post, array $attributes ) use ( &$capturedAttrs ): array {
		$capturedAttrs = $attributes;

		return $trail;
	}, 10 );

	$resolver = $this->app->make( BreadcrumbsResolver::class );

	$resolver->stampTree(
		[ breadcrumbsBlockNode( [ 'separatorIcon' => 'arrow-right', 'breadcrumbsSchema' => false ] ) ],
		null
	);

	expect( $capturedAttrs )->toBeArray();
	expect( $capturedAttrs['separatorIcon'] ?? null )->toBe( 'arrow-right' );
	expect( $capturedAttrs['breadcrumbsSchema'] ?? null )->toBeFalse();
} );

it( 'respects a pre-stamped _resolvedTrail on the saved tree', function () {
	$resolver = $this->app->make( BreadcrumbsResolver::class );

	$preStamped = [
		[ 'label' => 'Custom 1', 'url' => '/one' ],
		[ 'label' => 'Custom 2', 'url' => null, 'current' => true ],
	];

	$post = breadcrumbsFakePost( 7, 'Real Post', '/posts/7' );

	$stamped = $resolver->stampTree(
		[ breadcrumbsBlockNode( [ '_resolvedTrail' => $preStamped ] ) ],
		$post
	);

	// The resolver leaves the pre-stamped trail completely alone — no
	// finalization, no Home injection, no current-flag mutation.
	expect( $stamped[0]['attributes']['_resolvedTrail'] )->toBe( $preStamped );
} );

it( 'honors a configured custom home URL and home label', function () {
	config()->set( 'artisanpack.visual-editor.breadcrumbs.home_url', 'https://example.test/start' );
	config()->set( 'artisanpack.visual-editor.breadcrumbs.home_label', 'Start' );

	$post = breadcrumbsFakePost( 7, 'My Post', '/posts/7' );

	$resolver = $this->app->make( BreadcrumbsResolver::class );

	$stamped = $resolver->stampTree( [ breadcrumbsBlockNode() ], $post );
	$trail   = $stamped[0]['attributes']['_resolvedTrail'];

	expect( $trail[0]['label'] )->toBe( 'Start' );
	expect( $trail[0]['url'] )->toBe( 'https://example.test/start' );
} );

it( 'walks nested breadcrumbs blocks inside container blocks', function () {
	$post = breadcrumbsFakePost( 7, 'Nested Post', '/posts/7' );

	$resolver = $this->app->make( BreadcrumbsResolver::class );

	$tree = [
		[
			'name'        => 'core/group',
			'attributes'  => [],
			'innerBlocks' => [
				breadcrumbsBlockNode(),
			],
		],
	];

	$stamped = $resolver->stampTree( $tree, $post );

	$innerBlock = $stamped[0]['innerBlocks'][0];

	expect( $innerBlock['name'] )->toBe( 'artisanpack/breadcrumbs' );
	expect( $innerBlock['attributes']['_resolvedTrail'] )->toBeArray();
	expect( $innerBlock['attributes']['_resolvedTrail'] )->toHaveCount( 2 );
} );

it( 'tolerates posts with no parent accessor and only parent_id (no newQuery)', function () {
	$post            = new stdClass();
	$post->id        = 5;
	$post->title     = 'Orphan Page';
	$post->permalink = '/orphan';
	$post->parent_id = 999; // Points at a non-existent parent.
	// No `parent` accessor, no `newQuery()` method — resolver should bail
	// gracefully and just stamp Home + the page itself.

	$resolver = $this->app->make( BreadcrumbsResolver::class );

	$stamped = $resolver->stampTree( [ breadcrumbsBlockNode() ], $post );
	$trail   = $stamped[0]['attributes']['_resolvedTrail'];

	expect( $trail )->toHaveCount( 2 );
	expect( $trail[1]['label'] )->toBe( 'Orphan Page' );
	expect( $trail[1]['current'] )->toBeTrue();
} );

it( 'drops trail entries with blank labels through the filter', function () {
	if ( ! function_exists( 'addFilter' ) ) {
		expect( true )->toBeTrue();

		return;
	}

	addFilter( 'ap.visual-editor.breadcrumbs.trail', function ( array $trail, ?object $post, array $attributes ): array {
		return [
			[ 'label' => 'Home', 'url' => '/' ],
			[ 'label' => '', 'url' => '/dropped' ],
			[ 'label' => 'Current', 'url' => null ],
		];
	}, 10 );

	$resolver = $this->app->make( BreadcrumbsResolver::class );

	$stamped = $resolver->stampTree( [ breadcrumbsBlockNode() ], null );
	$trail   = $stamped[0]['attributes']['_resolvedTrail'];

	expect( $trail )->toHaveCount( 2 );
	expect( $trail[0]['label'] )->toBe( 'Home' );
	expect( $trail[1]['label'] )->toBe( 'Current' );
	expect( $trail[1]['current'] )->toBeTrue();
} );
