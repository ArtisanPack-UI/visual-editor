<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Embed\Embed\EmbedBlock;

test( 'embed block has correct type and category', function (): void {
	$block = new EmbedBlock();

	expect( $block->getType() )->toBe( 'embed' );
	expect( $block->getCategory() )->toBe( 'embed' );
} );

test( 'embed block content schema has url and caption fields', function (): void {
	$block  = new EmbedBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'url' );
	expect( $schema )->toHaveKey( 'caption' );
	expect( $schema['url']['type'] )->toBe( 'url' );
} );

test( 'embed block style schema has aspect ratio and responsive fields', function (): void {
	$block  = new EmbedBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'aspectRatio' );
	expect( $schema )->toHaveKey( 'responsive' );
	expect( $schema['aspectRatio']['type'] )->toBe( 'select' );
	expect( $schema['responsive']['type'] )->toBe( 'toggle' );
} );

test( 'embed block defaults to 16:9 aspect ratio and responsive', function (): void {
	$block    = new EmbedBlock();
	$defaults = $block->getDefaultStyles();

	expect( $defaults['aspectRatio'] )->toBe( '16:9' );
	expect( $defaults['responsive'] )->toBeTrue();
} );

test( 'embed block renders oembed content in sandboxed iframe', function (): void {
	$block  = new EmbedBlock();
	$output = $block->render(
		[
			'url'     => 'https://www.youtube.com/watch?v=test',
			'html'    => '<div>embed html</div>',
			'_source' => 'oembed',
			'title'   => 'Test Video',
			'caption' => '',
		],
		[ 'aspectRatio' => '16:9', 'responsive' => true ],
	);

	expect( $output )->toContain( 'sandbox="allow-scripts allow-popups"' );
	expect( $output )->not->toContain( 'allow-same-origin' );
	expect( $output )->toContain( 've-block-embed' );
	expect( $output )->toContain( 'iframe' );
} );

test( 'embed block renders opengraph fallback card', function (): void {
	$block  = new EmbedBlock();
	$output = $block->render(
		[
			'url'          => 'https://example.com/article',
			'html'         => '',
			'_source'      => 'opengraph',
			'title'        => 'Example Article',
			'description'  => 'An example article.',
			'thumbnailUrl' => 'https://example.com/thumb.jpg',
			'caption'      => '',
		],
		[ 'aspectRatio' => '16:9', 'responsive' => true ],
	);

	expect( $output )->toContain( 've-embed-fallback-card' );
	expect( $output )->toContain( 'Example Article' );
	expect( $output )->toContain( 'An example article.' );
} );

test( 'embed block renders caption when provided', function (): void {
	$block  = new EmbedBlock();
	$output = $block->render(
		[
			'url'     => 'https://www.youtube.com/watch?v=test',
			'html'    => '<div>embed</div>',
			'_source' => 'oembed',
			'title'   => 'Test',
			'caption' => 'My video caption',
		],
		[ 'aspectRatio' => '16:9', 'responsive' => true ],
	);

	expect( $output )->toContain( 'My video caption' );
	expect( $output )->toContain( 've-embed-caption' );
} );

test( 'embed block has keywords', function (): void {
	$block = new EmbedBlock();

	expect( $block->getKeywords() )->toContain( 'embed' );
	expect( $block->getKeywords() )->toContain( 'youtube' );
} );

test( 'embed block is public', function (): void {
	$block = new EmbedBlock();

	expect( $block->isPublic() )->toBeTrue();
} );

test( 'embed block has transform to custom html', function (): void {
	$block      = new EmbedBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toHaveKey( 'custom-html' );
} );

test( 'embed block supports alignment', function (): void {
	$block = new EmbedBlock();

	expect( $block->supportsFeature( 'align' ) )->toBeTrue();
} );

test( 'embed block aspect ratio options include standard ratios', function (): void {
	$block   = new EmbedBlock();
	$schema  = $block->getStyleSchema();
	$options = $schema['aspectRatio']['options'];

	expect( $options )->toHaveKey( '16:9' );
	expect( $options )->toHaveKey( '4:3' );
	expect( $options )->toHaveKey( '1:1' );
	expect( $options )->not->toHaveKey( 'custom' );
} );
