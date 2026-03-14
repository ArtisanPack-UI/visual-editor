<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Embed\CustomHtml\CustomHtmlBlock;

test( 'custom html block has correct type and category', function (): void {
	$block = new CustomHtmlBlock();

	expect( $block->getType() )->toBe( 'custom-html' );
	expect( $block->getCategory() )->toBe( 'embed' );
} );

test( 'custom html block content schema has preview sanitize and css class fields', function (): void {
	$block  = new CustomHtmlBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'preview' );
	expect( $schema )->toHaveKey( 'sanitize' );
	expect( $schema )->toHaveKey( 'cssClass' );
	expect( $schema['preview']['type'] )->toBe( 'toggle' );
	expect( $schema['sanitize']['type'] )->toBe( 'toggle' );
} );

test( 'custom html block defaults to sanitization enabled and preview disabled', function (): void {
	$block    = new CustomHtmlBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['sanitize'] )->toBeTrue();
	expect( $defaults['preview'] )->toBeFalse();
} );

test( 'custom html block renders content with sanitization enabled', function (): void {
	$block  = new CustomHtmlBlock();
	$output = $block->render(
		[
			'content'  => '<p>Hello World</p>',
			'sanitize' => true,
			'preview'  => false,
			'cssClass' => '',
		],
		[],
	);

	expect( $output )->toContain( 've-block-custom-html' );
	// With sanitization on, content is rendered inline (not in iframe).
	expect( $output )->toContain( 'Hello World' );
} );

test( 'custom html block renders unsanitized content in sandboxed iframe', function (): void {
	$block  = new CustomHtmlBlock();
	$output = $block->render(
		[
			'content'  => '<script>alert("hi")</script><p>Test</p>',
			'sanitize' => false,
			'preview'  => false,
			'cssClass' => '',
		],
		[],
	);

	expect( $output )->toContain( 've-block-custom-html' );
	expect( $output )->toContain( 'iframe' );
	expect( $output )->toContain( 'sandbox="allow-scripts"' );
} );

test( 'custom html block applies css class wrapper', function (): void {
	$block  = new CustomHtmlBlock();
	$output = $block->render(
		[
			'content'  => '<p>Styled</p>',
			'sanitize' => true,
			'preview'  => false,
			'cssClass' => 'my-custom-class',
		],
		[],
	);

	expect( $output )->toContain( 'my-custom-class' );
} );

test( 'custom html block has keywords', function (): void {
	$block = new CustomHtmlBlock();

	expect( $block->getKeywords() )->toContain( 'html' );
	expect( $block->getKeywords() )->toContain( 'code' );
} );

test( 'custom html block is public', function (): void {
	$block = new CustomHtmlBlock();

	expect( $block->isPublic() )->toBeTrue();
} );

test( 'custom html block has no transforms', function (): void {
	$block      = new CustomHtmlBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toBeEmpty();
} );

test( 'custom html block editor view shows warning when sanitization disabled', function (): void {
	$block  = new CustomHtmlBlock();
	$output = $block->renderEditor(
		[
			'content'  => '<p>Test</p>',
			'sanitize' => false,
			'preview'  => false,
			'cssClass' => '',
		],
		[],
	);

	expect( $output )->toContain( 've-custom-html-warning' );
} );

test( 'custom html block editor view shows textarea in edit mode', function (): void {
	$block  = new CustomHtmlBlock();
	$output = $block->renderEditor(
		[
			'content'  => '<p>Code here</p>',
			'sanitize' => true,
			'preview'  => false,
			'cssClass' => '',
		],
		[],
	);

	expect( $output )->toContain( 've-custom-html-textarea' );
	expect( $output )->toContain( 'Code here' );
} );

test( 'custom html block editor view shows iframe in preview mode', function (): void {
	$block  = new CustomHtmlBlock();
	$output = $block->renderEditor(
		[
			'content'  => '<p>Preview this</p>',
			'sanitize' => true,
			'preview'  => true,
			'cssClass' => '',
		],
		[],
	);

	expect( $output )->toContain( 've-custom-html-preview' );
	expect( $output )->toContain( 'iframe' );
} );
