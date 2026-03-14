<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Text\Details\DetailsBlock;

test( 'details block has correct type and category', function (): void {
	$block = new DetailsBlock();

	expect( $block->getType() )->toBe( 'details' );
	expect( $block->getCategory() )->toBe( 'text' );
} );

test( 'details block supports inner blocks', function (): void {
	$block = new DetailsBlock();

	expect( $block->supportsInnerBlocks() )->toBeTrue();
} );

test( 'details block has vertical inner blocks orientation', function (): void {
	$block = new DetailsBlock();

	expect( $block->getInnerBlocksOrientation() )->toBe( 'vertical' );
} );

test( 'details block has js renderer', function (): void {
	$block = new DetailsBlock();

	expect( $block->hasJsRenderer() )->toBeTrue();
} );

test( 'details block content schema has isOpenByDefault field', function (): void {
	$block  = new DetailsBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'isOpenByDefault' );
	expect( $schema['isOpenByDefault']['type'] )->toBe( 'toggle' );
	expect( $schema['isOpenByDefault']['default'] )->toBeFalse();
} );

test( 'details block style schema has icon and iconPosition fields', function (): void {
	$block  = new DetailsBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'icon' );
	expect( $schema['icon']['type'] )->toBe( 'select' );
	expect( $schema['icon']['default'] )->toBe( 'chevron' );
	expect( $schema['icon']['options'] )->toHaveKey( 'chevron' );
	expect( $schema['icon']['options'] )->toHaveKey( 'plus-minus' );
	expect( $schema['icon']['options'] )->toHaveKey( 'none' );

	expect( $schema )->toHaveKey( 'iconPosition' );
	expect( $schema['iconPosition']['type'] )->toBe( 'select' );
	expect( $schema['iconPosition']['default'] )->toBe( 'left' );
} );

test( 'details block style schema has borderStyle field', function (): void {
	$block  = new DetailsBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'borderStyle' );
	expect( $schema['borderStyle']['type'] )->toBe( 'select' );
	expect( $schema['borderStyle']['default'] )->toBe( 'default' );
	expect( $schema['borderStyle']['options'] )->toHaveKey( 'card' );
	expect( $schema['borderStyle']['options'] )->toHaveKey( 'minimal' );
	expect( $schema['borderStyle']['options'] )->toHaveKey( 'borderless' );
} );

test( 'details block style schema has summary and content background colors', function (): void {
	$block  = new DetailsBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'summaryBackgroundColor' );
	expect( $schema['summaryBackgroundColor']['type'] )->toBe( 'color' );

	expect( $schema )->toHaveKey( 'contentBackgroundColor' );
	expect( $schema['contentBackgroundColor']['type'] )->toBe( 'color' );
} );

test( 'details block defaults to closed with chevron icon', function (): void {
	$block           = new DetailsBlock();
	$contentDefaults = $block->getDefaultContent();
	$styleDefaults   = $block->getDefaultStyles();

	expect( $contentDefaults['isOpenByDefault'] )->toBeFalse();
	expect( $styleDefaults['icon'] )->toBe( 'chevron' );
	expect( $styleDefaults['iconPosition'] )->toBe( 'left' );
	expect( $styleDefaults['borderStyle'] )->toBe( 'default' );
} );

test( 'details block renders details and summary elements', function (): void {
	$block  = new DetailsBlock();
	$output = $block->render(
		[ 'summary' => 'Click to expand', 'isOpenByDefault' => false ],
		[ 'icon' => 'chevron', 'iconPosition' => 'left', 'borderStyle' => 'default' ],
	);

	expect( $output )->toContain( '<details' );
	expect( $output )->toContain( '<summary' );
	expect( $output )->toContain( 'Click to expand' );
	expect( $output )->toContain( 've-block-details' );
	expect( $output )->toContain( 've-details-content' );
} );

test( 'details block renders open attribute when open by default', function (): void {
	$block  = new DetailsBlock();
	$output = $block->render(
		[ 'summary' => 'Open section', 'isOpenByDefault' => true ],
		[ 'icon' => 'chevron', 'iconPosition' => 'left', 'borderStyle' => 'default' ],
	);

	expect( $output )->toContain( 'open' );
} );

test( 'details block does not render open attribute when closed by default', function (): void {
	$block  = new DetailsBlock();
	$output = $block->render(
		[ 'summary' => 'Closed section', 'isOpenByDefault' => false ],
		[ 'icon' => 'chevron', 'iconPosition' => 'left', 'borderStyle' => 'default' ],
	);

	expect( $output )->not->toMatch( '/\bopen\b/' );
} );

test( 'details block renders style classes for icon and border', function (): void {
	$block  = new DetailsBlock();
	$output = $block->render(
		[ 'summary' => 'Test', 'isOpenByDefault' => false ],
		[ 'icon' => 'plus-minus', 'iconPosition' => 'right', 'borderStyle' => 'card' ],
	);

	expect( $output )->toContain( 've-details-icon-plus-minus' );
	expect( $output )->toContain( 've-details-icon-right' );
	expect( $output )->toContain( 've-details-style-card' );
} );

test( 'details block renders inner blocks', function (): void {
	$block  = new DetailsBlock();
	$output = $block->render(
		[ 'summary' => 'FAQ Question', 'isOpenByDefault' => false ],
		[ 'icon' => 'chevron', 'iconPosition' => 'left', 'borderStyle' => 'default' ],
		[],
		[ '<p>Inner content here</p>' ],
	);

	expect( $output )->toContain( 'Inner content here' );
} );

test( 'details block renders with summary background color', function (): void {
	$block  = new DetailsBlock();
	$output = $block->render(
		[ 'summary' => 'Test', 'isOpenByDefault' => false ],
		[ 'icon' => 'chevron', 'iconPosition' => 'left', 'borderStyle' => 'default', 'summaryBackgroundColor' => '#f0f0f0' ],
	);

	expect( $output )->toContain( 'background-color: #f0f0f0' );
} );

test( 'details block has default inner blocks', function (): void {
	$block       = new DetailsBlock();
	$innerBlocks = $block->getDefaultInnerBlocks();

	expect( $innerBlocks )->toHaveCount( 1 );
	expect( $innerBlocks[0]['type'] )->toBe( 'paragraph' );
} );

test( 'details block has transforms to group', function (): void {
	$block      = new DetailsBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toHaveKey( 'group' );
} );

test( 'details block has keywords', function (): void {
	$block = new DetailsBlock();

	expect( $block->getKeywords() )->toContain( 'details' );
	expect( $block->getKeywords() )->toContain( 'accordion' );
	expect( $block->getKeywords() )->toContain( 'collapsible' );
	expect( $block->getKeywords() )->toContain( 'faq' );
} );
