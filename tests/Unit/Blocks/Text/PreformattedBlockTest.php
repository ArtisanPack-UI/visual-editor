<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Text\Preformatted\PreformattedBlock;

test( 'preformatted block has correct type and category', function (): void {
	$block = new PreformattedBlock();

	expect( $block->getType() )->toBe( 'preformatted' );
	expect( $block->getCategory() )->toBe( 'text' );
} );

test( 'preformatted block style schema has font family field', function (): void {
	$block  = new PreformattedBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'fontFamily' );
	expect( $schema['fontFamily']['type'] )->toBe( 'select' );
	expect( $schema['fontFamily']['default'] )->toBe( 'monospace' );
	expect( $schema['fontFamily']['options'] )->toHaveKey( 'monospace' );
	expect( $schema['fontFamily']['options'] )->toHaveKey( 'Consolas' );
} );

test( 'preformatted block style schema has line numbers field', function (): void {
	$block  = new PreformattedBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'showLineNumbers' );
	expect( $schema['showLineNumbers']['type'] )->toBe( 'toggle' );
	expect( $schema['showLineNumbers']['default'] )->toBeFalse();
} );

test( 'preformatted block defaults to monospace font with no line numbers', function (): void {
	$block         = new PreformattedBlock();
	$styleDefaults = $block->getDefaultStyles();

	expect( $styleDefaults['fontFamily'] )->toBe( 'monospace' );
	expect( $styleDefaults['showLineNumbers'] )->toBeFalse();
} );

test( 'preformatted block renders pre element', function (): void {
	$block  = new PreformattedBlock();
	$output = $block->render(
		[ 'content' => "Hello\n  World\n\tTabbed" ],
		[ 'fontFamily' => 'monospace', 'showLineNumbers' => false ],
	);

	expect( $output )->toContain( '<pre' );
	expect( $output )->toContain( 've-block-preformatted' );
	expect( $output )->toContain( 'Hello' );
	expect( $output )->toContain( 'font-family: monospace' );
} );

test( 'preformatted block renders with line numbers class', function (): void {
	$block  = new PreformattedBlock();
	$output = $block->render(
		[ 'content' => 'test content' ],
		[ 'fontFamily' => 'monospace', 'showLineNumbers' => true ],
	);

	expect( $output )->toContain( 've-pre-line-numbers' );
} );

test( 'preformatted block renders with custom font family', function (): void {
	$block  = new PreformattedBlock();
	$output = $block->render(
		[ 'content' => 'test' ],
		[ 'fontFamily' => 'Consolas', 'showLineNumbers' => false ],
	);

	expect( $output )->toContain( 'font-family: Consolas' );
} );

test( 'preformatted block renders with text color', function (): void {
	$block  = new PreformattedBlock();
	$output = $block->render(
		[ 'content' => 'test' ],
		[ 'fontFamily' => 'monospace', 'textColor' => '#00ff00', 'showLineNumbers' => false ],
	);

	expect( $output )->toContain( 'color: #00ff00' );
} );

test( 'preformatted block renders with background color', function (): void {
	$block  = new PreformattedBlock();
	$output = $block->render(
		[ 'content' => 'test' ],
		[ 'fontFamily' => 'monospace', 'backgroundColor' => '#1e1e1e', 'showLineNumbers' => false ],
	);

	expect( $output )->toContain( 'background-color: #1e1e1e' );
} );

test( 'preformatted block has transforms to code and paragraph', function (): void {
	$block      = new PreformattedBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toHaveKey( 'code' );
	expect( $transforms )->toHaveKey( 'paragraph' );
	expect( $transforms['code'] )->toBe( [ 'content' => 'content' ] );
	expect( $transforms['paragraph'] )->toBe( [ 'content' => 'text' ] );
} );

test( 'preformatted block has keywords', function (): void {
	$block = new PreformattedBlock();

	expect( $block->getKeywords() )->toContain( 'preformatted' );
	expect( $block->getKeywords() )->toContain( 'pre' );
	expect( $block->getKeywords() )->toContain( 'whitespace' );
} );
