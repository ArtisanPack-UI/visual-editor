<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Interactive\CodeBlock;

test( 'code block has correct type and category', function (): void {
	$block = new CodeBlock();

	expect( $block->getType() )->toBe( 'code' );
	expect( $block->getCategory() )->toBe( 'interactive' );
} );

test( 'code block content schema has language and filename fields', function (): void {
	$block  = new CodeBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'language' );
	expect( $schema )->toHaveKey( 'filename' );
	expect( $schema['language']['type'] )->toBe( 'select' );
} );

test( 'code block style schema has line numbers and copy button fields', function (): void {
	$block  = new CodeBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'showLineNumbers' );
	expect( $schema )->toHaveKey( 'highlightLines' );
	expect( $schema )->toHaveKey( 'showCopyButton' );
} );

test( 'code block defaults to plain language with line numbers', function (): void {
	$block           = new CodeBlock();
	$contentDefaults = $block->getDefaultContent();
	$styleDefaults   = $block->getDefaultStyles();

	expect( $contentDefaults['language'] )->toBe( 'plain' );
	expect( $styleDefaults['showLineNumbers'] )->toBeTrue();
	expect( $styleDefaults['showCopyButton'] )->toBeTrue();
} );

test( 'code block renders pre and code elements', function (): void {
	$block  = new CodeBlock();
	$output = $block->render(
		[ 'content' => 'echo "hello";', 'language' => 'php', 'filename' => '' ],
		[ 'showLineNumbers' => true, 'showCopyButton' => true, 'highlightLines' => '' ],
	);

	expect( $output )->toContain( '<pre' );
	expect( $output )->toContain( '<code' );
	expect( $output )->toContain( 'language-php' );
	expect( $output )->toContain( 've-block-code' );
} );

test( 'code block renders filename header when set', function (): void {
	$block  = new CodeBlock();
	$output = $block->render(
		[ 'content' => 'const x = 1;', 'language' => 'javascript', 'filename' => 'app.js' ],
		[ 'showLineNumbers' => true, 'showCopyButton' => true, 'highlightLines' => '' ],
	);

	expect( $output )->toContain( 've-code-filename' );
	expect( $output )->toContain( 'app.js' );
} );

test( 'code block has keywords', function (): void {
	$block = new CodeBlock();

	expect( $block->getKeywords() )->toContain( 'code' );
	expect( $block->getKeywords() )->toContain( 'snippet' );
} );
