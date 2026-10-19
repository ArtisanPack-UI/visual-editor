<?php

/**
 * Query Total block — Blade renderer (#962).
 *
 * The partial prints the `_resolvedTotal` snapshot QueryInliner stamps:
 * "25 results" (`total-results`) or "1–9 of 25 results" for the current
 * page (`range-display`), with the numbers in `<strong>`.
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Illuminate\Support\Facades\Blade;
use Tests\Fixtures\FakeQueryResolver;

/**
 * @param  array<string, mixed>  $attributes
 */
function renderQueryTotal( array $attributes, string $name = 'artisanpack/query-total' ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" />', [
		'tree' => [
			[
				'clientId'    => 'qt',
				'name'        => $name,
				'attributes'  => $attributes,
				'innerBlocks' => [],
			],
		],
	] );
}

it( 'prints the total with the plural noun', function (): void {
	$html = renderQueryTotal( [ '_resolvedTotal' => 25 ] );

	expect( $html )->toContain( '<div class="wp-block-query-total"><strong>25</strong> results</div>' );
} );

it( 'uses the singular noun for exactly one result', function (): void {
	$html = renderQueryTotal( [ '_resolvedTotal' => 1, 'singular' => 'package', 'plural' => 'packages' ] );

	expect( $html )->toContain( '<strong>1</strong> package</div>' );
} );

it( 'prints the plural noun for zero results', function (): void {
	$html = renderQueryTotal( [ '_resolvedTotal' => 0, 'displayType' => 'range-display' ] );

	expect( $html )->toContain( '<div class="wp-block-query-total"><strong>0</strong> results</div>' );
} );

it( 'wraps the count in the prefix and suffix, escaped', function (): void {
	$html = renderQueryTotal( [
		'_resolvedTotal' => 25,
		'prefix'         => 'Showing ',
		'plural'         => 'packages',
		'suffix'         => ' <em>now</em>',
	] );

	expect( $html )->toContain( 'Showing <strong>25</strong> packages &lt;em&gt;now&lt;/em&gt;</div>' );
} );

it( 'omits the noun when it is blank', function (): void {
	$html = renderQueryTotal( [ '_resolvedTotal' => 3, 'plural' => '' ] );

	expect( $html )->toContain( '<strong>3</strong></div>' );
} );

it( 'prints the current page range', function ( int $page, int $items, string $range ): void {
	$html = renderQueryTotal( [
		'_resolvedTotal'       => 25,
		'_resolvedCurrentPage' => $page,
		'_resolvedPerPage'     => 9,
		'_resolvedItems'       => $items,
		'displayType'          => 'range-display',
	] );

	expect( $html )->toContain( "<strong>{$range}</strong> of <strong>25</strong> results</div>" );
} )->with( [
	'first page'      => [ 1, 9, '1–9' ],
	'short last page' => [ 3, 7, '19–25' ],
] );

it( 'prints a single number when the page holds one result', function (): void {
	$html = renderQueryTotal( [
		'_resolvedTotal'       => 10,
		'_resolvedCurrentPage' => 2,
		'_resolvedPerPage'     => 9,
		'_resolvedItems'       => 1,
		'displayType'          => 'range-display',
	] );

	expect( $html )->toContain( '<strong>10</strong> of <strong>10</strong> results' );
} );

it( 'renders nothing outside a resolved query', function (): void {
	expect( renderQueryTotal( [ 'displayType' => 'total-results' ] ) )->not->toContain( 'wp-block-query-total' );
} );

it( 'renders the core alias identically to the fork', function (): void {
	$attributes = [ '_resolvedTotal' => 4, 'prefix' => 'Showing ' ];

	expect( $this->normalizeHtml( $this->stripGlobalStyles( renderQueryTotal( $attributes, 'core/query-total' ) ) ) )
		->toBe( $this->normalizeHtml( $this->stripGlobalStyles( renderQueryTotal( $attributes ) ) ) )
		->and( renderQueryTotal( $attributes, 'core/query-total' ) )->not->toContain( 'data-ve-unknown-block' );
} );

it( 'applies typography and colour supports to the wrapper', function (): void {
	$html = renderQueryTotal( [
		'_resolvedTotal' => 2,
		'fontSize'       => 'small',
		'textColor'      => 'primary',
	] );

	expect( $html )->toMatch( '/<div class="wp-block-query-total[^"]*has-small-font-size/' )
		->and( $html )->toContain( 'has-primary-color' );
} );

it( 'counts a whole query loop end to end', function (): void {
	$fake                = new FakeQueryResolver();
	$fake->perPage       = 2;
	$fake->currentPage   = 2;
	$fake->totalOverride = 5;
	$fake->setItems( [ (object) [ 'id' => 3, 'title' => 'C' ], (object) [ 'id' => 4, 'title' => 'D' ] ] );
	$this->app->instance( QueryResolverContract::class, $fake );

	$html = Blade::render( '<x-ve-blocks :tree="$tree" />', [
		'tree' => [
			[
				'clientId'    => 'q',
				'name'        => 'artisanpack/query',
				'attributes'  => [ 'query' => [ 'postType' => 'post', 'perPage' => 2 ] ],
				'innerBlocks' => [
					[ 'clientId' => 'qt', 'name' => 'artisanpack/query-total', 'attributes' => [ 'displayType' => 'range-display', 'prefix' => 'Showing ' ], 'innerBlocks' => [] ],
					[ 'clientId' => 'pt', 'name' => 'artisanpack/post-template', 'attributes' => [], 'innerBlocks' => [
						[ 'clientId' => 'ptt', 'name' => 'artisanpack/post-title', 'attributes' => [], 'innerBlocks' => [] ],
					] ],
				],
			],
		],
	] );

	expect( $html )->toContain( 'Showing <strong>3–4</strong> of <strong>5</strong> results</div>' );
} );
