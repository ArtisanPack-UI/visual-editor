<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use Illuminate\Http\Request;

/**
 * #961 — the Search and Search Filters forms used to submit to `/` (the
 * homepage) whatever page they sat on. Their `action` attribute now picks
 * the site search URL (default), the current page, or a same-site path,
 * and the Search input shows the current search term.
 */

function renderSearchForm( string $blockName, array $attributes = [] ): string
{
	return app( BlockRenderer::class )->renderBlock( [
		'name'        => $blockName,
		'attributes'  => $attributes,
		'innerBlocks' => [],
	] );
}

function fakeSearchRequest( string $uri ): void
{
	app()->instance( 'request', Request::create( $uri ) );
}

dataset( 'search form blocks', [
	'artisanpack/search'         => [ 'artisanpack/search' ],
	'core/search'                => [ 'core/search' ],
	'artisanpack/search-filters' => [ 'artisanpack/search-filters' ],
] );

it( 'submits to the configured site search URL by default', function ( string $blockName ): void {
	expect( renderSearchForm( $blockName ) )->toContain( 'action="/search"' );

	config()->set( 'artisanpack.visual-editor.search.url', '/find' );

	expect( renderSearchForm( $blockName, [ 'action' => '' ] ) )->toContain( 'action="/find"' );
} )->with( 'search form blocks' );

it( 'submits to the current page without its query string or pagination', function ( string $blockName, string $uri, string $expected ): void {
	fakeSearchRequest( $uri );

	expect( renderSearchForm( $blockName, [ 'action' => 'current' ] ) )->toContain( 'action="' . $expected . '"' );
} )->with( 'search form blocks' )->with( [
	'query string'          => [ '/blog?s=theme&page=2', '/blog' ],
	'page segment'          => [ '/blog/page/3', '/blog' ],
	'page segment + query'  => [ '/packages/page/12/?s=ui', '/packages' ],
	'site root'             => [ '/?page=2', '/' ],
	'non-page tail kept'    => [ '/blog/pages/3', '/blog/pages/3' ],
] );

it( 'keeps the current-page action same-site for a request path with leading slashes or backslashes', function ( string $blockName, string $requestUri, string $expected ): void {
	// Request::create() would read `//host` as an authority; a server hands
	// the raw path through REQUEST_URI, so build the request from that.
	app()->instance( 'request', new Request( [], [], [], [], [], [
		'REQUEST_URI' => $requestUri,
		'HTTP_HOST'   => 'site.test',
		'SERVER_NAME' => 'site.test',
	] ) );

	$html = renderSearchForm( $blockName, [ 'action' => 'current' ] );

	expect( $html )->toContain( 'action="' . $expected . '"' )
		->and( $html )->not->toContain( 'action="//' )
		->and( $html )->not->toContain( 'action="/\\' );
} )->with( 'search form blocks' )->with( [
	'protocol-relative + page' => [ '//evil.com/page/2', '/evil.com' ],
	'double slash'             => [ '//packages', '/packages' ],
	'slash backslash'          => [ '/\\evil.com', '/evil.com' ],
	'mixed leading run'        => [ '/\\/\\evil.com/x', '/evil.com/x' ],
	'backslash later in path'  => [ '//blog\\evil', '/' ],
	'normal path'              => [ '/blog', '/blog' ],
	'nested path'              => [ '/blog/category/x', '/blog/category/x' ],
	'nested path + page'       => [ '/blog/category/x/page/4', '/blog/category/x' ],
	'site root'                => [ '/', '/' ],
] );

it( 'submits to a custom same-site path', function ( string $blockName ): void {
	expect( renderSearchForm( $blockName, [ 'action' => '/blog' ] ) )->toContain( 'action="/blog"' );
} )->with( 'search form blocks' );

it( 'rejects custom actions that are not same-site paths', function ( string $blockName, string $action ): void {
	$html = renderSearchForm( $blockName, [ 'action' => $action ] );

	expect( $html )->toContain( 'action="/search"' )
		->and( $html )->not->toContain( 'evil' );
} )->with( 'search form blocks' )->with( [
	'absolute URL'      => [ 'https://evil.test/search' ],
	'protocol-relative' => [ '//evil.test/search' ],
	'backslash'         => [ '/\\evil.test' ],
	'javascript'        => [ 'javascript:alert(1)//evil' ],
	'relative no slash' => [ 'evil/search' ],
	'embedded space'    => [ '/evil path' ],
] );

it( 'lets a host-stamped _resolvedFormAction win over the action attribute', function ( string $blockName ): void {
	$html = renderSearchForm( $blockName, [
		'action'              => '/blog',
		'_resolvedFormAction' => 'https://example.test/archive',
	] );

	expect( $html )->toContain( 'action="https://example.test/archive"' );
} )->with( 'search form blocks' );

it( 'ignores an unsafe stamped _resolvedFormAction', function (): void {
	$html = renderSearchForm( 'artisanpack/search', [
		'action'              => '/blog',
		'_resolvedFormAction' => 'javascript:alert(1)',
	] );

	expect( $html )->toContain( 'action="/blog"' )
		->and( $html )->not->toContain( 'javascript:' );
} );

it( 'keeps the hidden post_type input on search filters', function (): void {
	$html = renderSearchForm( 'artisanpack/search-filters', [ 'postType' => 'package', 'action' => 'current' ] );

	expect( $html )->toContain( '<input type="hidden" name="post_type" value="package" />' );
} );

it( 'prefills the search input from the request, escaped', function (): void {
	fakeSearchRequest( '/blog?s=' . rawurlencode( '"><script>alert(1)</script>' ) );

	$html = renderSearchForm( 'artisanpack/search' );

	expect( $html )->toContain( 'value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"' )
		->and( $html )->not->toContain( '<script>' );
} );

it( 'prefills from the block\'s custom query name', function (): void {
	fakeSearchRequest( '/packages?q=forms&s=ignored' );

	$html = renderSearchForm( 'artisanpack/search', [ 'query' => [ 'name' => 'q' ] ] );

	expect( $html )->toContain( 'name="q" value="forms"' );
} );

it( 'prefers a stamped _resolvedSearchValue and ignores array query values', function (): void {
	fakeSearchRequest( '/blog?s[]=a' );

	expect( renderSearchForm( 'artisanpack/search' ) )->toContain( 'name="s" value=""' );
	expect( renderSearchForm( 'artisanpack/search', [ '_resolvedSearchValue' => 'stamped' ] ) )->toContain( 'name="s" value="stamped"' );
} );
