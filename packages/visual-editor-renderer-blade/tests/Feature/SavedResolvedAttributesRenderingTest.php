<?php

/**
 * `<x-ve-blocks>` renders what its resolvers compute, not `_resolved*`
 * values saved in the block (#1078, #1096). Every saved `_resolved*` key
 * is stripped, with or without a post, unless the host passes
 * `trust-resolved`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use ArtisanPackUI\VisualEditorRendererBlade\Services\BlockTreeReferenceResolver;
use ArtisanPackUI\VisualEditorRendererBlade\View\Components\BlocksComponent;
use Illuminate\Support\Facades\Blade;

function spoofedSocialTree(): array
{
	return [
		[
			'clientId'    => 'ssc-spoofed',
			'name'        => 'artisanpack/social-share-content',
			'attributes'  => [
				'socialIcons'         => [ 'facebook' ],
				'_resolvedShareLinks' => [ [ 'slug' => 'facebook', 'url' => 'https://evil.test/share' ] ],
			],
			'innerBlocks' => [],
		],
		[
			'clientId'    => 'asi-spoofed',
			'name'        => 'artisanpack/author-social-icons',
			'attributes'  => [
				'socialIcons'                => [ 'facebook' ],
				'_resolvedAuthorSocialLinks' => [ [ 'slug' => 'facebook', 'url' => 'https://evil.test/author' ] ],
			],
			'innerBlocks' => [],
		],
	];
}

function spoofedSocialPost(): object
{
	$post                    = new stdClass();
	$post->id                = 1;
	$post->title             = 'Hello world';
	$post->permalink         = 'https://example.test/posts/hello';
	$post->featured_image_id = null;
	$post->author            = (object) [
		'name'     => 'Jane Doe',
		'facebook' => 'https://example.test/jane-fb',
	];

	return $post;
}

it( 'renders the links the post resolves to instead of saved ones', function (): void {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" :post="$post" />', [
		'tree' => spoofedSocialTree(),
		'post' => spoofedSocialPost(),
	] );

	expect( $rendered )
		->not->toContain( 'evil.test' )
		->toContain( 'https://www.facebook.com/sharer.php?u=' . rawurlencode( 'https://example.test/posts/hello' ) )
		->toContain( 'href="https://example.test/jane-fb"' );
} );

it( 'strips saved values when no post is in scope', function (): void {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => spoofedSocialTree() ] );

	expect( $rendered )->not->toContain( 'evil.test' );
} );

it( 'keeps host-resolved values when the host trusts the tree', function (): void {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" :post="$post" :trust-resolved="true" />', [
		'tree' => spoofedSocialTree(),
		'post' => spoofedSocialPost(),
	] );

	expect( $rendered )
		->toContain( 'href="https://evil.test/share"' )
		->toContain( 'href="https://evil.test/author"' );
} );

it( 'strips saved values when post resolution is off', function (): void {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" :post="$post" :resolve-post="false" />', [
		'tree' => spoofedSocialTree(),
		'post' => spoofedSocialPost(),
	] );

	expect( $rendered )->not->toContain( 'evil.test' );
} );

it( 'keeps host-resolved values without a post when the host trusts the tree', function (): void {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" :trust-resolved="true" />', [ 'tree' => spoofedSocialTree() ] );

	expect( $rendered )->toContain( 'href="https://evil.test/share"' );
} );

/**
 * One block node in the `attributes` shape.
 *
 * @param  array<string, mixed>              $attributes
 * @param  array<int, array<string, mixed>>  $innerBlocks
 */
function savedResolvedBlock( string $name, array $attributes = [], array $innerBlocks = [] ): array
{
	return [
		'clientId'    => $name . '-cid',
		'name'        => $name,
		'attributes'  => $attributes,
		'innerBlocks' => $innerBlocks,
	];
}

/**
 * The tree `<x-ve-blocks>` hands its renderer, after every pass.
 *
 * @param  array<int, array<string, mixed>>  $tree
 * @param  array<string, mixed>              $props  Other component props.
 *
 * @return array<int, array<string, mixed>>
 */
function savedResolvedComponentTree( array $tree, array $props = [] ): array
{
	return app()->make( BlocksComponent::class, array_merge( [ 'tree' => $tree ], $props ) )->tree;
}

describe( 'blocks other resolvers stamp (#1096)', function (): void {
	beforeEach( function (): void {
		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 'ap.visualEditor.businessInfo' );
		}
	} );

	it( 'ignores a saved login URL and login form', function (): void {
		$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [
			'tree' => [
				savedResolvedBlock( 'artisanpack/loginout', [
					'redirectToCurrent'      => false,
					'_resolvedLoginoutUrl'   => 'https://evil.test/login',
					'_resolvedLoginFormHtml' => '<form action="https://evil.test/steal"></form>',
				] ),
			],
		] );

		expect( $rendered )
			->not->toContain( 'evil.test' )
			->toContain( '/login' );
	} );

	it( 'ignores a saved site title and URL', function ( string $name ): void {
		$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [
			'tree' => [
				savedResolvedBlock( $name, [
					'_resolvedSiteTitle' => 'Spoofed Title',
					'_resolvedSiteUrl'   => 'https://evil.test',
				] ),
			],
		] );

		expect( $rendered )
			->not->toContain( 'evil.test' )
			->not->toContain( 'Spoofed Title' );
	} )->with( [ 'core/site-title', 'artisanpack/site-title' ] );

	it( 'ignores a saved business envelope and map embed URL', function (): void {
		$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [
			'tree' => [
				savedResolvedBlock( 'artisanpack/business-address', [
					'showMap'               => true,
					'mapProvider'           => 'osm',
					'_resolvedBusinessInfo' => [
						'address'     => [ 'street' => '1 Spoofed Way' ],
						'mapEmbedUrl' => 'https://evil.test/map',
					],
				] ),
				savedResolvedBlock( 'artisanpack/business-phone', [
					'_resolvedBusinessInfo' => [ 'phone' => '+1 555-SPOOFED' ],
				] ),
			],
		] );

		expect( $rendered )
			->not->toContain( 'evil.test' )
			->not->toContain( 'Spoofed' )
			->not->toContain( '<iframe' );
	} );

	it( 'ignores a saved breadcrumb trail', function (): void {
		$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [
			'tree' => [
				savedResolvedBlock( 'artisanpack/breadcrumbs', [
					'_resolvedTrail' => [
						[ 'label' => 'Spoofed', 'url' => 'https://evil.test/crumb', 'isCurrent' => false ],
					],
				] ),
			],
		] );

		expect( $rendered )
			->not->toContain( 'evil.test' )
			->not->toContain( 'Spoofed' );
	} );

	it( 'ignores a saved comment author and reply link in a comment template', function (): void {
		$tree = savedResolvedComponentTree( [
			savedResolvedBlock( 'artisanpack/comments', [], [
				savedResolvedBlock( 'artisanpack/comment-template', [], [
					savedResolvedBlock( 'artisanpack/comment-author-name', [ '_resolvedAuthorUrl' => 'https://evil.test/author' ] ),
					savedResolvedBlock( 'artisanpack/comment-reply-link', [ '_resolvedReplyLinkUrl' => 'https://evil.test/reply' ] ),
				] ),
			] ),
		] );

		expect( json_encode( $tree ) )->not->toContain( 'evil.test' );
	} );

	it( 'computes the Single Content state instead of using a saved one', function (): void {
		$tree = savedResolvedComponentTree( [
			savedResolvedBlock( 'artisanpack/single-content', [ '_resolvedHasPost' => true ] ),
		] );

		expect( $tree[0]['attributes']['_resolvedHasPost'] )->toBeFalse();
	} );

	it( 'ignores a saved permalink and a saved next-page URL with no post in scope', function (): void {
		$tree = savedResolvedComponentTree( [
			savedResolvedBlock( 'artisanpack/post-title', [
				'isLink'             => true,
				'_resolvedTitle'     => 'Spoofed',
				'_resolvedPermalink' => 'https://evil.test/post',
			] ),
			savedResolvedBlock( 'artisanpack/query-pagination-next', [
				'_resolvedNextPageUrl' => 'https://evil.test/page/2',
			] ),
		] );

		expect( json_encode( $tree ) )->not->toContain( 'evil.test' );
	} );

	it( 'ignores saved values in a template rendered through x-ve-template', function (): void {
		$template = new class() {
			public function resolve( string $slug ): ?object
			{
				return (object) [
					'slug'   => $slug,
					'blocks' => [
						savedResolvedBlock( 'artisanpack/loginout', [
							'redirectToCurrent'    => false,
							'_resolvedLoginoutUrl' => 'https://evil.test/login',
						] ),
					],
				];
			}
		};

		// TemplateComponent looks its resolver up under the leading-backslash FQCN.
		app()->bind( '\\ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplateResolver', fn () => $template );

		$rendered = Blade::render( '<x-ve-template slug="index" theme="artisanpack-ui" />' );

		expect( $rendered )
			->toContain( 'wp-block-artisanpack-loginout' )
			->not->toContain( 'evil.test' );
	} );

	it( 'ignores saved values in markup rendered through renderMarkup()', function (): void {
		$html = app( BlockRenderer::class )->renderMarkup(
			'<!-- wp:artisanpack/loginout {"redirectToCurrent":false,"_resolvedLoginoutUrl":"https://evil.test/login"} /-->',
		);

		expect( $html )
			->toContain( 'wp-block-artisanpack-loginout' )
			->not->toContain( 'evil.test' );
	} );

	it( 'strips saved values from a navigation overlay part', function (): void {
		$tree = app( BlockTreeReferenceResolver::class )->resolveStandalone( [
			savedResolvedBlock( 'artisanpack/loginout', [ '_resolvedLoginoutUrl' => 'https://evil.test/login' ] ),
		] );

		expect( $tree[0]['attributes'] )->not->toHaveKey( '_resolvedLoginoutUrl' );
	} );

	it( 'keeps every saved value when the host trusts the tree', function (): void {
		$tree = savedResolvedComponentTree( [
			savedResolvedBlock( 'artisanpack/breadcrumbs', [
				'_resolvedTrail' => [ [ 'label' => 'Host', 'url' => 'https://host.test/', 'isCurrent' => true ] ],
			] ),
			savedResolvedBlock( 'artisanpack/query-pagination-next', [
				'_resolvedNextPageUrl' => 'https://host.test/page/2',
			] ),
		], [ 'trustResolved' => true ] );

		expect( $tree[0]['attributes']['_resolvedTrail'][0]['url'] )->toBe( 'https://host.test/' )
			->and( $tree[1]['attributes']['_resolvedNextPageUrl'] )->toBe( 'https://host.test/page/2' );
	} );
} );
