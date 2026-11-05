<?php

/**
 * Content visibility through `<x-ve-blocks>` (#1030): the real pipeline,
 * so the rule reads what `QueryInliner` and `PostResolver` stamped.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Illuminate\Support\Facades\Blade;
use Tests\Fixtures\FakeQueryResolver;

/**
 * @param  array<string, mixed>              $rule
 * @param  array<int, array<string, mixed>>  $inner
 *
 * @return array<string, mixed>
 */
function contentVisibilityGroup( array $rule, array $inner ): array
{
	return [
		'name'        => 'core/group',
		'attributes'  => [ 'artisanpackVisibility' => [ 'content' => $rule ] ],
		'innerBlocks' => $inner,
	];
}

/**
 * @return array<string, mixed>
 */
function contentVisibilityParagraph( string $text ): array
{
	return [ 'name' => 'core/paragraph', 'attributes' => [ 'content' => $text ], 'innerBlocks' => [] ];
}

/**
 * @param  array<int, array<string, mixed>>  $tree
 */
function renderContentVisibility( array $tree, ?object $post = null ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" :post="$post" />', [ 'tree' => $tree, 'post' => $post ] );
}

/**
 * @return array<int, array<string, mixed>>
 */
function relatedPostsSection(): array
{
	return [
		contentVisibilityGroup( [ 'source' => 'relatedPosts', 'condition' => 'hasResults' ], [
			contentVisibilityParagraph( 'Related posts heading' ),
			[
				'name'        => 'artisanpack/related-posts',
				'attributes'  => [],
				'innerBlocks' => [ contentVisibilityParagraph( 'Related item' ) ],
			],
		] ),
		contentVisibilityParagraph( 'After the section' ),
	];
}

/**
 * @return array<int, array<string, mixed>>
 */
function postNavigationSection( string $condition = 'hasEither' ): array
{
	return [
		contentVisibilityGroup( [ 'source' => 'postNavigation', 'condition' => $condition ], [
			contentVisibilityParagraph( 'Navigation band' ),
			[ 'name' => 'artisanpack/post-navigation-link', 'attributes' => [ 'type' => 'previous' ], 'innerBlocks' => [] ],
			[ 'name' => 'artisanpack/post-navigation-link', 'attributes' => [ 'type' => 'next' ], 'innerBlocks' => [] ],
		] ),
	];
}

beforeEach( function (): void {
	$this->resolver = new FakeQueryResolver();
	app()->instance( QueryResolverContract::class, $this->resolver );
} );

it( 'removes the whole related-posts section, heading included, when there are none', function (): void {
	$this->resolver->setItems( [] );

	$html = renderContentVisibility( relatedPostsSection(), (object) [ 'id' => 1, 'title' => 'Host' ] );

	expect( $html )->not->toContain( 'Related posts heading' )
		->and( $html )->toContain( 'After the section' );
} );

it( 'renders the related-posts section when there are some', function (): void {
	$this->resolver->setItems( [ (object) [ 'id' => 2, 'title' => 'Neighbour' ] ] );

	$html = renderContentVisibility( relatedPostsSection(), (object) [ 'id' => 1, 'title' => 'Host' ] );

	expect( $html )->toContain( 'Related posts heading' )
		->and( $html )->toContain( 'Related item' );
} );

it( 'removes the section when a resolver failure leaves the loop empty', function (): void {
	app()->forgetInstance( QueryResolverContract::class );
	app()->offsetUnset( QueryResolverContract::class );

	$html = renderContentVisibility( relatedPostsSection(), (object) [ 'id' => 1, 'title' => 'Host' ] );

	expect( $html )->not->toContain( 'Related posts heading' );
} );

it( 'removes the section where there is no host post', function (): void {
	$html = renderContentVisibility( relatedPostsSection() );

	expect( $html )->not->toContain( 'Related posts heading' );
} );

it( 'removes the post navigation band for a post with no neighbours', function (): void {
	$html = renderContentVisibility( postNavigationSection(), (object) [ 'id' => 1, 'title' => 'Only post' ] );

	expect( $html )->not->toContain( 'Navigation band' );
} );

it( 'keeps the post navigation band with one neighbour', function ( string $condition, bool $visible ): void {
	$post = (object) [
		'id'            => 1,
		'title'         => 'Newest',
		'previous_post' => (object) [ 'id' => 2, 'title' => 'Older', 'permalink' => '/older' ],
	];

	$html = renderContentVisibility( postNavigationSection( $condition ), $post );

	expect( str_contains( $html, 'Navigation band' ) )->toBe( $visible );
} )->with( [
	'either'   => [ 'hasEither', true ],
	'previous' => [ 'hasPrevious', true ],
	'next'     => [ 'hasNext', false ],
] );

it( 'hides a pagination row when the loop fits on one page', function ( int $total, bool $visible ): void {
	$this->resolver->perPage       = 2;
	$this->resolver->totalOverride = $total;
	$this->resolver->setItems( [ (object) [ 'id' => 1, 'title' => 'One' ] ] );

	$html = renderContentVisibility( [
		[
			'name'        => 'artisanpack/query',
			'attributes'  => [ 'query' => [ 'perPage' => 2 ] ],
			'innerBlocks' => [
				[ 'name' => 'artisanpack/post-template', 'attributes' => [], 'innerBlocks' => [ contentVisibilityParagraph( 'Row' ) ] ],
				contentVisibilityGroup( [ 'source' => 'pagination', 'condition' => 'hasMultiplePages' ], [
					contentVisibilityParagraph( 'Pagination row' ),
					[ 'name' => 'artisanpack/query-pagination', 'attributes' => [], 'innerBlocks' => [] ],
				] ),
			],
		],
	] );

	expect( str_contains( $html, 'Pagination row' ) )->toBe( $visible );
} )->with( [
	'one page'  => [ 1, false ],
	'two pages' => [ 3, true ],
] );

it( 'shows a fallback outside the loop only when the loop is empty', function ( array $items, bool $visible ): void {
	$this->resolver->setItems( $items );

	$html = renderContentVisibility( [
		contentVisibilityGroup( [ 'source' => 'query', 'condition' => 'hasResults', 'direction' => 'hide' ], [
			contentVisibilityParagraph( 'Nothing here yet' ),
			[
				'name'        => 'artisanpack/query',
				'attributes'  => [],
				'innerBlocks' => [ [ 'name' => 'artisanpack/post-template', 'attributes' => [], 'innerBlocks' => [] ] ],
			],
		] ),
	] );

	expect( str_contains( $html, 'Nothing here yet' ) )->toBe( $visible );
} )->with( [
	'empty loop'     => [ [], true ],
	'loop with rows' => [ [ (object) [ 'id' => 1, 'title' => 'One' ] ], false ],
] );
