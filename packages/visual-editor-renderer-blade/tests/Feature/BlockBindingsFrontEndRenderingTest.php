<?php

/**
 * Front-end block binding resolution through `<x-ve-blocks :post>` (#848).
 *
 * The editor canvas resolves bindings against the loaded record; these
 * tests pin the front-end render path to the same behaviour, including
 * nested renders and the no-post fallback.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @since      1.14.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;

/**
 * Build an in-memory Eloquent record for bindings to resolve against.
 *
 * @param  array<string, mixed>  $attributes
 */
function bindingTestPost( array $attributes ): Model
{
	$post = new class extends Model {
		protected $table = 'posts';
	};

	$post->setRawAttributes( $attributes, true );

	return $post;
}

/**
 * Build a canonical-shape block with an optional bindings sidecar.
 *
 * @param  array<string, mixed>              $attributes
 * @param  array<string, array<string, mixed>>  $bindings
 * @param  array<int, array<string, mixed>>  $innerBlocks
 *
 * @return array<string, mixed>
 */
function boundBlock( string $name, array $attributes = [], array $bindings = [], array $innerBlocks = [], string $clientId = 'cid' ): array
{
	if ( [] !== $bindings ) {
		$attributes['bindings'] = $bindings;
	}

	return [
		'clientId'    => $clientId,
		'name'        => $name,
		'attributes'  => $attributes,
		'innerBlocks' => $innerBlocks,
	];
}

/**
 * @param  array<int, array<string, mixed>>  $tree
 */
function renderBoundTree( array $tree, mixed $post = null ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" :post="$post" />', [
		'tree' => $tree,
		'post' => $post,
	] );
}

/**
 * @return array<int, array<string, mixed>>
 */
function customFieldTree( string $onEmpty = 'fallback' ): array
{
	return [
		boundBlock( 'core/paragraph', [ 'content' => 'v' ], [
			'content' => [ 'source' => 'custom_field', 'args' => [ 'key' => 'version' ], 'onEmpty' => $onEmpty, 'placeholder' => 'n/a' ],
		], [], 'p-1' ),
		boundBlock( 'core/image', [ 'url' => 'https://example.com/placeholder.png' ], [
			'url' => [ 'source' => 'custom_field', 'args' => [ 'key' => 'icon' ], 'onEmpty' => $onEmpty, 'placeholder' => 'https://example.com/empty.png' ],
		], [], 'img-1' ),
	];
}

it( 'resolves custom_field bindings against the host post', function (): void {
	$post = bindingTestPost( [ 'version' => '1.3.1', 'icon' => 'https://example.com/atom.svg' ] );

	$html = renderBoundTree( customFieldTree(), $post );

	expect( $html )->toContain( '<p class="wp-block-paragraph">1.3.1</p>' )
		->and( $html )->toContain( 'src="https://example.com/atom.svg"' )
		->and( $html )->not->toContain( '>v</p>' )
		->and( $html )->not->toContain( 'placeholder.png' );
} );

it( 'keeps resolved values under the hide and placeholder policies', function ( string $policy ): void {
	$post = bindingTestPost( [ 'version' => '1.3.1', 'icon' => 'https://example.com/atom.svg' ] );

	$html = renderBoundTree( customFieldTree( $policy ), $post );

	expect( $html )->toContain( '<p class="wp-block-paragraph">1.3.1</p>' )
		->and( $html )->toContain( 'src="https://example.com/atom.svg"' )
		->and( $html )->not->toContain( 'n/a' )
		->and( $html )->not->toContain( 'empty.png' );
} )->with( [ 'hide', 'placeholder' ] );

it( 'resolves post_core and relation bindings against the host post', function (): void {
	$post   = bindingTestPost( [ 'title' => 'Core Package' ] );
	$author = bindingTestPost( [ 'name' => 'Ada Lovelace' ] );
	$post->setRelation( 'author', $author );

	$tree = [
		boundBlock( 'core/paragraph', [ 'content' => 'Static title' ], [
			'content' => [ 'source' => 'post_core', 'args' => [ 'key' => 'title' ] ],
		], [], 'p-title' ),
		boundBlock( 'core/paragraph', [ 'content' => 'Static author' ], [
			'content' => [ 'source' => 'relation', 'args' => [ 'path' => 'author.name' ] ],
		], [], 'p-author' ),
	];

	$html = renderBoundTree( $tree, $post );

	expect( $html )->toContain( '<p class="wp-block-paragraph">Core Package</p>' )
		->and( $html )->toContain( '<p class="wp-block-paragraph">Ada Lovelace</p>' )
		->and( $html )->not->toContain( 'Static title' )
		->and( $html )->not->toContain( 'Static author' );
} );

it( 'resolves bindings inside nested accordion renders against the same post', function (): void {
	$post = bindingTestPost( [ 'version' => '1.3.1' ] );

	// `hide` would blank the paragraph if the nested `render()` call ran
	// with a model-less context, so this also pins the reuse behaviour.
	$tree = [
		boundBlock( 'artisanpack/accordions', [], [], [
			boundBlock( 'artisanpack/accordion', [ 'panelId' => 'faq-1' ], [], [
				boundBlock( 'artisanpack/accordion-title', [], [], [
					boundBlock( 'core/heading', [ 'level' => 3, 'content' => 'Version' ], [], [], 'h-1' ),
				], 'title-1' ),
				boundBlock( 'artisanpack/accordion-body', [], [], [
					boundBlock( 'core/paragraph', [ 'content' => 'v' ], [
						'content' => [ 'source' => 'custom_field', 'args' => [ 'key' => 'version' ], 'onEmpty' => 'hide' ],
					], [], 'p-1' ),
				], 'body-1' ),
			], 'panel-1' ),
		], 'acc-1' ),
	];

	$html = renderBoundTree( $tree, $post );

	expect( $html )->toContain( '<p class="wp-block-paragraph">1.3.1</p>' );
} );

it( 'resolves bindings inside nested tab renders against the same post', function (): void {
	$post = bindingTestPost( [ 'version' => '1.3.1' ] );

	$tree = [
		boundBlock( 'artisanpack/tabs', [], [], [
			boundBlock( 'artisanpack/tab-section', [ 'label' => 'Details', 'tabId' => 'details' ], [], [
				boundBlock( 'core/paragraph', [ 'content' => 'v' ], [
					'content' => [ 'source' => 'custom_field', 'args' => [ 'key' => 'version' ], 'onEmpty' => 'hide' ],
				], [], 'p-1' ),
			], 'tab-1' ),
		], 'tabs-1' ),
	];

	$html = renderBoundTree( $tree, $post );

	expect( $html )->toContain( '<p class="wp-block-paragraph">1.3.1</p>' );
} );

it( 'applies the fallback policy when no post is supplied', function (): void {
	$html = renderBoundTree( customFieldTree() );

	expect( $html )->toContain( '<p class="wp-block-paragraph">v</p>' )
		->and( $html )->toContain( 'src="https://example.com/placeholder.png"' );
} );

it( 'applies the fallback policy when the post is not an Eloquent model', function (): void {
	$post = (object) [ 'version' => '1.3.1', 'icon' => 'https://example.com/atom.svg' ];

	$html = renderBoundTree( customFieldTree(), $post );

	expect( $html )->toContain( '<p class="wp-block-paragraph">v</p>' )
		->and( $html )->toContain( 'src="https://example.com/placeholder.png"' );
} );

it( 'applies the placeholder policy when no post is supplied', function (): void {
	$html = renderBoundTree( customFieldTree( 'placeholder' ) );

	expect( $html )->toContain( '<p class="wp-block-paragraph">n/a</p>' )
		->and( $html )->toContain( 'src="https://example.com/empty.png"' );
} );

it( 'does not carry a binding context over to the next top-level render', function (): void {
	$renderer = app( BlockRenderer::class );
	$tree     = [
		boundBlock( 'core/paragraph', [ 'content' => 'v' ], [
			'content' => [ 'source' => 'custom_field', 'args' => [ 'key' => 'version' ] ],
		], [], 'p-1' ),
	];

	$first  = $renderer->render( $tree, new BindingContext( bindingTestPost( [ 'version' => '1.3.1' ] ) ) );
	$second = $renderer->render( $tree );

	expect( $first )->toContain( '1.3.1' )
		->and( $second )->toContain( '<p class="wp-block-paragraph">v</p>' );
} );

it( 'derives heading anchors and TOC entries from bound heading content (#851)', function (): void {
	$post = bindingTestPost( [ 'title' => 'Core Package', 'version' => 'Version 1.3' ] );

	$tree = [
		boundBlock( 'artisanpack/toc', [], [], [], 'toc-1' ),
		boundBlock( 'core/heading', [ 'level' => 2, 'content' => 'Static title' ], [
			'content' => [ 'source' => 'post_core', 'args' => [ 'key' => 'title' ] ],
		], [], 'h-1' ),
		boundBlock( 'core/group', [], [], [
			boundBlock( 'artisanpack/heading', [ 'level' => 3, 'content' => 'Static version' ], [
				'content' => [ 'source' => 'custom_field', 'args' => [ 'key' => 'version' ] ],
			], [], 'h-2' ),
		], 'group-1' ),
	];

	$html = renderBoundTree( $tree, $post );

	expect( $html )->toContain( 'id="core-package"' )
		->and( $html )->toContain( 'href="#core-package"' )
		->and( $html )->toContain( 'id="version-1-3"' )
		->and( $html )->toContain( 'href="#version-1-3"' )
		->and( $html )->not->toContain( 'static-title' )
		->and( $html )->not->toContain( 'static-version' );
} );

it( 'keeps author-set anchors on bound headings', function (): void {
	$post = bindingTestPost( [ 'title' => 'Core Package' ] );

	$tree = [
		boundBlock( 'artisanpack/toc', [], [], [], 'toc-1' ),
		boundBlock( 'core/heading', [ 'level' => 2, 'content' => 'Static title', 'anchor' => 'overview' ], [
			'content' => [ 'source' => 'post_core', 'args' => [ 'key' => 'title' ] ],
		], [], 'h-1' ),
	];

	$html = renderBoundTree( $tree, $post );

	expect( $html )->toContain( 'id="overview"' )
		->and( $html )->toContain( 'href="#overview"' )
		->and( $html )->not->toContain( 'id="core-package"' );
} );

it( 'leaves no orphan TOC entry for a heading hidden by an empty binding', function (): void {
	$post = bindingTestPost( [ 'title' => 'Core Package' ] );

	$tree = [
		boundBlock( 'artisanpack/toc', [], [], [], 'toc-1' ),
		boundBlock( 'core/heading', [ 'level' => 2, 'content' => 'Static subtitle' ], [
			'content' => [ 'source' => 'custom_field', 'args' => [ 'key' => 'subtitle' ], 'onEmpty' => 'hide' ],
		], [], 'h-1' ),
	];

	$html = renderBoundTree( $tree, $post );

	expect( $html )->not->toContain( 'static-subtitle' )
		->and( $html )->not->toContain( 'Static subtitle' )
		->and( $html )->toContain( 'No headings found on this page yet.' );
} );

it( 'derives anchors from the fallback content when no post is supplied', function (): void {
	$tree = [
		boundBlock( 'artisanpack/toc', [], [], [], 'toc-1' ),
		boundBlock( 'core/heading', [ 'level' => 2, 'content' => 'Static title' ], [
			'content' => [ 'source' => 'post_core', 'args' => [ 'key' => 'title' ] ],
		], [], 'h-1' ),
	];

	$html = renderBoundTree( $tree );

	expect( $html )->toContain( 'id="static-title"' )
		->and( $html )->toContain( 'href="#static-title"' );
} );
