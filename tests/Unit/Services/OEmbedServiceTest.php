<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\OEmbedService;

test( 'oembed service detects youtube platform', function (): void {
	$service = new OEmbedService();

	expect( $service->detectPlatform( 'https://www.youtube.com/watch?v=abc123' ) )->toBe( 'youtube' );
	expect( $service->detectPlatform( 'https://youtu.be/abc123' ) )->toBe( 'youtube' );
} );

test( 'oembed service detects twitter platform', function (): void {
	$service = new OEmbedService();

	expect( $service->detectPlatform( 'https://twitter.com/user/status/123' ) )->toBe( 'twitter' );
	expect( $service->detectPlatform( 'https://x.com/user/status/123' ) )->toBe( 'twitter' );
} );

test( 'oembed service detects instagram platform', function (): void {
	$service = new OEmbedService();

	expect( $service->detectPlatform( 'https://www.instagram.com/p/abc123' ) )->toBe( 'instagram' );
	expect( $service->detectPlatform( 'https://www.instagram.com/reel/abc123' ) )->toBe( 'instagram' );
} );

test( 'oembed service detects facebook platform', function (): void {
	$service = new OEmbedService();

	expect( $service->detectPlatform( 'https://www.facebook.com/user/posts/123' ) )->toBe( 'facebook' );
} );

test( 'oembed service detects tiktok platform', function (): void {
	$service = new OEmbedService();

	expect( $service->detectPlatform( 'https://www.tiktok.com/@user/video/123' ) )->toBe( 'tiktok' );
} );

test( 'oembed service detects reddit platform', function (): void {
	$service = new OEmbedService();

	expect( $service->detectPlatform( 'https://www.reddit.com/r/laravel/comments/abc/post' ) )->toBe( 'reddit' );
} );

test( 'oembed service detects bluesky platform', function (): void {
	$service = new OEmbedService();

	expect( $service->detectPlatform( 'https://bsky.app/profile/user.bsky.social/post/abc' ) )->toBe( 'bluesky' );
} );

test( 'oembed service detects vimeo platform', function (): void {
	$service = new OEmbedService();

	expect( $service->detectPlatform( 'https://vimeo.com/123456' ) )->toBe( 'vimeo' );
} );

test( 'oembed service detects spotify platform', function (): void {
	$service = new OEmbedService();

	expect( $service->detectPlatform( 'https://open.spotify.com/track/abc' ) )->toBe( 'spotify' );
} );

test( 'oembed service detects codepen platform', function (): void {
	$service = new OEmbedService();

	expect( $service->detectPlatform( 'https://codepen.io/user/pen/abc' ) )->toBe( 'codepen' );
} );

test( 'oembed service returns null for unknown platform', function (): void {
	$service = new OEmbedService();

	expect( $service->detectPlatform( 'https://example.com/page' ) )->toBeNull();
} );

test( 'oembed service has provider for youtube url', function (): void {
	$service = new OEmbedService();

	expect( $service->hasProvider( 'https://www.youtube.com/watch?v=abc123' ) )->toBeTrue();
	expect( $service->hasProvider( 'https://youtu.be/abc123' ) )->toBeTrue();
} );

test( 'oembed service has provider for twitter url', function (): void {
	$service = new OEmbedService();

	expect( $service->hasProvider( 'https://twitter.com/user/status/123' ) )->toBeTrue();
	expect( $service->hasProvider( 'https://x.com/user/status/123' ) )->toBeTrue();
} );

test( 'oembed service has no provider for unknown url', function (): void {
	$service = new OEmbedService();

	expect( $service->hasProvider( 'https://example.com/page' ) )->toBeFalse();
} );

test( 'oembed service returns social platforms list', function (): void {
	$service   = new OEmbedService();
	$platforms = $service->getSocialPlatforms();

	expect( $platforms )->toContain( 'twitter' );
	expect( $platforms )->toContain( 'instagram' );
	expect( $platforms )->toContain( 'facebook' );
	expect( $platforms )->toContain( 'tiktok' );
	expect( $platforms )->toContain( 'reddit' );
	expect( $platforms )->toContain( 'bluesky' );
} );

test( 'oembed service returns null for unresolvable oembed', function (): void {
	$service = new OEmbedService();

	expect( $service->resolveOEmbed( 'https://example.com/nonexistent' ) )->toBeNull();
} );

test( 'oembed service detects soundcloud platform', function (): void {
	$service = new OEmbedService();

	expect( $service->detectPlatform( 'https://soundcloud.com/artist/track' ) )->toBe( 'soundcloud' );
} );

test( 'oembed service strips www prefix for platform detection', function (): void {
	$service = new OEmbedService();

	expect( $service->detectPlatform( 'https://www.reddit.com/r/test/comments/abc/title' ) )->toBe( 'reddit' );
	expect( $service->detectPlatform( 'https://reddit.com/r/test/comments/abc/title' ) )->toBe( 'reddit' );
} );

test( 'oembed service has provider for vimeo url', function (): void {
	$service = new OEmbedService();

	expect( $service->hasProvider( 'https://vimeo.com/123456' ) )->toBeTrue();
} );

test( 'oembed service has provider for spotify url', function (): void {
	$service = new OEmbedService();

	expect( $service->hasProvider( 'https://open.spotify.com/track/abc' ) )->toBeTrue();
} );

test( 'oembed service has provider for bluesky url', function (): void {
	$service = new OEmbedService();

	expect( $service->hasProvider( 'https://bsky.app/profile/user.bsky.social/post/abc' ) )->toBeTrue();
} );
