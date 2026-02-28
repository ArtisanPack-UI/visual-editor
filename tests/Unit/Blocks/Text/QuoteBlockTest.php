<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Text\Quote\QuoteBlock;

test( 'quote block has correct type', function (): void {
	$block = new QuoteBlock();

	expect( $block->getType() )->toBe( 'quote' );
} );

test( 'quote block has correct category', function (): void {
	$block = new QuoteBlock();

	expect( $block->getCategory() )->toBe( 'text' );
} );

test( 'quote block content schema is empty', function (): void {
	$block  = new QuoteBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toBeEmpty();
} );

test( 'quote block style schema has color fields', function (): void {
	$block  = new QuoteBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'textColor' );
	expect( $schema )->toHaveKey( 'backgroundColor' );
	expect( $schema )->not->toHaveKey( 'style' );
} );

test( 'quote block transforms to paragraph and heading', function (): void {
	$block      = new QuoteBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toHaveKey( 'paragraph' );
	expect( $transforms )->toHaveKey( 'heading' );
} );

test( 'quote block renders blockquote', function (): void {
	$block  = new QuoteBlock();
	$output = $block->render(
		[ 'text' => 'A wise quote' ],
		[ 'alignment' => 'left' ],
	);

	expect( $output )->toContain( '<blockquote' );
	expect( $output )->toContain( 'A wise quote' );
} );

test( 'quote block does not render citation when showCitation is false', function (): void {
	$block  = new QuoteBlock();
	$output = $block->render(
		[ 'text' => 'A wise quote', 'showCitation' => false ],
		[ 'alignment' => 'left' ],
	);

	expect( $output )->not->toContain( '<cite' );
} );

test( 'quote block renders citation when showCitation is true and citation exists', function (): void {
	$block  = new QuoteBlock();
	$output = $block->render(
		[ 'text' => 'A wise quote', 'showCitation' => true, 'citation' => 'Albert Einstein' ],
		[ 'alignment' => 'left' ],
	);

	expect( $output )->toContain( '<cite' );
	expect( $output )->toContain( 'Albert Einstein' );
} );

test( 'quote block does not render citation when showCitation is true but citation is empty', function (): void {
	$block  = new QuoteBlock();
	$output = $block->render(
		[ 'text' => 'A wise quote', 'showCitation' => true, 'citation' => '' ],
		[ 'alignment' => 'left' ],
	);

	expect( $output )->not->toContain( '<cite' );
} );

test( 'quote block renders with inner blocks', function (): void {
	$block  = new QuoteBlock();
	$output = $block->render(
		[ 'text' => '' ],
		[ 'alignment' => 'left' ],
		[],
		[ '<p>Inner paragraph</p>' ],
	);

	expect( $output )->toContain( '<blockquote' );
	expect( $output )->toContain( 'Inner paragraph' );
} );

test( 'quote block has allowedChildren null in metadata', function (): void {
	$block = new QuoteBlock();

	expect( $block->getAllowedChildren() )->toBeNull();
} );

test( 'quote block has showCitation and citation attributes', function (): void {
	$block      = new QuoteBlock();
	$attributes = $block->getAttributes();

	expect( $attributes )->toHaveKey( 'showCitation' );
	expect( $attributes )->toHaveKey( 'citation' );
	expect( $attributes['showCitation']['type'] )->toBe( 'boolean' );
	expect( $attributes['citation']['type'] )->toBe( 'rich_text' );
} );

test( 'quote block has custom toolbar', function (): void {
	$block = new QuoteBlock();

	expect( $block->hasCustomToolbar() )->toBeTrue();
} );

test( 'quote block toolbar controls include citation toggle', function (): void {
	$block    = new QuoteBlock();
	$controls = $block->getToolbarControls();

	$quoteGroup = collect( $controls )->firstWhere( 'group', 'quote' );

	expect( $quoteGroup )->not->toBeNull();
	expect( $quoteGroup['controls'][0]['field'] )->toBe( 'showCitation' );
	expect( $quoteGroup['controls'][0]['type'] )->toBe( 'toggle' );
} );

test( 'quote block editor always renders inner blocks container', function (): void {
	$block  = new QuoteBlock();
	$output = $block->renderEditor(
		[ 'text' => 'Hello' ],
		[ 'alignment' => 'left' ],
	);

	expect( $output )->toContain( 'data-ve-inner-blocks' );
	expect( $output )->toContain( 'contenteditable="true"' );
	expect( $output )->toContain( 'data-ve-enter-new-block' );
} );

test( 'quote block editor renders inner blocks container when inner blocks exist', function (): void {
	$block  = new QuoteBlock();
	$output = $block->renderEditor(
		[ 'text' => '' ],
		[ 'alignment' => 'left' ],
		[ 'editing'   => true ],
		[ '<p>Inner content</p>' ],
	);

	expect( $output )->toContain( 'data-ve-inner-blocks' );
	expect( $output )->toContain( 'Inner content' );
} );

test( 'quote block editor renders citation when showCitation is true', function (): void {
	$block  = new QuoteBlock();
	$output = $block->renderEditor(
		[ 'text' => '', 'showCitation' => true, 'citation' => 'Someone' ],
		[ 'alignment' => 'left' ],
		[ 'editing'   => true ],
	);

	expect( $output )->toContain( '<cite' );
	expect( $output )->toContain( 'Someone' );
} );
