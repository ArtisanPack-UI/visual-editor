<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Embed\SocialEmbed\SocialEmbedBlock;

test( 'social embed block has correct type and category', function (): void {
	$block = new SocialEmbedBlock();

	expect( $block->getType() )->toBe( 'social-embed' );
	expect( $block->getCategory() )->toBe( 'embed' );
} );

test( 'social embed block content schema has url and platform options', function (): void {
	$block  = new SocialEmbedBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'url' );
	expect( $schema )->toHaveKey( 'platform' );
	expect( $schema )->toHaveKey( 'hideConversation' );
	expect( $schema )->toHaveKey( 'hideMedia' );
	expect( $schema['url']['type'] )->toBe( 'url' );
} );

test( 'social embed block style schema has max width and alignment', function (): void {
	$block  = new SocialEmbedBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'maxWidth' );
	expect( $schema )->toHaveKey( 'align' );
	expect( $schema['align']['type'] )->toBe( 'select' );
} );

test( 'social embed block defaults to centered with 550px max width', function (): void {
	$block    = new SocialEmbedBlock();
	$defaults = $block->getDefaultStyles();

	expect( $defaults['maxWidth'] )->toBe( '550px' );
	expect( $defaults['align'] )->toBe( 'center' );
} );

test( 'social embed block renders oembed content in sandboxed iframe', function (): void {
	$block  = new SocialEmbedBlock();
	$output = $block->render(
		[
			'url'      => 'https://twitter.com/user/status/123',
			'platform' => 'twitter',
			'html'     => '<blockquote>tweet content</blockquote>',
			'_source'  => 'oembed',
			'title'    => '',
		],
		[ 'maxWidth' => '550px', 'align' => 'center' ],
	);

	expect( $output )->toContain( 've-block-social-embed' );
	expect( $output )->toContain( 'sandbox="allow-scripts allow-popups"' );
	expect( $output )->not->toContain( 'allow-same-origin' );
	expect( $output )->toContain( 'iframe' );
} );

test( 'social embed block renders opengraph fallback card', function (): void {
	$block  = new SocialEmbedBlock();
	$output = $block->render(
		[
			'url'          => 'https://twitter.com/user/status/123',
			'platform'     => 'twitter',
			'html'         => '',
			'_source'      => 'opengraph',
			'title'        => 'A tweet about Laravel',
			'description'  => 'Some tweet text',
			'thumbnailUrl' => '',
		],
		[ 'maxWidth' => '550px', 'align' => 'center' ],
	);

	expect( $output )->toContain( 've-social-fallback-card' );
	expect( $output )->toContain( 'A tweet about Laravel' );
	expect( $output )->toContain( 'Twitter/X' );
} );

test( 'social embed block has keywords', function (): void {
	$block = new SocialEmbedBlock();

	expect( $block->getKeywords() )->toContain( 'social' );
	expect( $block->getKeywords() )->toContain( 'twitter' );
} );

test( 'social embed block is public', function (): void {
	$block = new SocialEmbedBlock();

	expect( $block->isPublic() )->toBeTrue();
} );

test( 'social embed block has transform to generic embed', function (): void {
	$block      = new SocialEmbedBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toHaveKey( 'embed' );
} );

test( 'social embed block alignment options include left center right', function (): void {
	$block   = new SocialEmbedBlock();
	$schema  = $block->getStyleSchema();
	$options = $schema['align']['options'];

	expect( $options )->toHaveKey( 'left' );
	expect( $options )->toHaveKey( 'center' );
	expect( $options )->toHaveKey( 'right' );
} );

test( 'social embed block defaults hide conversation and media to false', function (): void {
	$block    = new SocialEmbedBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['hideConversation'] )->toBeFalse();
	expect( $defaults['hideMedia'] )->toBeFalse();
} );
