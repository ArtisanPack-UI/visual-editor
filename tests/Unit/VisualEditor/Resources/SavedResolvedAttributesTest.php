<?php

/**
 * Saved `_resolved*` attributes can't replace what {@see PostResolver}
 * computes (#1078), while values a host resolves itself still win.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use ArtisanPackUI\VisualEditor\Resources\QueryInliner;
use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Tests\Fixtures\FakeQueryResolver;

function savedResolvedPost( int $id = 1, string $title = 'Hello world' ): object
{
	$post                    = new stdClass();
	$post->id                = $id;
	$post->title             = $title;
	$post->permalink         = "https://example.test/posts/{$id}";
	$post->featured_image_id = null;
	$post->published_at      = null;
	$post->updated_at        = null;
	$post->author            = (object) [
		'name'     => 'Jane Doe',
		'facebook' => 'https://example.test/jane-fb',
	];

	return $post;
}

function spoofedShareBlock(): array
{
	return [
		'name'        => 'artisanpack/social-share-content',
		'attributes'  => [
			'socialIcons'         => [ 'facebook' ],
			'_resolvedShareLinks' => [ [ 'slug' => 'facebook', 'url' => 'https://evil.test/' ] ],
		],
		'innerBlocks' => [],
	];
}

function shareUrlFor( array $block, string $slug ): ?string
{
	foreach ( $block['attributes']['_resolvedShareLinks'] ?? [] as $link ) {
		if ( $slug === $link['slug'] ) {
			return $link['url'];
		}
	}

	return null;
}

describe( 'PostResolver::stripSavedAttributes()', function (): void {
	it( 'strips the _resolved* keys from every block the resolver stamps', function (): void {
		$stripped = ( new PostResolver() )->stripSavedAttributes( [
			spoofedShareBlock(),
			[
				'name'        => 'artisanpack/author-social-icons',
				'attributes'  => [
					'socialIcons'                => [ 'facebook' ],
					'_resolvedAuthorSocialLinks' => [ [ 'slug' => 'facebook', 'url' => 'https://evil.test/' ] ],
				],
				'innerBlocks' => [],
			],
			[
				'name'        => 'core/post-title',
				'attributes'  => [ 'level' => 2, '_resolvedTitle' => 'Spoofed', '_resolvedPermalink' => 'https://evil.test/' ],
				'innerBlocks' => [],
			],
			[
				'name'        => 'artisanpack/next-post',
				'attributes'  => [ '_resolvedHasAdjacent' => true ],
				'innerBlocks' => [],
			],
		] );

		expect( $stripped[0]['attributes'] )->toBe( [ 'socialIcons' => [ 'facebook' ] ] )
			->and( $stripped[1]['attributes'] )->toBe( [ 'socialIcons' => [ 'facebook' ] ] )
			->and( $stripped[2]['attributes'] )->toBe( [ 'level' => 2 ] )
			->and( $stripped[3]['attributes'] )->toBe( [] );
	} );

	it( 'strips nested blocks', function (): void {
		$stripped = ( new PostResolver() )->stripSavedAttributes( [
			[
				'name'        => 'core/group',
				'attributes'  => [],
				'innerBlocks' => [ spoofedShareBlock() ],
			],
		] );

		expect( $stripped[0]['innerBlocks'][0]['attributes'] )->not->toHaveKey( '_resolvedShareLinks' );
	} );

	it( 'leaves blocks the resolver does not stamp untouched', function (): void {
		$tree = [
			[
				'name'        => 'artisanpack/query-pagination',
				'attributes'  => [ '_resolvedTotalPages' => 3 ],
				'innerBlocks' => [],
			],
			[
				'name'        => 'core/cover',
				'attributes'  => [ '_resolvedImageUrl' => 'https://example.test/x.png' ],
				'innerBlocks' => [],
			],
		];

		expect( ( new PostResolver() )->stripSavedAttributes( $tree ) )->toBe( $tree );
	} );

	it( 'strips a cover set to use the featured image', function (): void {
		$stripped = ( new PostResolver() )->stripSavedAttributes( [
			[
				'name'        => 'core/cover',
				'attributes'  => [ 'useFeaturedImage' => true, '_resolvedImageUrl' => 'https://evil.test/x.png' ],
				'innerBlocks' => [],
			],
		] );

		expect( $stripped[0]['attributes'] )->toBe( [ 'useFeaturedImage' => true ] );
	} );

	it( 'skips non-array entries and tolerates blocks without attributes', function (): void {
		$stripped = ( new PostResolver() )->stripSavedAttributes( [
			'not-a-block',
			[ 'name' => 'artisanpack/social-share-content' ],
		] );

		expect( $stripped )->toBe( [ [ 'name' => 'artisanpack/social-share-content' ] ] );
	} );
} );

describe( 'PostResolver stamping', function (): void {
	it( 'stamps the computed share URL once a saved tree is stripped', function (): void {
		$resolver = new PostResolver();
		$stamped  = $resolver->stampTree( $resolver->stripSavedAttributes( [ spoofedShareBlock() ] ), savedResolvedPost() );

		expect( shareUrlFor( $stamped[0], 'facebook' ) )
			->toStartWith( 'https://www.facebook.com/' )
			->not->toContain( 'evil.test' );
	} );

	it( 'keeps a value a host stamps after stripping', function (): void {
		$resolver = new PostResolver();
		$tree     = $resolver->stripSavedAttributes( [
			[
				'name'        => 'core/post-content',
				'attributes'  => [ '_resolvedContent' => '<p>saved</p>' ],
				'innerBlocks' => [],
			],
		] );

		$tree[0]['attributes']['_resolvedContent'] = '';

		$stamped = $resolver->stampTree( $tree, savedResolvedPost() );

		expect( $stamped[0]['attributes']['_resolvedContent'] )->toBe( '' );
	} );
} );

describe( 'QueryInliner', function (): void {
	beforeEach( function (): void {
		$this->fake = new FakeQueryResolver();
		$this->app->instance( QueryResolverContract::class, $this->fake );

		$this->inliner = new QueryInliner( $this->app, new PostResolver() );
	} );

	it( 'strips the saved tree before stamping when a host post is given', function (): void {
		$inlined = $this->inliner->inline( [ spoofedShareBlock() ], savedResolvedPost() );

		expect( $inlined[0]['attributes'] )->not->toHaveKey( '_resolvedShareLinks' );
	} );

	it( 'keeps the tree as given without a host post', function (): void {
		$inlined = $this->inliner->inline( [ spoofedShareBlock() ] );

		expect( shareUrlFor( $inlined[0], 'facebook' ) )->toBe( 'https://evil.test/' );
	} );

	it( 'keeps the tree as given when asked not to strip it', function (): void {
		$inlined = $this->inliner->inline( [ spoofedShareBlock() ], savedResolvedPost(), false );

		expect( shareUrlFor( $inlined[0], 'facebook' ) )->toBe( 'https://evil.test/' );
	} );

	it( 'stamps every iteration of a query template with its own post', function (): void {
		$this->fake->setItems( [ savedResolvedPost( 1, 'First' ), savedResolvedPost( 2, 'Second' ) ] );

		$query = [
			'name'        => 'core/query',
			'attributes'  => [ 'query' => [ 'postType' => 'post', 'perPage' => 2 ] ],
			'innerBlocks' => [
				[
					'name'        => 'core/post-template',
					'attributes'  => [],
					'innerBlocks' => [
						[
							'name'        => 'core/post-title',
							'attributes'  => [ '_resolvedTitle' => 'Spoofed', '_resolvedPermalink' => 'https://evil.test/' ],
							'innerBlocks' => [],
						],
						spoofedShareBlock(),
					],
				],
			],
		];

		// No host post (an archive page), so only the template strip applies.
		$items = $this->inliner->inline( [ $query ] )[0]['innerBlocks'][0]['innerBlocks'];

		expect( $items )->toHaveCount( 2 )
			->and( $items[0]['innerBlocks'][0]['attributes']['_resolvedTitle'] )->toBe( 'First' )
			->and( $items[0]['innerBlocks'][0]['attributes']['_resolvedPermalink'] )->toBe( 'https://example.test/posts/1' )
			->and( $items[1]['innerBlocks'][0]['attributes']['_resolvedTitle'] )->toBe( 'Second' )
			->and( shareUrlFor( $items[1]['innerBlocks'][1], 'facebook' ) )->toContain( rawurlencode( 'https://example.test/posts/2' ) );
	} );

	it( 'stamps query controls over values saved on them when a host post is given', function (): void {
		$this->fake->setItems( [ savedResolvedPost( 1, 'First' ) ] );
		$this->fake->perPage       = 1;
		$this->fake->totalOverride = 3;

		$query = $this->inliner->inline( [
			[
				'name'        => 'core/query',
				'attributes'  => [ 'query' => [ 'postType' => 'post', 'perPage' => 1 ] ],
				'innerBlocks' => [
					[ 'name' => 'core/post-template', 'attributes' => [], 'innerBlocks' => [] ],
					[
						'name'        => 'core/query-pagination-next',
						'attributes'  => [ 'label' => 'Next', '_resolvedNextPageUrl' => 'https://evil.test/', '_resolvedTotalPages' => 99 ],
						'innerBlocks' => [],
					],
				],
			],
		], savedResolvedPost( 9, 'Host' ) )[0];

		$next = $query['innerBlocks'][1]['attributes'];

		expect( $next['label'] )->toBe( 'Next' )
			->and( $next['_resolvedNextPageUrl'] )->not->toContain( 'evil.test' )
			->and( $next['_resolvedNextPageUrl'] )->toContain( 'page=2' )
			->and( $next['_resolvedTotalPages'] )->toBe( 3 );
	} );

	it( 'keeps iteration stamps when the stamped tree is stamped again against the host post', function (): void {
		$this->fake->setItems( [ savedResolvedPost( 2, 'Looped' ) ] );

		$resolver = new PostResolver();
		$inliner  = new QueryInliner( $this->app, $resolver );
		$host     = savedResolvedPost( 1, 'Host' );

		$tree = $inliner->inline( [
			[
				'name'        => 'core/query',
				'attributes'  => [ 'query' => [ 'postType' => 'post', 'perPage' => 1 ] ],
				'innerBlocks' => [
					[
						'name'        => 'core/post-template',
						'attributes'  => [],
						'innerBlocks' => [
							[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
						],
					],
				],
			],
		], $host );

		$stamped = $resolver->stampTree( $tree, $host );

		expect( $stamped[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0]['attributes']['_resolvedTitle'] )->toBe( 'Looped' );
	} );
} );
