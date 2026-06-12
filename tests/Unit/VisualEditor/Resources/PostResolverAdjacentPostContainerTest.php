<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PostResolver;

/**
 * Adjacent-post container family (#499).
 *
 * `artisanpack/next-post` and `artisanpack/previous-post` are wrapper
 * blocks: PostResolver resolves the neighbor in the same post type and
 * re-stamps the inner-block tree against it so every `post-*` child
 * renders the adjacent post's data. The wrapper itself only carries a
 * `_resolvedHasAdjacent` boolean so renderers can collapse to empty
 * markup when no neighbor exists.
 */

function adjacencyHostPost(): object
{
	$post            = new stdClass();
	$post->title     = 'Current post';
	$post->permalink = 'https://example.test/posts/current';

	$post->next_post = (object) [
		'title'     => 'Next neighbor title',
		'permalink' => 'https://example.test/posts/next',
	];

	$post->previous_post = (object) [
		'title'     => 'Previous neighbor title',
		'permalink' => 'https://example.test/posts/previous',
	];

	return $post;
}

it( 'stamps _resolvedHasAdjacent=true on next-post when the host has a next neighbor', function (): void {
	$resolved = ( new PostResolver() )->stampBlock(
		[
			'name'        => 'artisanpack/next-post',
			'attributes'  => [],
			'innerBlocks' => [],
		],
		adjacencyHostPost()
	);

	expect( $resolved['attributes']['_resolvedHasAdjacent'] )->toBeTrue();
} );

it( 'stamps _resolvedHasAdjacent=true on previous-post when the host has a previous neighbor', function (): void {
	$resolved = ( new PostResolver() )->stampBlock(
		[
			'name'        => 'artisanpack/previous-post',
			'attributes'  => [],
			'innerBlocks' => [],
		],
		adjacencyHostPost()
	);

	expect( $resolved['attributes']['_resolvedHasAdjacent'] )->toBeTrue();
} );

it( 'stamps _resolvedHasAdjacent=false when no neighbor is available in the chosen direction', function ( string $name ): void {
	$post = new stdClass();
	$post->title = 'Solo';

	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => $name, 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	expect( $resolved['attributes']['_resolvedHasAdjacent'] )->toBeFalse();
} )->with( [
	'next'     => [ 'artisanpack/next-post' ],
	'previous' => [ 'artisanpack/previous-post' ],
] );

it( 'rewrites inner post-title against the adjacent post when a neighbor exists', function (): void {
	$tree = [
		[
			'name'        => 'artisanpack/next-post',
			'attributes'  => [],
			'innerBlocks' => [
				[
					'name'        => 'artisanpack/post-title',
					'attributes'  => [],
					'innerBlocks' => [],
				],
			],
		],
	];

	$resolved = ( new PostResolver() )->stampTree( $tree, adjacencyHostPost() );

	$titleAttrs = $resolved[0]['innerBlocks'][0]['attributes'];

	expect( $titleAttrs['_resolvedTitle'] )->toBe( 'Next neighbor title' )
		->and( $titleAttrs['_resolvedPermalink'] )->toBe( 'https://example.test/posts/next' );
} );

it( 'rewrites inner post-title against the previous post for previous-post wrappers', function (): void {
	$tree = [
		[
			'name'        => 'artisanpack/previous-post',
			'attributes'  => [],
			'innerBlocks' => [
				[
					'name'        => 'artisanpack/post-title',
					'attributes'  => [],
					'innerBlocks' => [],
				],
			],
		],
	];

	$resolved = ( new PostResolver() )->stampTree( $tree, adjacencyHostPost() );

	$titleAttrs = $resolved[0]['innerBlocks'][0]['attributes'];

	expect( $titleAttrs['_resolvedTitle'] )->toBe( 'Previous neighbor title' )
		->and( $titleAttrs['_resolvedPermalink'] )->toBe( 'https://example.test/posts/previous' );
} );

it( 'leaves inner blocks untouched when no neighbor exists so renderers emit empty markup', function (): void {
	$post = new stdClass();
	$post->title = 'Solo';

	$tree = [
		[
			'name'        => 'artisanpack/next-post',
			'attributes'  => [],
			'innerBlocks' => [
				[
					'name'        => 'artisanpack/post-title',
					'attributes'  => [],
					'innerBlocks' => [],
				],
			],
		],
	];

	$resolved = ( new PostResolver() )->stampTree( $tree, $post );

	// No stamping happens against the host post itself — the renderer
	// reads `_resolvedHasAdjacent` and skips emitting markup, so the
	// inner attributes never reach a partial.
	$titleAttrs = $resolved[0]['innerBlocks'][0]['attributes'];

	expect( $titleAttrs )->not->toHaveKey( '_resolvedTitle' );
} );

it( 'lets a pre-existing _resolvedHasAdjacent flag win over the resolver default', function (): void {
	$post = new stdClass();
	$post->title = 'Solo';

	$resolved = ( new PostResolver() )->stampBlock(
		[
			'name'        => 'artisanpack/next-post',
			'attributes'  => [ '_resolvedHasAdjacent' => true ],
			'innerBlocks' => [],
		],
		$post
	);

	// Host-supplied resolved values must always win on merge so hosts
	// can pre-resolve the envelope upstream (custom Inertia payload,
	// etc.) without the resolver clobbering it.
	expect( $resolved['attributes']['_resolvedHasAdjacent'] )->toBeTrue();
} );
