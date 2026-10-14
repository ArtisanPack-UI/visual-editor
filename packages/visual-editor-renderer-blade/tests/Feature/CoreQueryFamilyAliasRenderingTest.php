<?php

declare( strict_types=1 );

/**
 * Core query-family aliases (#856) — Blade renderer parity.
 *
 * Unsaved theme files keep WordPress's `core/query-pagination*`,
 * `core/query-no-results` and `core/query-title` names. Each `core/*`
 * partial is a thin `@include` over its `artisanpack/*` fork, so these
 * tests render both names side by side and assert identical markup with
 * no unknown-block fallback.
 *
 * @since 1.0.0
 */

use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Illuminate\Support\Facades\Blade;
use Tests\Fixtures\FakeQueryResolver;

beforeEach( function (): void {
	$this->fake = new FakeQueryResolver();
	$this->app->instance( QueryResolverContract::class, $this->fake );
} );

function aliasRenderTree( array $tree ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );
}

function aliasBlockNode( string $name, array $attributes = [], array $innerBlocks = [] ): array
{
	return [
		'clientId'    => 'alias-cid',
		'name'        => $name,
		'attributes'  => $attributes,
		'innerBlocks' => $innerBlocks,
	];
}

function aliasPost( int $id ): object
{
	$post                    = new stdClass();
	$post->id                = $id;
	$post->title             = "Post {$id}";
	$post->excerpt           = '';
	$post->content           = '';
	$post->permalink         = "/posts/{$id}";
	$post->author            = null;
	$post->published_at      = null;
	$post->updated_at        = null;
	$post->featured_image_id = null;

	return $post;
}

/**
 * Build a query loop with pagination + title controls under the given
 * namespace for the control blocks. The query and post-template wrappers
 * stay `core/*` so only the aliased controls differ between the trees.
 */
function aliasQueryTree( string $namespace ): array
{
	return [
		aliasBlockNode( 'core/query', [ 'query' => [ 'postType' => 'post', 'perPage' => 2 ] ], [
			aliasBlockNode( "{$namespace}/query-title", [ 'type' => 'archive' ] ),
			aliasBlockNode( 'core/post-template', [], [
				aliasBlockNode( 'core/post-title' ),
			] ),
			aliasBlockNode( "{$namespace}/query-pagination", [], [
				aliasBlockNode( "{$namespace}/query-pagination-previous" ),
				aliasBlockNode( "{$namespace}/query-pagination-numbers" ),
				aliasBlockNode( "{$namespace}/query-pagination-next" ),
			] ),
			aliasBlockNode( "{$namespace}/query-no-results", [], [
				aliasBlockNode( 'core/paragraph', [ 'content' => 'Nothing found' ] ),
			] ),
		] ),
	];
}

dataset( 'coreQueryFamilyAliases', [
	'query-pagination'          => [ 'query-pagination', [], [ aliasBlockNode( 'core/paragraph', [ 'content' => 'Inner' ] ) ] ],
	'query-pagination-previous' => [ 'query-pagination-previous', [ 'label' => 'Older', '_resolvedPreviousPageUrl' => '/blog?page=1' ], [] ],
	'query-pagination-next'     => [ 'query-pagination-next', [ '_resolvedNextPageUrl' => '/blog?page=3' ], [] ],
	'query-pagination-numbers'  => [ 'query-pagination-numbers', [
		'_resolvedCurrentPage' => 2,
		'_resolvedPageNumbers' => [
			[ 'number' => 1, 'url' => '/blog?page=1' ],
			[ 'number' => 2, 'url' => '/blog?page=2' ],
			[ 'number' => 3, 'url' => '/blog?page=3' ],
		],
	], [] ],
	'query-no-results'          => [ 'query-no-results', [], [ aliasBlockNode( 'core/paragraph', [ 'content' => 'Nothing found' ] ) ] ],
	'query-title'               => [ 'query-title', [ 'level' => 2, '_resolvedQueryTitle' => 'Blog' ], [] ],
] );

it( 'renders the core alias identically to its artisanpack fork', function ( string $slug, array $attributes, array $innerBlocks ): void {
	$core = $this->stripGlobalStyles( aliasRenderTree( [ aliasBlockNode( "core/{$slug}", $attributes, $innerBlocks ) ] ) );
	$fork = $this->stripGlobalStyles( aliasRenderTree( [ aliasBlockNode( "artisanpack/{$slug}", $attributes, $innerBlocks ) ] ) );

	expect( $core )->not->toContain( 'data-ve-unknown-block' )
		->and( $core )->toContain( "wp-block-{$slug}" )
		->and( $this->normalizeHtml( $core ) )->toBe( $this->normalizeHtml( $fork ) );
} )->with( 'coreQueryFamilyAliases' );

it( 'renders core pagination inside a core/query loop the same as the artisanpack equivalent', function (): void {
	$this->fake->setItems( [ aliasPost( 3 ), aliasPost( 4 ) ] );
	$this->fake->perPage       = 2;
	$this->fake->currentPage   = 2;
	$this->fake->totalOverride = 6;

	$core = $this->stripGlobalStyles( aliasRenderTree( aliasQueryTree( 'core' ) ) );
	$fork = $this->stripGlobalStyles( aliasRenderTree( aliasQueryTree( 'artisanpack' ) ) );

	expect( $core )->not->toContain( 'data-ve-unknown-block' )
		->and( $core )->toContain( '<nav class="wp-block-query-pagination' )
		->and( $core )->toContain( 'wp-block-query-pagination-previous' )
		->and( $core )->toContain( 'wp-block-query-pagination-next' )
		->and( $core )->toContain( '<span class="page-numbers current" aria-current="page">2</span>' )
		->and( $core )->not->toContain( 'Nothing found' )
		->and( $this->normalizeHtml( $core ) )->toBe( $this->normalizeHtml( $fork ) );
} );

it( 'keeps core/query-no-results when the core/query loop is empty', function (): void {
	$this->fake->setItems( [] );

	$html = $this->stripGlobalStyles( aliasRenderTree( aliasQueryTree( 'core' ) ) );

	expect( $html )->not->toContain( 'data-ve-unknown-block' )
		->and( $html )->toContain( 'wp-block-query-no-results' )
		->and( $html )->toContain( 'Nothing found' );
} );

it( 'loads the pagination stylesheet for a page that only uses core pagination names', function (): void {
	$this->fake->setItems( [ aliasPost( 1 ), aliasPost( 2 ) ] );
	$this->fake->perPage       = 2;
	$this->fake->totalOverride = 4;

	$html = Blade::render(
		'<x-ve-blocks-styles /><x-ve-blocks :tree="$tree" />',
		[ 'tree' => aliasQueryTree( 'core' ) ],
	);

	expect( $html )->toContain( '/vendor/visual-editor-renderer-blade/frontend/query-pagination.css' )
		->and( $html )->toContain( 'class="wp-block-query-pagination' )
		->and( $html )->toContain( 'class="wp-block-query-pagination-next' );
} );
