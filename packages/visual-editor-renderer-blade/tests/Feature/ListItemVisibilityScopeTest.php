<?php

/**
 * Screen-size visibility scope on list-item blocks (#806).
 *
 * A screen-size visibility rule normally wraps the block in a
 * `<div class="ve-vis-N" data-ve-vis-scope>`. When the block renders a
 * single `<li>` root (`core/navigation-link`, `core/navigation-submenu`,
 * `core/list-item`, …) that wrapper would land directly inside a
 * `<ul>` / `<ol>`, which is non-conforming HTML. These tests lock in
 * that the scope class is merged onto the `<li>` itself instead.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.12.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;

/**
 * Return the tag names of every element that is a direct child of a
 * `<ul>` / `<ol>` in the given markup.
 *
 * @return array<int, string>
 */
function listItemScopeChildTagNames( string $html ): array
{
	$document = new DOMDocument();

	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();

	$names = [];

	foreach ( [ 'ul', 'ol' ] as $listTag ) {
		foreach ( $document->getElementsByTagName( $listTag ) as $list ) {
			foreach ( $list->childNodes as $child ) {
				if ( $child instanceof DOMElement ) {
					$names[] = strtolower( $child->tagName );
				}
			}
		}
	}

	return $names;
}

function listItemScopeHideAtMd(): array
{
	return [
		'screenSize' => [
			'direction'   => 'hide',
			'breakpoints' => [ 'md' ],
		],
	];
}

it( 'merges the scope onto a core/navigation-link <li> instead of wrapping it in a <div>', function () {
	$html = app( BlockRenderer::class )->render( [
		[
			'name'        => 'core/navigation',
			'attributes'  => [ 'overlayMenu' => 'mobile' ],
			'innerBlocks' => [
				[
					'name'        => 'core/navigation-link',
					'attributes'  => [ 'label' => 'Home', 'url' => '/' ],
					'innerBlocks' => [],
				],
				[
					'name'        => 'core/navigation-link',
					'attributes'  => [
						'label'                 => 'Contact',
						'url'                   => '/contact',
						'artisanpackVisibility' => listItemScopeHideAtMd(),
					],
					'innerBlocks' => [],
				],
			],
		],
	] );

	expect( $html )
		->not->toMatch( '/<div[^>]*data-ve-vis-scope/' )
		->and( $html )->toMatch( '/<li class="[^"]*wp-block-navigation-link[^"]*\bve-vis-\d+"[^>]*data-ve-vis-scope>/' )
		->and( $html )->toMatch( '/<li[^>]*data-ve-vis-scope>.*Contact.*<style>@media \(min-width:768px\) and \(max-width:1023px\)\{\.ve-vis-\d+\{display:none !important;\}\}<\/style><\/li>/s' )
		->and( substr_count( $html, 'data-ve-vis-scope' ) )->toBe( 1 )
		->and( array_unique( listItemScopeChildTagNames( $html ) ) )->toBe( [ 'li' ] );
} );

it( 'merges the scope onto a core/navigation-submenu <li> that contains nested <li>s', function () {
	$html = app( BlockRenderer::class )->render( [
		[
			'name'        => 'core/navigation',
			'attributes'  => [ 'overlayMenu' => 'mobile' ],
			'innerBlocks' => [
				[
					'name'        => 'core/navigation-submenu',
					'attributes'  => [
						'label'                 => 'Products',
						'url'                   => '/products',
						'artisanpackVisibility' => listItemScopeHideAtMd(),
					],
					'innerBlocks' => [
						[
							'name'        => 'core/navigation-link',
							'attributes'  => [ 'label' => 'Plans', 'url' => '/plans' ],
							'innerBlocks' => [],
						],
						[
							'name'        => 'core/navigation-link',
							'attributes'  => [ 'label' => 'Pricing', 'url' => '/pricing' ],
							'innerBlocks' => [],
						],
					],
				],
			],
		],
	] );

	expect( $html )
		->not->toMatch( '/<div[^>]*data-ve-vis-scope/' )
		->and( $html )->toMatch( '/<li class="[^"]*wp-block-navigation-submenu[^"]*\bve-vis-\d+"[^>]*data-ve-vis-scope>/' )
		->and( $html )->toContain( 'Plans' )
		->and( $html )->toContain( 'Pricing' )
		->and( array_unique( listItemScopeChildTagNames( $html ) ) )->toBe( [ 'li' ] );
} );

it( 'merges the scope onto a nested child link inside a submenu', function () {
	$html = app( BlockRenderer::class )->render( [
		[
			'name'        => 'core/navigation',
			'attributes'  => [ 'overlayMenu' => 'mobile' ],
			'innerBlocks' => [
				[
					'name'        => 'core/navigation-submenu',
					'attributes'  => [ 'label' => 'Products', 'url' => '/products' ],
					'innerBlocks' => [
						[
							'name'        => 'core/navigation-link',
							'attributes'  => [
								'label'                 => 'Plans',
								'url'                   => '/plans',
								'artisanpackVisibility' => listItemScopeHideAtMd(),
							],
							'innerBlocks' => [],
						],
					],
				],
			],
		],
	] );

	expect( $html )
		->not->toMatch( '/<div[^>]*data-ve-vis-scope/' )
		->and( substr_count( $html, 'data-ve-vis-scope' ) )->toBe( 1 )
		->and( array_unique( listItemScopeChildTagNames( $html ) ) )->toBe( [ 'li' ] );
} );

it( 'adds a class attribute to a core/list-item <li> that has none', function () {
	$html = app( BlockRenderer::class )->render( [
		[
			'name'        => 'core/list',
			'attributes'  => [],
			'innerBlocks' => [
				[
					'name'        => 'core/list-item',
					'attributes'  => [ 'content' => 'First' ],
					'innerBlocks' => [],
				],
				[
					'name'        => 'core/list-item',
					'attributes'  => [
						'content'               => 'Second',
						'artisanpackVisibility' => listItemScopeHideAtMd(),
					],
					'innerBlocks' => [],
				],
			],
		],
	] );

	expect( $html )
		->not->toMatch( '/<div[^>]*data-ve-vis-scope/' )
		->and( $html )->toMatch( '/<li[^>]*class="ve-vis-\d+"[^>]*data-ve-vis-scope>Second<style>/' )
		->and( array_unique( listItemScopeChildTagNames( $html ) ) )->toBe( [ 'li' ] );
} );

it( 'appends the scope to an existing custom className on a core/list-item', function () {
	$html = app( BlockRenderer::class )->render( [
		[
			'name'        => 'core/list',
			'attributes'  => [],
			'innerBlocks' => [
				[
					'name'        => 'core/list-item',
					'attributes'  => [
						'content'               => 'Only',
						'className'             => 'is-featured',
						'artisanpackVisibility' => listItemScopeHideAtMd(),
					],
					'innerBlocks' => [],
				],
			],
		],
	] );

	expect( $html )
		->toMatch( '/<li[^>]*class="[^"]*\bis-featured ve-vis-\d+"[^>]*data-ve-vis-scope>/' )
		->and( $html )->not->toMatch( '/<li[^>]*\sclass=[^>]*\sclass=/' );
} );

it( 'keeps the <div> scope wrapper for blocks that do not render a single <li> root', function () {
	$html = app( BlockRenderer::class )->render( [
		[
			'name'        => 'core/paragraph',
			'attributes'  => [
				'content'               => 'Hello',
				'artisanpackVisibility' => listItemScopeHideAtMd(),
			],
			'innerBlocks' => [],
		],
	] );

	expect( $html )
		->toMatch( '/<div class="ve-vis-\d+" data-ve-vis-scope><p[^>]*>Hello<\/p>\s*<style>/' );
} );

/**
 * Invoke the protected `mergeScopeIntoListItem()` on the container's renderer.
 */
function listItemScopeMerge( string $html ): ?string
{
	return ( fn ( string $markup ): ?string => $this->mergeScopeIntoListItem(
		$markup,
		've-vis-7',
		'@media (min-width:768px){.ve-vis-7{display:none !important;}}'
	) )->call( app( BlockRenderer::class ), $html );
}

it( 'keeps a quoted attribute value that contains ">" intact', function () {
	expect( listItemScopeMerge( '<li title="a > b" class="item">Text</li>' ) )
		->toBe( '<li title="a > b" class="item ve-vis-7" data-ve-vis-scope>Text<style>@media (min-width:768px){.ve-vis-7{display:none !important;}}</style></li>' );
} );

it( 'merges into an unquoted class value instead of adding a duplicate class attribute', function () {
	$html = listItemScopeMerge( '<li class=featured>Text</li>' );

	expect( $html )->toStartWith( '<li class="featured ve-vis-7" data-ve-vis-scope>' )
		->and( substr_count( (string) $html, 'class=' ) )->toBe( 1 );
} );

it( 'merges into a single-quoted class value and escapes embedded double quotes', function () {
	expect( listItemScopeMerge( "<li class='a' data-x='say \"hi\"'>Text</li>" ) )
		->toStartWith( '<li class="a ve-vis-7" data-x=\'say "hi"\' data-ve-vis-scope>' );
} );

it( 'does not mistake a class= inside another attribute value for the class attribute', function () {
	expect( listItemScopeMerge( '<li title="class=x">Text</li>' ) )
		->toStartWith( '<li title="class=x" class="ve-vis-7" data-ve-vis-scope>' );
} );

it( 'preserves boolean attributes', function () {
	expect( listItemScopeMerge( '<li hidden class="a">Text</li>' ) )
		->toStartWith( '<li hidden class="a ve-vis-7" data-ve-vis-scope>' );
} );

it( 'falls back to the wrapper for sibling <li>s, non-<li> roots, and unterminated tags', function ( string $html ) {
	expect( listItemScopeMerge( $html ) )->toBeNull();
} )->with( [
	'siblings'         => '<li>a</li><li>b</li>',
	'non-li root'      => '<div>a</div>',
	'link element'     => '<link rel="x"></li>',
	'unterminated tag' => '<li title="a>b</li>',
] );
