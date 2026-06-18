<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use ArtisanPackUI\VisualEditor\Resources\QueryInliner;
use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Tests\Fixtures\FakeQueryResolver;

/**
 * Issue #592 — variable column / row spans on post-variants.
 *
 * Pins the `_resolvedGridSpan` stamping rules on the synthetic
 * `core/post-template-item` wrapper: spans only attach when (a) a
 * variant matched the post and (b) the parent post-template's layout
 * is "grid". The precedence rule from #591 is re-asserted here for the
 * span data so a higher-precedence variant's spans win over a
 * lower-precedence variant's spans when both could match.
 */

function variantBlockWithSpans(
	array $matcher,
	array $innerBlocks,
	?int $columnSpan = null,
	?int $rowSpan = null,
	?array $responsive = null,
	int $priority = 10
): array {
	$attributes = [
		'matcher'  => $matcher,
		'priority' => $priority,
	];

	if ( null !== $columnSpan ) {
		$attributes['gridColumnSpan'] = $columnSpan;
	}

	if ( null !== $rowSpan ) {
		$attributes['gridRowSpan'] = $rowSpan;
	}

	if ( null !== $responsive ) {
		$attributes['responsive'] = $responsive;
	}

	return [
		'name'        => 'artisanpack/post-variant',
		'attributes'  => $attributes,
		'innerBlocks' => $innerBlocks,
	];
}

function queryWithLayout( array $baseInner, array $variants, string $layout = 'list' ): array
{
	$postTemplateChildren = array_merge( $baseInner, $variants );

	return [
		'name'        => 'core/query',
		'attributes'  => [ 'query' => [ 'postType' => 'post', 'perPage' => 5 ] ],
		'innerBlocks' => [
			[
				'name'        => 'core/post-template',
				'attributes'  => [ 'layout' => $layout ],
				'innerBlocks' => $postTemplateChildren,
			],
		],
	];
}

function variantSpanPostFixture( int $id, string $title, array $extras = [] ): object
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

it( 'stamps _resolvedGridSpan on the iteration wrapper when the variant matched and the layout is grid', function () {
	$this->fake->setItems( [
		variantSpanPostFixture( 1, 'Hero' ),
		variantSpanPostFixture( 2, 'Listed' ),
	] );

	$base    = [ [ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ] ];
	$variant = variantBlockWithSpans(
		[ 'kind' => 'position', 'value' => 'first' ],
		[ [ 'name' => 'core/post-excerpt', 'attributes' => [], 'innerBlocks' => [] ] ],
		2,
		2
	);

	$inlined = $this->inliner->inline( [ queryWithLayout( $base, [ $variant ], 'grid' ) ] );
	$items   = $inlined[0]['innerBlocks'][0]['innerBlocks'];

	expect( $items[0]['attributes']['_resolvedGridSpan'] )->toEqual( [
		'columns' => [ 'base' => 2 ],
		'rows'    => [ 'base' => 2 ],
	] );

	// Non-matched item gets no span stamp.
	expect( array_key_exists( '_resolvedGridSpan', $items[1]['attributes'] ) )->toBeFalse();
} );

it( 'does not stamp _resolvedGridSpan when the parent post-template layout is not grid', function () {
	$this->fake->setItems( [
		variantSpanPostFixture( 1, 'Hero' ),
	] );

	$base    = [ [ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ] ];
	$variant = variantBlockWithSpans(
		[ 'kind' => 'position', 'value' => 'first' ],
		[ [ 'name' => 'core/post-excerpt', 'attributes' => [], 'innerBlocks' => [] ] ],
		2,
		2
	);

	foreach ( [ 'list', 'flex', 'stack' ] as $layout ) {
		$inlined = $this->inliner->inline( [ queryWithLayout( $base, [ $variant ], $layout ) ] );
		$items   = $inlined[0]['innerBlocks'][0]['innerBlocks'];

		expect( array_key_exists( '_resolvedGridSpan', $items[0]['attributes'] ) )
			->toBeFalse( "layout {$layout} must not produce a span stamp" );
	}
} );

it( 'omits _resolvedGridSpan when the variant has the default 1x1 span and no breakpoint overrides', function () {
	$this->fake->setItems( [
		variantSpanPostFixture( 1, 'Hero' ),
	] );

	$base    = [ [ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ] ];
	$variant = variantBlockWithSpans(
		[ 'kind' => 'position', 'value' => 'first' ],
		[ [ 'name' => 'core/post-excerpt', 'attributes' => [], 'innerBlocks' => [] ] ],
		1,
		1
	);

	$inlined = $this->inliner->inline( [ queryWithLayout( $base, [ $variant ], 'grid' ) ] );
	$items   = $inlined[0]['innerBlocks'][0]['innerBlocks'];

	// The 1x1 case is a no-op for the renderer; skip the stamp so
	// the CSS bundle stays the only source of span emission.
	expect( array_key_exists( '_resolvedGridSpan', $items[0]['attributes'] ) )->toBeFalse();
} );

it( 'merges base and per-breakpoint responsive overrides into the resolved span shape', function () {
	$this->fake->setItems( [
		variantSpanPostFixture( 1, 'Hero' ),
	] );

	$base    = [ [ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ] ];
	$variant = variantBlockWithSpans(
		[ 'kind' => 'position', 'value' => 'first' ],
		[ [ 'name' => 'core/post-excerpt', 'attributes' => [], 'innerBlocks' => [] ] ],
		2,
		2,
		[
			'gridColumnSpan' => [ 'md' => 3, 'lg' => 4 ],
			'gridRowSpan'    => [ 'md' => 1 ],
		]
	);

	$inlined = $this->inliner->inline( [ queryWithLayout( $base, [ $variant ], 'grid' ) ] );
	$items   = $inlined[0]['innerBlocks'][0]['innerBlocks'];

	expect( $items[0]['attributes']['_resolvedGridSpan'] )->toEqual( [
		'columns' => [ 'base' => 2, 'md' => 3, 'lg' => 4 ],
		'rows'    => [ 'base' => 2, 'md' => 1 ],
	] );
} );

it( 'clamps span values into the renderer-supported 1..12 range', function () {
	$this->fake->setItems( [
		variantSpanPostFixture( 1, 'Hero' ),
	] );

	$base    = [ [ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ] ];
	$variant = variantBlockWithSpans(
		[ 'kind' => 'position', 'value' => 'first' ],
		[ [ 'name' => 'core/post-excerpt', 'attributes' => [], 'innerBlocks' => [] ] ],
		// 0 -> 1, 99 -> 12 via clampSpanValue().
		0,
		99
	);

	$inlined = $this->inliner->inline( [ queryWithLayout( $base, [ $variant ], 'grid' ) ] );
	$items   = $inlined[0]['innerBlocks'][0]['innerBlocks'];

	// Note: when the clamped values land back at the 1×1 default with
	// no breakpoint overrides, the inliner skips the stamp. We use
	// row=99 to ensure the stamp survives.
	expect( $items[0]['attributes']['_resolvedGridSpan'] )->toEqual( [
		'columns' => [ 'base' => 1 ],
		'rows'    => [ 'base' => 12 ],
	] );
} );

it( 'honors the variant precedence cascade when two variants compete on spans', function () {
	$this->fake->setItems( [
		variantSpanPostFixture( 1, 'Sticky Top', [ 'sticky' => true ] ),
	] );

	$base = [ [ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ] ];

	$positionVariant = variantBlockWithSpans(
		[ 'kind' => 'position', 'value' => 'first' ],
		[ [ 'name' => 'core/post-excerpt', 'attributes' => [], 'innerBlocks' => [] ] ],
		4,
		2
	);
	$metaVariant = variantBlockWithSpans(
		[ 'kind' => 'meta', 'value' => 'sticky' ],
		[ [ 'name' => 'core/post-content', 'attributes' => [], 'innerBlocks' => [] ] ],
		2,
		1
	);

	// Both variants match the first post. Position outranks meta, so
	// the position variant's 4x2 span must win.
	$inlined = $this->inliner->inline( [
		queryWithLayout( $base, [ $metaVariant, $positionVariant ], 'grid' ),
	] );

	$items = $inlined[0]['innerBlocks'][0]['innerBlocks'];

	expect( $items[0]['attributes']['_resolvedGridSpan'] )->toEqual( [
		'columns' => [ 'base' => 4 ],
		'rows'    => [ 'base' => 2 ],
	] );
} );
