<?php

/**
 * Coverage for the Content visibility rule (#1030): the built-in
 * sources, descendant search, Any / All, Show / Hide, and the inert and
 * fail-closed paths.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use ArtisanPackUI\VisualEditor\Resources\QueryInliner;
use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use ArtisanPackUI\VisualEditor\Visibility\ContentSourceRegistry;
use ArtisanPackUI\VisualEditor\Visibility\ContentSources\AbstractContentSource;
use ArtisanPackUI\VisualEditor\Visibility\Rules\ContentRule;
use ArtisanPackUI\VisualEditor\Visibility\TreePruner;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityEvaluator;
use Tests\Fixtures\FakeQueryResolver;

afterEach( function (): void {
	removeAllFilters( 'ap.visualEditor.visibility.registerContentSources' );
	removeAllActions( 'ap.visualEditor.visibility.evaluated' );
} );

/**
 * Build a Group carrying a content rule.
 *
 * @param  array<string, mixed>              $rule   The rule slice.
 * @param  array<int, array<string, mixed>>  $inner  The Group's inner blocks.
 *
 * @return array<string, mixed>
 */
function contentGroup( array $rule, array $inner ): array
{
	return [
		'name'        => 'artisanpack/group',
		'attributes'  => [ 'artisanpackVisibility' => [ 'content' => $rule ] ],
		'innerBlocks' => $inner,
	];
}

/**
 * @param  array<string, mixed>  $attributes
 *
 * @return array<string, mixed>
 */
function contentBlock( string $name, array $attributes = [] ): array
{
	return [ 'name' => $name, 'attributes' => $attributes, 'innerBlocks' => [] ];
}

function evaluateContent( array $block ): ArtisanPackUI\VisualEditor\Visibility\VisibilityDecision
{
	return app( VisibilityEvaluator::class )->evaluate( $block, new VisibilityContext() );
}

/**
 * A heading next to a loop, the shape both website cases take.
 *
 * @param  array<string, mixed>  $loop
 *
 * @return array<int, array<string, mixed>>
 */
function headingAnd( array $loop ): array
{
	return [ contentBlock( 'artisanpack/heading', [ 'content' => 'Related posts' ] ), $loop ];
}

it( 'hides a related-posts section when there are no related posts', function (): void {
	$rule = [ 'source' => 'relatedPosts', 'condition' => 'hasResults' ];

	$empty = contentGroup( $rule, headingAnd( contentBlock( 'artisanpack/related-posts', [ '_resolvedTotal' => 0, '_resolvedItems' => 0 ] ) ) );
	$full  = contentGroup( $rule, headingAnd( contentBlock( 'artisanpack/related-posts', [ '_resolvedTotal' => 3, '_resolvedItems' => 3 ] ) ) );

	$decision = evaluateContent( $empty );

	expect( $decision->isHidden() )->toBeTrue();
	expect( $decision->reasons )->toBe( [ 'content' ] );
	expect( evaluateContent( $full )->isVisible() )->toBeTrue();
} );

it( 'treats a loop whose page shows no rows as empty, whatever the total says', function ( string $source, string $name ): void {
	$block = contentGroup(
		[ 'source' => $source, 'condition' => 'hasResults' ],
		[ contentBlock( $name, [ '_resolvedTotal' => 12, '_resolvedItems' => 0 ] ) ],
	);

	expect( evaluateContent( $block )->isHidden() )->toBeTrue();
} )->with( [
	'query past the last page'         => [ 'query', 'artisanpack/query' ],
	'related posts past their offset'  => [ 'relatedPosts', 'artisanpack/related-posts' ],
] );

it( 'matches query loops in either namespace', function ( string $name ): void {
	$rule = [ 'source' => 'query', 'condition' => 'hasResults' ];

	expect( evaluateContent( contentGroup( $rule, [ contentBlock( $name, [ '_resolvedTotal' => 0 ] ) ] ) )->isHidden() )->toBeTrue();
	expect( evaluateContent( contentGroup( $rule, [ contentBlock( $name, [ '_resolvedTotal' => 2 ] ) ] ) )->isVisible() )->toBeTrue();
} )->with( [ 'artisanpack/query', 'core/query' ] );

it( 'answers each post navigation condition independently', function ( string $condition, string $previous, string $next, bool $visible ): void {
	foreach ( [ 'artisanpack/post-navigation-link', 'core/post-navigation-link' ] as $name ) {
		$block = contentGroup(
			[ 'source' => 'postNavigation', 'condition' => $condition ],
			[ contentBlock( $name, [ '_resolvedPrevUrl' => $previous, '_resolvedNextUrl' => $next ] ) ],
		);

		expect( evaluateContent( $block )->isVisible() )->toBe( $visible );
	}
} )->with( [
	'either, no neighbours'  => [ 'hasEither', '', '', false ],
	'either, previous only'  => [ 'hasEither', '/a', '', true ],
	'either, next only'      => [ 'hasEither', '', '/b', true ],
	'previous, has it'       => [ 'hasPrevious', '/a', '', true ],
	'previous, next only'    => [ 'hasPrevious', '', '/b', false ],
	'next, has it'           => [ 'hasNext', '', '/b', true ],
	'next, previous only'    => [ 'hasNext', '/a', '', false ],
] );

it( 'hides a pagination row when the loop has a single page', function ( string $name ): void {
	$rule = [ 'source' => 'pagination', 'condition' => 'hasMultiplePages' ];

	expect( evaluateContent( contentGroup( $rule, [ contentBlock( $name, [ '_resolvedTotalPages' => 1 ] ) ] ) )->isHidden() )->toBeTrue();
	expect( evaluateContent( contentGroup( $rule, [ contentBlock( $name, [ '_resolvedTotalPages' => 2 ] ) ] ) )->isVisible() )->toBeTrue();
} )->with( [
	'artisanpack/query-pagination',
	'core/query-pagination',
	'artisanpack/query',
	'core/query',
] );

it( 'renders a hide-direction fallback only when the source is empty', function (): void {
	$rule = [ 'source' => 'query', 'condition' => 'hasResults', 'direction' => 'hide' ];

	expect( evaluateContent( contentGroup( $rule, [ contentBlock( 'artisanpack/query', [ '_resolvedTotal' => 0 ] ) ] ) )->isVisible() )->toBeTrue();
	expect( evaluateContent( contentGroup( $rule, [ contentBlock( 'artisanpack/query', [ '_resolvedTotal' => 4 ] ) ] ) )->isHidden() )->toBeTrue();
} );

it( 'combines several sources with any and all', function ( string $match, int $first, int $second, bool $visible ): void {
	$block = contentGroup(
		[ 'source' => 'query', 'condition' => 'hasResults', 'match' => $match ],
		[
			contentBlock( 'artisanpack/query', [ '_resolvedTotal' => $first ] ),
			contentBlock( 'artisanpack/group' ),
			contentBlock( 'core/query', [ '_resolvedTotal' => $second ] ),
		],
	);

	expect( evaluateContent( $block )->isVisible() )->toBe( $visible );
} )->with( [
	'any, one has results'  => [ 'any', 0, 2, true ],
	'any, neither has'      => [ 'any', 0, 0, false ],
	'all, one has results'  => [ 'all', 0, 2, false ],
	'all, both have'        => [ 'all', 1, 2, true ],
] );

it( 'finds sources nested below the rule and on the block carrying it', function (): void {
	$rule = [ 'source' => 'relatedPosts', 'condition' => 'hasResults' ];

	$nested = contentGroup( $rule, [
		[
			'name'        => 'artisanpack/group',
			'attributes'  => [],
			'innerBlocks' => [ contentBlock( 'artisanpack/related-posts', [ '_resolvedTotal' => 0 ] ) ],
		],
	] );

	$self = contentBlock( 'artisanpack/related-posts', [
		'_resolvedTotal'        => 0,
		'artisanpackVisibility' => [ 'content' => $rule ],
	] );

	expect( evaluateContent( $nested )->isHidden() )->toBeTrue();
	expect( evaluateContent( $self )->isHidden() )->toBeTrue();
} );

it( 'stays inert and records why when it has nothing to check', function ( array $rule, array $inner, string $reason ): void {
	$decision = evaluateContent( contentGroup( $rule, $inner ) );

	expect( $decision->isVisible() )->toBeTrue();
	expect( $decision->reasons )->toBe( [ $reason ] );
} )->with( [
	'no matching block' => [
		[ 'source' => 'relatedPosts', 'condition' => 'hasResults' ],
		[ contentBlock( 'artisanpack/paragraph' ) ],
		ContentRule::REASON_NO_SOURCE,
	],
	'no matching block, hide direction' => [
		[ 'source' => 'relatedPosts', 'condition' => 'hasResults', 'direction' => 'hide' ],
		[],
		ContentRule::REASON_NO_SOURCE,
	],
	'unregistered source' => [
		[ 'source' => 'gone', 'condition' => 'hasResults' ],
		[ contentBlock( 'artisanpack/query', [ '_resolvedTotal' => 0 ] ) ],
		ContentRule::REASON_UNKNOWN_SOURCE,
	],
	'missing source' => [
		[ 'condition' => 'hasResults' ],
		[],
		ContentRule::REASON_UNKNOWN_SOURCE,
	],
	'condition from another source' => [
		[ 'source' => 'query', 'condition' => 'hasNext' ],
		[ contentBlock( 'artisanpack/query', [ '_resolvedTotal' => 0 ] ) ],
		ContentRule::REASON_UNKNOWN_CONDITION,
	],
] );

it( 'passes the no-source reason to the evaluated hook', function (): void {
	$captured = null;

	addAction( 'ap.visualEditor.visibility.evaluated', function ( $decision ) use ( &$captured ): void {
		$captured = $decision;
	} );

	evaluateContent( contentGroup( [ 'source' => 'relatedPosts', 'condition' => 'hasResults' ], [] ) );

	expect( $captured?->reasons )->toContain( 'content:no-source' );
} );

it( 'counts failed and unresolved sources as empty', function ( array $attributes ): void {
	$show = contentGroup( [ 'source' => 'query', 'condition' => 'hasResults' ], [ contentBlock( 'artisanpack/query', $attributes ) ] );
	$hide = contentGroup( [ 'source' => 'query', 'condition' => 'hasResults', 'direction' => 'hide' ], [ contentBlock( 'artisanpack/query', $attributes ) ] );

	$decision = evaluateContent( $show );

	expect( $decision->isHidden() )->toBeTrue();
	expect( $decision->reasons )->toBe( [ 'content', ContentRule::REASON_UNRESOLVED ] );

	$fallback = evaluateContent( $hide );

	expect( $fallback->isVisible() )->toBeTrue();
	expect( $fallback->reasons )->toBe( [ ContentRule::REASON_UNRESOLVED ] );
} )->with( [
	'failed'                      => [ [ '_resolutionError' => QueryInliner::ERROR_RESOLVER_ERROR ] ],
	'failed with a stale total'   => [ [ '_resolutionError' => QueryInliner::ERROR_NO_RUNTIME, '_resolvedTotal' => 4 ] ],
	'never inlined'               => [ [] ],
] );

it( 'leaves blocks that opted out of visibility alone', function (): void {
	$evaluator = app( VisibilityEvaluator::class );
	$evaluator->primeSupports( [ 'artisanpack/group' => false ] );

	$block = contentGroup( [ 'source' => 'relatedPosts', 'condition' => 'hasResults' ], [ contentBlock( 'artisanpack/related-posts', [ '_resolvedTotal' => 0 ] ) ] );

	expect( $evaluator->evaluate( $block, new VisibilityContext() )->isVisible() )->toBeTrue();
} );

it( 'combines with the other rules', function (): void {
	$block = contentGroup(
		[ 'source' => 'query', 'condition' => 'hasResults' ],
		[ contentBlock( 'artisanpack/query', [ '_resolvedTotal' => 3 ] ) ],
	);

	$block['attributes']['artisanpackVisibility']['hide'] = [ 'hidden' => true ];

	expect( evaluateContent( $block )->isHidden() )->toBeTrue();
} );

it( 'lets a host register a source through the filter', function (): void {
	addFilter( 'ap.visualEditor.visibility.registerContentSources', function ( ContentSourceRegistry $registry ): ContentSourceRegistry {
		$registry->register( new class extends AbstractContentSource {
			public function key(): string
			{
				return 'products';
			}

			public function label(): string
			{
				return 'Products';
			}

			public function blockNames(): array
			{
				return [ 'acme/products' ];
			}

			public function conditions(): array
			{
				return [ 'inStock' => 'In stock' ];
			}

			public function passes( string $condition, array $block ): bool
			{
				return ( $block['attributes']['_resolvedStock'] ?? 0 ) > 0;
			}

			protected function resolvedKey(): string
			{
				return '_resolvedStock';
			}
		} );

		return $registry;
	} );

	app()->forgetScopedInstances();

	$rule = [ 'source' => 'products', 'condition' => 'inStock' ];

	expect( evaluateContent( contentGroup( $rule, [ contentBlock( 'acme/products', [ '_resolvedStock' => 0 ] ) ] ) )->isHidden() )->toBeTrue();
	expect( evaluateContent( contentGroup( $rule, [ contentBlock( 'acme/products', [ '_resolvedStock' => 5 ] ) ] ) )->isVisible() )->toBeTrue();

	$payload = collect( app( ContentSourceRegistry::class )->toEditorPayload() )->firstWhere( 'key', 'products' );

	expect( $payload )->toBe( [
		'key'        => 'products',
		'label'      => 'Products',
		'blocks'     => [ 'acme/products' ],
		'conditions' => [ [ 'value' => 'inStock', 'label' => 'In stock' ] ],
	] );
} );

it( 'publishes the four built-in sources to the editor', function (): void {
	$payload = app( ContentSourceRegistry::class )->toEditorPayload();

	expect( array_column( $payload, 'key' ) )->toContain( 'query', 'relatedPosts', 'postNavigation', 'pagination' );
} );

it( 'drops the hidden group through the tree pruner and keeps the rest', function (): void {
	$rule = [ 'source' => 'relatedPosts', 'condition' => 'hasResults' ];

	$tree = [
		contentGroup( $rule, headingAnd( contentBlock( 'artisanpack/related-posts', [ '_resolvedTotal' => 0 ] ) ) ),
		contentBlock( 'artisanpack/paragraph' ),
		contentGroup( $rule, headingAnd( contentBlock( 'artisanpack/related-posts', [ '_resolvedTotal' => 2 ] ) ) ),
	];

	$result = app( TreePruner::class )->prune( $tree, new VisibilityContext() );

	expect( array_column( $result, 'name' ) )->toBe( [ 'artisanpack/paragraph', 'artisanpack/group' ] );
} );

describe( 'with a resolved tree', function (): void {
	beforeEach( function (): void {
		$this->resolver = new FakeQueryResolver();
		app()->instance( QueryResolverContract::class, $this->resolver );
	} );

	/**
	 * @return array<int, array<string, mixed>>
	 */
	function inlinedLoop( array $inner = [] ): array
	{
		return app( QueryInliner::class )->inline( [
			[ 'name' => 'artisanpack/query', 'attributes' => [ 'query' => [ 'perPage' => 2 ] ], 'innerBlocks' => $inner ],
		] );
	}

	it( 'stamps the page count on the owning query on every path', function ( array $inner ): void {
		$this->resolver->perPage = 2;
		$this->resolver->setItems( [ (object) [ 'id' => 1 ], (object) [ 'id' => 2 ] ] );
		$this->resolver->totalOverride = 5;

		expect( inlinedLoop( $inner )[0]['attributes']['_resolvedTotalPages'] )->toBe( 3 );
	} )->with( [
		'no inner blocks'  => [ [] ],
		'post template'    => [ [ [ 'name' => 'artisanpack/post-template', 'attributes' => [], 'innerBlocks' => [] ] ] ],
		'flat inner tree'  => [ [ [ 'name' => 'artisanpack/paragraph', 'attributes' => [], 'innerBlocks' => [] ] ] ],
	] );

	it( 'stamps the page count on an empty loop', function (): void {
		$this->resolver->setItems( [] );

		$tree = inlinedLoop( [ [ 'name' => 'artisanpack/post-template', 'attributes' => [], 'innerBlocks' => [] ] ] );

		expect( $tree[0]['attributes']['_resolvedTotalPages'] )->toBe( 1 );
	} );

	it( 'answers hasMultiplePages from a query with no pagination block', function (): void {
		$rule = [ 'source' => 'pagination', 'condition' => 'hasMultiplePages' ];

		$this->resolver->perPage = 2;
		$this->resolver->setItems( [ (object) [ 'id' => 1 ] ] );
		$this->resolver->totalOverride = 1;

		expect( evaluateContent( contentGroup( $rule, inlinedLoop() ) )->isHidden() )->toBeTrue();

		$this->resolver->totalOverride = 9;

		expect( evaluateContent( contentGroup( $rule, inlinedLoop() ) )->isVisible() )->toBeTrue();
	} );

	it( 'scopes a rule inside a loop iteration to that iteration\'s post', function (): void {
		$this->resolver->setItems( [
			(object) [ 'id' => 1, 'previous_post' => (object) [ 'id' => 9, 'title' => 'Older', 'permalink' => '/older' ] ],
			(object) [ 'id' => 2 ],
		] );

		$tree = inlinedLoop( [
			[
				'name'        => 'artisanpack/post-template',
				'attributes'  => [],
				'innerBlocks' => [
					contentGroup(
						[ 'source' => 'postNavigation', 'condition' => 'hasPrevious' ],
						[ contentBlock( 'artisanpack/post-navigation-link', [ 'type' => 'previous' ] ) ],
					),
				],
			],
		] );

		$pruned = app( TreePruner::class )->prune( $tree, new VisibilityContext() );

		$items = $pruned[0]['innerBlocks'][0]['innerBlocks'];

		expect( $items[0]['innerBlocks'] )->toHaveCount( 1 );
		expect( $items[1]['innerBlocks'] )->toHaveCount( 0 );
	} );
} );

it( 'hides a post navigation group when the resolved post has no neighbours', function (): void {
	$stamped = ( new PostResolver() )->stampTree( [ contentBlock( 'artisanpack/post-navigation-link' ) ], (object) [ 'id' => 1 ] );

	$block = contentGroup( [ 'source' => 'postNavigation', 'condition' => 'hasEither' ], $stamped );

	expect( evaluateContent( $block )->isHidden() )->toBeTrue();
} );
