<?php

/**
 * `<x-ve-blocks :post>` renders the share and author links the post
 * resolves to, not `_resolved*` values saved in the block (#1078).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

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

it( 'keeps the tree values when no post is in scope', function (): void {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => spoofedSocialTree() ] );

	expect( $rendered )
		->toContain( 'href="https://evil.test/share"' )
		->toContain( 'href="https://evil.test/author"' );
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

it( 'keeps the tree values when post resolution is off', function (): void {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" :post="$post" :resolve-post="false" />', [
		'tree' => spoofedSocialTree(),
		'post' => spoofedSocialPost(),
	] );

	expect( $rendered )->toContain( 'href="https://evil.test/share"' );
} );
