<?php

declare( strict_types=1 );

/**
 * Phase I5 entity-cluster (#413) — Blade renderer parity.
 *
 * Each forked `artisanpack/*` entity partial is a thin `@include` over its
 * `core/*` counterpart, so the two must emit byte-identical markup for the
 * same attributes. These tests render the fork and its core counterpart
 * side by side and assert equality, plus confirm `artisanpack/site-*`
 * blocks pick up the `_resolvedSite*` stamping the same way `core/site-*`
 * do.
 *
 * @since 1.0.0
 */

use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\SiteMetaResolver;
use Illuminate\Support\Facades\Blade;

function forkRenderTree( array $tree ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );
}

function forkBlockNode( string $name, array $attributes = [], array $innerBlocks = [] ): array
{
	return [
		'clientId'    => 'fork-cid',
		'name'        => $name,
		'attributes'  => $attributes,
		'innerBlocks' => $innerBlocks,
	];
}

afterEach( function (): void {
	app( SiteMetaResolver::class )->flush();
} );

dataset( 'entityForkParity', [
	'post-title'   => [ 'post-title', [ 'level' => 3, '_resolvedTitle' => 'Hello World' ] ],
	'post-content' => [ 'post-content', [ '_resolvedContent' => '<p>Body</p>' ] ],
	'post-excerpt' => [ 'post-excerpt', [ 'moreText' => 'Read more', '_resolvedExcerpt' => 'Snippet', '_resolvedPermalink' => 'https://example.test/p' ] ],
	'post-author'  => [ 'post-author', [ '_resolvedAuthorName' => 'Jane Doe' ] ],
] );

it( 'renders the artisanpack fork identically to its core counterpart', function ( string $slug, array $attributes ) {
	$core = $this->stripGlobalStyles( forkRenderTree( [ forkBlockNode( "core/$slug", $attributes ) ] ) );
	$fork = $this->stripGlobalStyles( forkRenderTree( [ forkBlockNode( "artisanpack/$slug", $attributes ) ] ) );

	expect( $this->normalizeHtml( $fork ) )->toBe( $this->normalizeHtml( $core ) );
} )->with( 'entityForkParity' );

it( 'renders artisanpack/post-title at the configured level via the core partial', function () {
	$rendered = $this->stripGlobalStyles( forkRenderTree( [
		forkBlockNode( 'artisanpack/post-title', [ 'level' => 3, '_resolvedTitle' => 'Hello World' ] ),
	] ) );

	expect( $this->normalizeHtml( $rendered ) )->toContain( '<h3 class="wp-block-post-title">Hello World</h3>' );
} );

it( 'stamps _resolvedSite* onto artisanpack/site-* blocks from config defaults', function () {
	config()->set( 'artisanpack.visual-editor.site_meta', [
		'title'       => 'Config Title',
		'description' => 'Config Tagline',
		'url'         => 'https://config.example',
		'logo_id'     => null,
		'icon_id'     => null,
	] );

	app( SiteMetaResolver::class )->flush();

	$rendered = $this->stripGlobalStyles( forkRenderTree( [
		forkBlockNode( 'artisanpack/site-title' ),
		forkBlockNode( 'artisanpack/site-tagline' ),
	] ) );

	expect( $rendered )
		->toContain( 'Config Title' )
		->toContain( 'href="https://config.example"' )
		->toContain( '<p class="wp-block-site-tagline">Config Tagline</p>' );
} );

it( 'renders artisanpack/navigation inner blocks through the core partial', function () {
	$rendered = $this->stripGlobalStyles( forkRenderTree( [
		forkBlockNode( 'artisanpack/navigation', [], [
			forkBlockNode( 'core/navigation-link', [ 'label' => 'Home', 'url' => 'https://example.test' ] ),
		] ),
	] ) );

	expect( $rendered )->toContain( 'wp-block-navigation' );
} );
