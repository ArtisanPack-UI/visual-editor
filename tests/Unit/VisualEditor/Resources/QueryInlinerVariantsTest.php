<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use ArtisanPackUI\VisualEditor\Resources\QueryInliner;
use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Tests\Fixtures\FakeQueryResolver;

/**
 * Issue #591 — post-variant system: per-position template overrides on
 * the Query Loop. These tests pin the precedence cascade, the
 * static map fast-path, the meta / custom matcher behavior, and the
 * backward-compat guarantee (no variants = unchanged output).
 */

function variantBlock( array $matcher, array $innerBlocks, int $priority = 10 ): array
{
	return [
		'name'        => 'artisanpack/post-variant',
		'attributes'  => [
			'matcher'  => $matcher,
			'priority' => $priority,
		],
		'innerBlocks' => $innerBlocks,
	];
}

function queryWithVariants( array $baseInner, array $variants, array $compiledMap = [] ): array
{
	$postTemplateChildren = array_merge( $baseInner, $variants );

	return [
		'name'        => 'core/query',
		'attributes'  => [ 'query' => [ 'postType' => 'post', 'perPage' => 5 ] ],
		'innerBlocks' => [
			[
				'name'        => 'core/post-template',
				'attributes'  => [ '_compiledVariantMap' => $compiledMap ],
				'innerBlocks' => $postTemplateChildren,
			],
		],
	];
}

function variantPostFixture( int $id, string $title, array $extras = [] ): object
{
	$post                    = new stdClass();
	$post->id                = $id;
	$post->title             = $title;
	$post->content           = "<p>{$title}</p>";
	$post->permalink         = "/posts/{$id}";
	$post->author            = null;
	$post->published_at      = null;
	$post->updated_at        = null;
	$post->featured_image_id = null;

	foreach ( $extras as $key => $value ) {
		$post->{$key} = $value;
	}

	return $post;
}

beforeEach( function (): void {
	$this->fake = new FakeQueryResolver();
	$this->app->instance( QueryResolverContract::class, $this->fake );

	$this->inliner = new QueryInliner( $this->app, new PostResolver() );
} );

it( 'renders identically when no variants are declared (backwards compat)', function () {
	$this->fake->setItems( [
		variantPostFixture( 1, 'First' ),
		variantPostFixture( 2, 'Second' ),
	] );

	$base = [
		[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
	];

	$tree    = [ queryWithVariants( $base, [] ) ];
	$inlined = $this->inliner->inline( $tree );

	$items = $inlined[0]['innerBlocks'][0]['innerBlocks'];

	expect( count( $items ) )->toBe( 2 )
		->and( $items[0]['name'] )->toBe( 'core/post-template-item' )
		->and( count( $items[0]['innerBlocks'] ) )->toBe( 1 )
		->and( $items[0]['innerBlocks'][0]['name'] )->toBe( 'core/post-title' )
		// no-variant items must not get the `is-variant` class
		->and( str_contains( $items[0]['attributes']['className'], 'is-variant' ) )->toBeFalse()
		->and( str_contains( $items[1]['attributes']['className'], 'is-variant' ) )->toBeFalse();
} );

it( 'swaps the first post template via a position:first variant', function () {
	$this->fake->setItems( [
		variantPostFixture( 1, 'Hero' ),
		variantPostFixture( 2, 'Listed' ),
	] );

	$base    = [ [ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ] ];
	$variant = variantBlock(
		[ 'kind' => 'position', 'value' => 'first' ],
		[ [ 'name' => 'core/post-excerpt', 'attributes' => [], 'innerBlocks' => [] ] ]
	);

	$tree    = [ queryWithVariants( $base, [ $variant ] ) ];
	$inlined = $this->inliner->inline( $tree );

	$items = $inlined[0]['innerBlocks'][0]['innerBlocks'];

	expect( count( $items ) )->toBe( 2 )
		// variant template for the first post
		->and( $items[0]['innerBlocks'][0]['name'] )->toBe( 'core/post-excerpt' )
		// base template for the rest
		->and( $items[1]['innerBlocks'][0]['name'] )->toBe( 'core/post-title' )
		// is-variant class is stamped on the swapped iteration
		->and( str_contains( $items[0]['attributes']['className'], 'is-variant' ) )->toBeTrue()
		->and( str_contains( $items[1]['attributes']['className'], 'is-variant' ) )->toBeFalse();
} );

it( 'matches odd / even pattern variants', function () {
	$this->fake->setItems( [
		variantPostFixture( 1, 'A' ),
		variantPostFixture( 2, 'B' ),
		variantPostFixture( 3, 'C' ),
	] );

	$base       = [ [ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ] ];
	$oddVariant = variantBlock(
		[ 'kind' => 'pattern', 'value' => 'odd' ],
		[ [ 'name' => 'core/post-content', 'attributes' => [], 'innerBlocks' => [] ] ]
	);

	$inlined = $this->inliner->inline( [ queryWithVariants( $base, [ $oddVariant ] ) ] );
	$items   = $inlined[0]['innerBlocks'][0]['innerBlocks'];

	expect( $items[0]['innerBlocks'][0]['name'] )->toBe( 'core/post-content' ) // pos 1 odd
		->and( $items[1]['innerBlocks'][0]['name'] )->toBe( 'core/post-title' )  // pos 2 even -> base
		->and( $items[2]['innerBlocks'][0]['name'] )->toBe( 'core/post-content' ); // pos 3 odd
} );

it( 'matches meta:sticky variants by walking the variant list at render time', function () {
	$this->fake->setItems( [
		variantPostFixture( 1, 'Pinned', [ 'sticky' => true ] ),
		variantPostFixture( 2, 'Normal' ),
	] );

	$base    = [ [ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ] ];
	$variant = variantBlock(
		[ 'kind' => 'meta', 'value' => 'sticky' ],
		[ [ 'name' => 'core/post-content', 'attributes' => [], 'innerBlocks' => [] ] ]
	);

	$inlined = $this->inliner->inline( [ queryWithVariants( $base, [ $variant ] ) ] );
	$items   = $inlined[0]['innerBlocks'][0]['innerBlocks'];

	expect( $items[0]['innerBlocks'][0]['name'] )->toBe( 'core/post-content' )
		->and( $items[1]['innerBlocks'][0]['name'] )->toBe( 'core/post-title' );
} );

it( 'resolves custom matchers via the apve_query_variant_match_<name> filter hook', function () {
	if ( ! function_exists( 'ArtisanPackUI\\Hooks\\applyFilters' ) ) {
		test()->markTestSkipped( 'artisanpack-ui/hooks not loaded.' );
		return;
	}

	$this->fake->setItems( [
		variantPostFixture( 1, 'Promo', [ 'is_promo' => true ] ),
		variantPostFixture( 2, 'Regular' ),
	] );

	\ArtisanPackUI\Hooks\addFilter(
		'apve_query_variant_match_premium',
		fn ( $matches, $post ) => true === ( $post->is_promo ?? false ),
		10
	);

	try {
		$base    = [ [ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ] ];
		$variant = variantBlock(
			[ 'kind' => 'custom', 'value' => 'callback:premium' ],
			[ [ 'name' => 'core/post-content', 'attributes' => [], 'innerBlocks' => [] ] ]
		);

		$inlined = $this->inliner->inline( [ queryWithVariants( $base, [ $variant ] ) ] );
		$items   = $inlined[0]['innerBlocks'][0]['innerBlocks'];

		expect( $items[0]['innerBlocks'][0]['name'] )->toBe( 'core/post-content' )
			->and( $items[1]['innerBlocks'][0]['name'] )->toBe( 'core/post-title' );
	} finally {
		\ArtisanPackUI\Hooks\removeAllFilters( 'apve_query_variant_match_premium' );
	}
} );

it( 'honors the precedence cascade: position > pattern > meta > custom', function () {
	$this->fake->setItems( [
		variantPostFixture( 1, 'First', [ 'sticky' => true ] ),
		variantPostFixture( 2, 'Second' ),
	] );

	$base = [ [ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ] ];

	$positionVariant = variantBlock(
		[ 'kind' => 'position', 'value' => 'first' ],
		[ [ 'name' => 'core/post-excerpt', 'attributes' => [], 'innerBlocks' => [] ] ]
	);
	$metaVariant = variantBlock(
		[ 'kind' => 'meta', 'value' => 'sticky' ],
		[ [ 'name' => 'core/post-content', 'attributes' => [], 'innerBlocks' => [] ] ]
	);

	// First post matches BOTH position:first AND meta:sticky.
	// Position must win because it's a higher precedence tier.
	$inlined = $this->inliner->inline( [
		queryWithVariants( $base, [ $metaVariant, $positionVariant ] ),
	] );

	$items = $inlined[0]['innerBlocks'][0]['innerBlocks'];

	expect( $items[0]['innerBlocks'][0]['name'] )->toBe( 'core/post-excerpt' );
} );

it( 'uses the precompiled _compiledVariantMap as the fast path for static matchers', function () {
	$this->fake->setItems( [
		variantPostFixture( 1, 'A' ),
		variantPostFixture( 2, 'B' ),
		variantPostFixture( 3, 'C' ),
	] );

	$base    = [ [ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ] ];
	$variant = variantBlock(
		[ 'kind' => 'position', 'value' => 'first' ],
		[ [ 'name' => 'core/post-content', 'attributes' => [], 'innerBlocks' => [] ] ]
	);

	// Map says: at loop index 2, use variant #0. Without the map,
	// position:first would only match index 0, so map presence is
	// observable in output.
	$inlined = $this->inliner->inline( [
		queryWithVariants( $base, [ $variant ], [ 2 => 0 ] ),
	] );

	$items = $inlined[0]['innerBlocks'][0]['innerBlocks'];

	expect( $items[0]['innerBlocks'][0]['name'] )->toBe( 'core/post-content' ) // matched via walk
		->and( $items[1]['innerBlocks'][0]['name'] )->toBe( 'core/post-title' )
		->and( $items[2]['innerBlocks'][0]['name'] )->toBe( 'core/post-content' ); // matched via map
} );

it( 'breaks ties between same-tier variants on priority ascending then document order', function () {
	$this->fake->setItems( [
		variantPostFixture( 1, 'A' ),
	] );

	$base = [ [ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ] ];

	$higherPriority = variantBlock(
		[ 'kind' => 'position', 'value' => 'first' ],
		[ [ 'name' => 'core/post-excerpt', 'attributes' => [], 'innerBlocks' => [] ] ],
		20
	);
	$lowerPriority = variantBlock(
		[ 'kind' => 'position', 'value' => 'first' ],
		[ [ 'name' => 'core/post-content', 'attributes' => [], 'innerBlocks' => [] ] ],
		5
	);

	// Both match index 0; lowerPriority (priority=5) wins.
	$inlined = $this->inliner->inline( [
		queryWithVariants( $base, [ $higherPriority, $lowerPriority ] ),
	] );

	$items = $inlined[0]['innerBlocks'][0]['innerBlocks'];

	expect( $items[0]['innerBlocks'][0]['name'] )->toBe( 'core/post-content' );
} );
