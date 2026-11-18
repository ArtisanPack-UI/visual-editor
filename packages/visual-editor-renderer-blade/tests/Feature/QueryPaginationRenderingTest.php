<?php

/**
 * Query Pagination on a single page (#955).
 *
 * QueryInliner stamps the page count on the wrapper and the numbers leaf.
 * One page renders nothing, as in WordPress, so a loop that fits on one
 * page doesn't print a lone "1" inside an otherwise empty landmark.
 */

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

/**
 * @return array<string, mixed>
 */
function singlePagePagination( int $totalPages ): array
{
	$pages = [];
	for ( $page = 1; $page <= $totalPages; $page++ ) {
		$pages[] = [ 'number' => $page, 'url' => "/blog?page={$page}" ];
	}

	return [
		'clientId'    => 'qp-' . $totalPages,
		'name'        => 'artisanpack/query-pagination',
		'attributes'  => [ '_resolvedTotalPages' => $totalPages ],
		'innerBlocks' => [
			[
				'clientId'    => 'qpn-' . $totalPages,
				'name'        => 'artisanpack/query-pagination-numbers',
				'attributes'  => [ '_resolvedCurrentPage' => 1, '_resolvedPageNumbers' => $pages, '_resolvedTotalPages' => $totalPages ],
				'innerBlocks' => [],
			],
		],
	];
}

/**
 * @param  array<int, array<string, mixed>>  $tree
 */
function renderPaginationTree( array $tree ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" :trust-resolved="true" />', [ 'tree' => $tree ] );
}

it( 'renders nothing when the results fit on one page', function (): void {
	$html = renderPaginationTree( [ singlePagePagination( 1 ) ] );

	expect( $html )->not->toContain( '<nav class="wp-block-query-pagination' )
		->and( $html )->not->toContain( 'aria-current="page"' );
} );

it( 'renders no numbers for a single page outside a stamped wrapper', function (): void {
	$html = renderPaginationTree( [ singlePagePagination( 1 )['innerBlocks'][0] ] );

	expect( $html )->not->toContain( 'wp-block-query-pagination-numbers' );
} );

it( 'renders the wrapper and numbers for two or more pages, and an unstamped wrapper', function (): void {
	$html       = renderPaginationTree( [ singlePagePagination( 2 ) ] );
	$unstamped  = renderPaginationTree( [ [ 'clientId' => 'qp-bare', 'name' => 'artisanpack/query-pagination', 'attributes' => [], 'innerBlocks' => [] ] ] );

	expect( $html )->toContain( '<nav class="wp-block-query-pagination' )
		->and( $html )->toContain( '<a class="page-numbers" href="/blog?page=2">2</a>' )
		->and( $unstamped )->toContain( '<nav class="wp-block-query-pagination' );
} );

it( 'keeps a one-page wrapper that holds other content', function (): void {
	$pagination                  = singlePagePagination( 1 );
	$pagination['innerBlocks'][] = [ 'clientId' => 'qp-para', 'name' => 'core/paragraph', 'attributes' => [ 'content' => 'Page one of one' ], 'innerBlocks' => [] ];

	$html = renderPaginationTree( [ $pagination ] );

	expect( $html )->toContain( '<nav class="wp-block-query-pagination' )
		->and( $html )->toContain( 'Page one of one' );
} );

/**
 * @param  array<string, mixed>  $attributes
 */
function renderPaginationLeaf( string $leaf, array $attributes ): string
{
	return renderPaginationTree( [ [
		'clientId'    => 'leaf-' . $leaf,
		'name'        => 'artisanpack/query-pagination-' . $leaf,
		'attributes'  => $attributes,
		'innerBlocks' => [],
	] ] );
}

it( 'renders previous and next with the stamped arrow and label settings (#1130)', function (): void {
	$next     = [ '_resolvedNextPageUrl' => '/blog?page=3' ];
	$previous = [ '_resolvedPreviousPageUrl' => '/blog?page=1' ];

	expect( renderPaginationLeaf( 'next', $next + [ '_resolvedPaginationArrow' => 'none' ] ) )->toContain( 'href="/blog?page=3">Next Page</a>' )
		->and( renderPaginationLeaf( 'next', $next + [ '_resolvedPaginationArrow' => 'arrow' ] ) )
		->toContain( 'Next Page<span class="wp-block-query-pagination-next-arrow is-arrow-arrow" aria-hidden="true">→</span></a>' )
		->and( renderPaginationLeaf( 'previous', $previous + [ '_resolvedPaginationArrow' => 'chevron' ] ) )
		->toContain( '"><span class="wp-block-query-pagination-previous-arrow is-arrow-chevron" aria-hidden="true">«</span>Previous Page</a>' )
		->and( renderPaginationLeaf( 'next', $next + [ '_resolvedPaginationArrow' => 'chevron', '_resolvedShowLabel' => false ] ) )
		->toContain( '<span class="screen-reader-text">Next Page</span><span class="wp-block-query-pagination-next-arrow is-arrow-chevron" aria-hidden="true">»</span>' )
		// Without an arrow the label can't be hidden, or the link would be empty.
		->and( renderPaginationLeaf( 'next', $next + [ '_resolvedPaginationArrow' => 'none', '_resolvedShowLabel' => false ] ) )
		->toContain( '>Next Page</a>' );
} );

it( 'renders a gap in the windowed page list as an ellipsis (#1130)', function (): void {
	$html = renderPaginationLeaf( 'numbers', [
		'_resolvedCurrentPage' => 1,
		'_resolvedTotalPages'  => 41,
		'_resolvedPageNumbers' => [
			[ 'number' => 1, 'url' => '/blog?page=1' ],
			[ 'number' => 2, 'url' => '/blog?page=2' ],
			[ 'dots' => true ],
			[ 'number' => 41, 'url' => '/blog?page=41' ],
		],
	] );

	expect( preg_replace( '/>\s+</', '><', $html ) )->toContain( '<a class="page-numbers" href="/blog?page=2">2</a><span class="page-numbers dots">&hellip;</span><a class="page-numbers" href="/blog?page=41">41</a>' );
} );
