<?php

/**
 * Current-page marking on `core/navigation-link` and
 * `core/navigation-submenu` (#975).
 *
 * WordPress parity: the link for the page being viewed gets
 * `aria-current="page"` and `current-menu-item` on its `<li>`; a link to
 * a parent path and a submenu leading to the page get
 * `current-menu-ancestor` and no `aria-current`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.2
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use Illuminate\Http\Request;

/**
 * Render a block tree as if `$url` were the request being served.
 *
 * @param  array<int, array<string, mixed>>  $tree  Block tree.
 * @param  string                            $url   Request URL.
 */
function renderNavigationOn( array $tree, string $url ): string
{
	app()->instance( 'request', Request::create( $url ) );

	return app( BlockRenderer::class )->render( $tree );
}

/**
 * @param  array<string, mixed>  $attributes  Link attributes.
 *
 * @return array<string, mixed>
 */
function navigationLink( array $attributes ): array
{
	return [ 'name' => 'core/navigation-link', 'attributes' => $attributes, 'innerBlocks' => [] ];
}

it( 'marks the link for the current page with aria-current and current-menu-item', function (): void {
	$html = renderNavigationOn( [ navigationLink( [ 'label' => 'Blog', 'url' => '/blog/' ] ) ], 'http://localhost/blog?page=2' );

	expect( $html )
		->toContain( 'class="wp-block-navigation-item wp-block-navigation-link current-menu-item"' )
		->toContain( 'href="/blog/" aria-current="page"' );
} );

it( 'marks an absolute link on the same host and never one on another host', function (): void {
	$html = renderNavigationOn( [
		navigationLink( [ 'label' => 'Here', 'url' => 'http://localhost/blog' ] ),
		navigationLink( [ 'label' => 'Elsewhere', 'url' => 'https://other.test/blog' ] ),
	], 'http://localhost/blog' );

	expect( substr_count( $html, 'aria-current="page"' ) )->toBe( 1 )
		->and( substr_count( $html, 'current-menu-item' ) )->toBe( 1 )
		->and( $html )->toContain( 'href="http://localhost/blog" aria-current="page"' );
} );

it( 'gives a parent-path link current-menu-ancestor without aria-current', function (): void {
	$html = renderNavigationOn( [ navigationLink( [ 'label' => 'Blog', 'url' => '/blog' ] ) ], 'http://localhost/blog/hello' );

	expect( $html )
		->toContain( 'class="wp-block-navigation-item wp-block-navigation-link current-menu-ancestor"' )
		->not->toContain( 'aria-current' )
		->not->toContain( 'current-menu-item' );
} );

it( 'marks the home link only on the home page', function (): void {
	$tree = [ navigationLink( [ 'label' => 'Home', 'url' => '/' ] ) ];

	expect( renderNavigationOn( $tree, 'http://localhost/' ) )->toContain( 'aria-current="page"' )
		->and( renderNavigationOn( $tree, 'http://localhost/blog' ) )->not->toContain( 'current-menu' )
		->not->toContain( 'aria-current' );
} );

it( 'gives a submenu containing the current page current-menu-ancestor', function (): void {
	$html = renderNavigationOn( [
		[
			'name'        => 'core/navigation-submenu',
			'attributes'  => [ 'label' => 'Services', 'url' => '/what-we-do' ],
			'innerBlocks' => [
				navigationLink( [ 'label' => 'Design', 'url' => '/services/design' ] ),
				navigationLink( [ 'label' => 'Build', 'url' => '/services/build' ] ),
			],
		],
	], 'http://localhost/services/design' );

	expect( $html )
		->toContain( 'class="wp-block-navigation-item wp-block-navigation-submenu has-child current-menu-ancestor"' )
		->toContain( 'href="/what-we-do"><span' )
		->toContain( 'href="/services/design" aria-current="page"' )
		->and( substr_count( $html, 'aria-current' ) )->toBe( 1 );
} );

it( 'marks a submenu whose own URL is the current page', function (): void {
	$html = renderNavigationOn( [
		[
			'name'        => 'core/navigation-submenu',
			'attributes'  => [ 'label' => 'Services', 'url' => '/services' ],
			'innerBlocks' => [ navigationLink( [ 'label' => 'Design', 'url' => '/services/design' ] ) ],
		],
	], 'http://localhost/services' );

	expect( $html )
		->toContain( 'class="wp-block-navigation-item wp-block-navigation-submenu has-child current-menu-item"' )
		->toContain( 'href="/services" aria-current="page"' );
} );

it( 'marks nothing when no link matches', function (): void {
	$html = renderNavigationOn( [
		navigationLink( [ 'label' => 'Blog', 'url' => '/blog' ] ),
		navigationLink( [ 'label' => 'Top', 'url' => '#top' ] ),
	], 'http://localhost/blogroll' );

	expect( $html )->not->toContain( 'current-menu' )->not->toContain( 'aria-current' );
} );
