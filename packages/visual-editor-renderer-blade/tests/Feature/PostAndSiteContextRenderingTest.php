<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

function renderTree( array $tree ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );
}

function blockNode( string $name, array $attributes = [], array $innerBlocks = [], string $clientId = 'cid' ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => $name,
		'attributes'  => $attributes,
		'innerBlocks' => $innerBlocks,
	];
}

it( 'renders a post-title block at the configured level', function () {
	$tree = [
		blockNode( 'core/post-title', [
			'level'           => 3,
			'_resolvedTitle'  => 'Hello World',
		] ),
	];

	$rendered = $this->stripGlobalStyles( renderTree( $tree ) );

	expect( $this->normalizeHtml( $rendered ) )->toContain( '<h3 class="wp-block-post-title">Hello World</h3>' );
} );

it( 'wraps the post-title in a permalink when isLink is true', function () {
	$tree = [
		blockNode( 'core/post-title', [
			'isLink'              => true,
			'_resolvedTitle'      => 'Linked Title',
			'_resolvedPermalink'  => 'https://example.test/post',
		] ),
	];

	$rendered = $this->stripGlobalStyles( renderTree( $tree ) );

	expect( $rendered )->toContain( '<a href="https://example.test/post">Linked Title</a>' );
} );

it( 'renders post-content with the resolved HTML body', function () {
	$tree = [
		blockNode( 'core/post-content', [
			'_resolvedContent' => '<p>Body</p>',
		] ),
	];

	$rendered = $this->stripGlobalStyles( renderTree( $tree ) );

	expect( $this->normalizeHtml( $rendered ) )->toContain( '<div class="entry-content wp-block-post-content"><p>Body</p></div>' );
} );

it( 'renders post-excerpt with a more-text link', function () {
	$tree = [
		blockNode( 'core/post-excerpt', [
			'moreText'           => 'Read more',
			'_resolvedExcerpt'   => 'A short excerpt',
			'_resolvedPermalink' => 'https://example.test/post',
		] ),
	];

	$rendered = $this->stripGlobalStyles( renderTree( $tree ) );

	expect( $rendered )->toContain( 'wp-block-post-excerpt' );
	expect( $rendered )->toContain( '<a class="wp-block-post-excerpt__more-link" href="https://example.test/post">Read more</a>' );
} );

it( 'renders a post-date with a datetime attribute and formatted text', function () {
	$tree = [
		blockNode( 'core/post-date', [
			'_resolvedDate'           => '2026-04-20T12:00:00+00:00',
			'_resolvedDateFormatted'  => 'April 20, 2026',
		] ),
	];

	$rendered = $this->stripGlobalStyles( renderTree( $tree ) );

	expect( $rendered )->toContain( 'datetime="2026-04-20T12:00:00+00:00"' );
	expect( $rendered )->toContain( 'April 20, 2026' );
} );

it( 'renders post-author with name, avatar, and bio when shown', function () {
	$tree = [
		blockNode( 'core/post-author', [
			'showAvatar'             => true,
			'showBio'                => true,
			'avatarSize'             => 48,
			'byline'                 => 'Posted by',
			'_resolvedAuthorName'    => 'Jane Doe',
			'_resolvedAuthorBio'     => 'Writer.',
			'_resolvedAuthorAvatar'  => 'https://example.test/avatar.jpg',
		] ),
	];

	$rendered = $this->stripGlobalStyles( renderTree( $tree ) );

	expect( $rendered )->toContain( 'wp-block-post-author' );
	expect( $rendered )->toContain( 'Jane Doe' );
	expect( $rendered )->toContain( 'Writer.' );
	expect( $rendered )->toContain( 'src="https://example.test/avatar.jpg"' );
	expect( $rendered )->toContain( 'Posted by' );
} );

it( 'renders post-featured-image with link wrapper when isLink is true', function () {
	$tree = [
		blockNode( 'core/post-featured-image', [
			'isLink'                  => true,
			'_resolvedImageUrl'       => 'https://example.test/featured.jpg',
			'_resolvedImageAlt'       => 'Featured photo',
			'_resolvedImageWidth'     => 800,
			'_resolvedImageHeight'    => 600,
			'_resolvedPermalink'      => 'https://example.test/post',
		] ),
	];

	$rendered = $this->stripGlobalStyles( renderTree( $tree ) );

	expect( $rendered )->toContain( '<figure class="wp-block-post-featured-image">' );
	expect( $rendered )->toContain( '<a href="https://example.test/post">' );
	expect( $rendered )->toContain( 'src="https://example.test/featured.jpg"' );
	expect( $rendered )->toContain( 'alt="Featured photo"' );
	expect( $rendered )->toContain( 'width="800"' );
	expect( $rendered )->toContain( 'height="600"' );
} );

it( 'drops javascript: URLs from post-featured-image hrefs', function () {
	$tree = [
		blockNode( 'core/post-featured-image', [
			'isLink'              => true,
			'_resolvedImageUrl'   => 'https://example.test/safe.jpg',
			'_resolvedPermalink'  => 'javascript:alert(1)',
		] ),
	];

	$rendered = $this->stripGlobalStyles( renderTree( $tree ) );

	expect( $rendered )->not()->toContain( 'javascript:' );
	expect( $rendered )->not()->toContain( '<a ' );
} );

it( 'renders site-title with default link to site URL', function () {
	$tree = [
		blockNode( 'core/site-title', [
			'level'                => 1,
			'_resolvedSiteTitle'   => 'Acme',
			'_resolvedSiteUrl'     => 'https://example.test',
		] ),
	];

	$rendered = $this->stripGlobalStyles( renderTree( $tree ) );

	expect( $rendered )->toContain( '<h1 class="wp-block-site-title">' );
	expect( $rendered )->toContain( '<a href="https://example.test"' );
	expect( $rendered )->toContain( 'rel="home"' );
	expect( $rendered )->toContain( 'Acme' );
} );

it( 'renders site-title as a paragraph when level is 0', function () {
	$tree = [
		blockNode( 'core/site-title', [
			'level'              => 0,
			'isLink'             => false,
			'_resolvedSiteTitle' => 'Acme',
		] ),
	];

	$rendered = $this->stripGlobalStyles( renderTree( $tree ) );

	expect( $this->normalizeHtml( $rendered ) )->toContain( '<p class="wp-block-site-title">Acme</p>' );
} );

it( 'renders site-tagline', function () {
	$tree = [
		blockNode( 'core/site-tagline', [
			'_resolvedSiteTagline' => 'A small site',
		] ),
	];

	$rendered = $this->stripGlobalStyles( renderTree( $tree ) );

	expect( $this->normalizeHtml( $rendered ) )->toContain( '<p class="wp-block-site-tagline">A small site</p>' );
} );

it( 'renders site-logo with link wrapper and image', function () {
	$tree = [
		blockNode( 'core/site-logo', [
			'width'              => 120,
			'_resolvedLogoUrl'   => 'https://example.test/logo.svg',
			'_resolvedSiteUrl'   => 'https://example.test',
			'_resolvedSiteTitle' => 'Acme',
		] ),
	];

	$rendered = $this->stripGlobalStyles( renderTree( $tree ) );

	expect( $rendered )->toContain( 'wp-block-site-logo' );
	expect( $rendered )->not()->toContain( 'is-default-size' );
	expect( $rendered )->toContain( '<a href="https://example.test"' );
	expect( $rendered )->toContain( 'class="custom-logo-link"' );
	expect( $rendered )->toContain( 'src="https://example.test/logo.svg"' );
	expect( $rendered )->toContain( 'width="120"' );
} );

it( 'adds is-default-size to site-logo when no width is set', function () {
	$tree = [
		blockNode( 'core/site-logo', [
			'_resolvedLogoUrl'   => 'https://example.test/logo.svg',
			'_resolvedSiteUrl'   => 'https://example.test',
			'_resolvedSiteTitle' => 'Acme',
		] ),
	];

	$rendered = $this->stripGlobalStyles( renderTree( $tree ) );

	expect( $rendered )->toContain( 'is-default-size' );
} );

it( 'renders a navigation block with menu items', function () {
	$tree = [
		blockNode( 'core/navigation', [ 'ariaLabel' => 'Primary' ], [
			blockNode( 'core/navigation-link', [
				'label' => 'About',
				'url'   => 'https://example.test/about',
			], [], 'nl-1' ),
			blockNode( 'core/navigation-submenu', [
				'label' => 'More',
				'url'   => 'https://example.test/more',
			], [
				blockNode( 'core/navigation-link', [
					'label' => 'Sub',
					'url'   => 'https://example.test/sub',
				], [], 'nl-sub' ),
			], 'sub-1' ),
		] ),
	];

	$rendered = $this->stripGlobalStyles( renderTree( $tree ) );

	expect( $rendered )->toContain( '<nav class="wp-block-navigation is-horizontal is-responsive"' );
	expect( $rendered )->toContain( 'aria-label="Primary"' );
	expect( $rendered )->toContain( '<ul class="wp-block-navigation__container">' );
	expect( $rendered )->toContain( 'href="https://example.test/about"' );
	expect( $rendered )->toContain( 'wp-block-navigation-submenu has-child' );
	expect( $rendered )->toContain( 'href="https://example.test/sub"' );
} );

it( 'forces noopener when a navigation link opens in a new tab', function () {
	$tree = [
		blockNode( 'core/navigation', [], [
			blockNode( 'core/navigation-link', [
				'label'         => 'External',
				'url'           => 'https://example.com',
				'opensInNewTab' => true,
			], [], 'nl-1' ),
		] ),
	];

	$rendered = $this->stripGlobalStyles( renderTree( $tree ) );

	expect( $rendered )->toContain( 'target="_blank"' );
	expect( $rendered )->toContain( 'rel="noopener noreferrer"' );
} );
