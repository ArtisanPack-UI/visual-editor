<?php

/**
 * Coverage for {@see PostResolver}'s handling of the single-post
 * content cluster's social-icon blocks (#501): `author-social-icons`
 * (reads the post author's stored profile URLs) and
 * `social-share-content` (builds per-platform share URLs from the
 * host post's permalink, title, and featured image).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PostResolver;

function fakeSocialPost( array $authorAttrs = [], string $permalink = 'https://example.test/posts/hello' ): object
{
	$post                    = new stdClass();
	$post->id                = 1;
	$post->title             = 'Hello world';
	$post->permalink         = $permalink;
	$post->featured_image_id = null;
	$post->author            = (object) array_merge( [
		'name' => 'Jane Doe',
	], $authorAttrs );

	return $post;
}

it( 'stamps the post author social links for artisanpack/author-social-icons', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[
			'name'        => 'artisanpack/author-social-icons',
			'attributes'  => [ 'socialIcons' => [ 'facebook', 'email' ] ],
			'innerBlocks' => [],
		],
		fakeSocialPost( [
			'facebook' => 'https://example.test/jane-fb',
			'email'    => 'jane@example.test',
		] )
	);

	$links = $resolved['attributes']['_resolvedAuthorSocialLinks'];

	expect( $links )->toBeArray()
		->and( $links )->toHaveCount( 2 )
		->and( $links[0]['slug'] )->toBe( 'facebook' )
		->and( $links[0]['url'] )->toBe( 'https://example.test/jane-fb' )
		->and( $links[1]['slug'] )->toBe( 'email' )
		->and( $links[1]['url'] )->toBe( 'mailto:jane@example.test' );
} );

it( 'omits social links the author has not filled in', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[
			'name'        => 'artisanpack/author-social-icons',
			'attributes'  => [],
			'innerBlocks' => [],
		],
		fakeSocialPost( [
			'facebook' => 'https://example.test/jane-fb',
		] )
	);

	$links = $resolved['attributes']['_resolvedAuthorSocialLinks'];

	expect( $links )->toBeArray()
		->and( $links )->toHaveCount( 1 )
		->and( $links[0]['slug'] )->toBe( 'facebook' );
} );

it( 'stamps a generic `social` map on the author when individual properties are absent', function () {
	$author       = new stdClass();
	$author->name = 'Jane Doe';
	$author->social = [
		'twitter' => 'https://example.test/jane-tw',
	];

	$post                    = new stdClass();
	$post->id                = 1;
	$post->title             = 'Hello';
	$post->permalink         = 'https://example.test/posts/hello';
	$post->featured_image_id = null;
	$post->author            = $author;

	$resolved = ( new PostResolver() )->stampBlock(
		[
			'name'        => 'artisanpack/author-social-icons',
			'attributes'  => [],
			'innerBlocks' => [],
		],
		$post
	);

	$links = $resolved['attributes']['_resolvedAuthorSocialLinks'];

	expect( $links )->toBeArray()
		->and( $links )->toHaveCount( 1 )
		->and( $links[0]['slug'] )->toBe( 'twitter' )
		->and( $links[0]['url'] )->toBe( 'https://example.test/jane-tw' );
} );

it( 'returns an empty social-link list when the post has no author', function () {
	$post                    = new stdClass();
	$post->id                = 1;
	$post->title             = 'Hello';
	$post->permalink         = 'https://example.test/posts/hello';
	$post->featured_image_id = null;
	$post->author            = null;

	$resolved = ( new PostResolver() )->stampBlock(
		[
			'name'        => 'artisanpack/author-social-icons',
			'attributes'  => [],
			'innerBlocks' => [],
		],
		$post
	);

	expect( $resolved['attributes']['_resolvedAuthorSocialLinks'] )->toBe( [] );
} );

it( 'stamps share URLs for artisanpack/social-share-content from the post permalink + title', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[
			'name'        => 'artisanpack/social-share-content',
			'attributes'  => [],
			'innerBlocks' => [],
		],
		fakeSocialPost()
	);

	$links = $resolved['attributes']['_resolvedShareLinks'];

	expect( $links )->toBeArray()
		->and( count( $links ) )->toBeGreaterThan( 0 );

	$bySlug = [];
	foreach ( $links as $link ) {
		$bySlug[ $link['slug'] ] = $link['url'];
	}

	expect( $bySlug['facebook'] )->toContain( 'https://www.facebook.com/sharer.php?u=' )
		->and( $bySlug['twitter'] )->toContain( 'https://twitter.com/share?url=' )
		->and( $bySlug['twitter'] )->toContain( '&text=' )
		->and( $bySlug['reddit'] )->toContain( 'https://www.reddit.com/submit?url=' )
		->and( $bySlug['email'] )->toStartWith( 'mailto:' );
} );

it( 'returns an empty share-link list when the post has no permalink', function () {
	$post                    = new stdClass();
	$post->id                = 1;
	$post->title             = 'Hello';
	$post->permalink         = '';
	$post->featured_image_id = null;
	$post->author            = null;

	$resolved = ( new PostResolver() )->stampBlock(
		[
			'name'        => 'artisanpack/social-share-content',
			'attributes'  => [],
			'innerBlocks' => [],
		],
		$post
	);

	expect( $resolved['attributes']['_resolvedShareLinks'] )->toBe( [] );
} );
