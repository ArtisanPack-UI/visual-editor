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
	expect( $schema )->toHaveKey( 'fontSize' );
	expect( $schema )->toHaveKey( 'padding' );
	expect( $schema )->toHaveKey( 'margin' );
	expect( $schema )->toHaveKey( 'blockSpacing' );
	expect( $schema )->toHaveKey( 'border' );
	expect( $schema )->toHaveKey( 'backgroundImage' );
	expect( $schema )->toHaveKey( 'backgroundSize' );
	expect( $schema )->toHaveKey( 'backgroundPosition' );
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

test( 'quote block renders with font size class', function (): void {
	$block  = new QuoteBlock();
	$output = $block->render(
		[ 'text' => 'Styled quote' ],
		[ 'alignment' => 'left', 'fontSize' => 'large' ],
	);

	expect( $output )->toContain( 'text-large' );
} );

test( 'quote block renders with padding', function (): void {
	$block  = new QuoteBlock();
	$output = $block->render(
		[ 'text' => 'Padded quote' ],
		[
			'alignment' => 'left',
			'padding'   => [ 'top' => '10px', 'right' => '20px', 'bottom' => '10px', 'left' => '20px' ],
		],
	);

	expect( $output )->toContain( 'padding: 10px 20px 10px 20px' );
} );

test( 'quote block renders with margin', function (): void {
	$block  = new QuoteBlock();
	$output = $block->render(
		[ 'text' => 'Margin quote' ],
		[
			'alignment' => 'left',
			'margin'    => [ 'top' => '2rem', 'bottom' => '2rem' ],
		],
	);

	expect( $output )->toContain( 'margin-top: 2rem' );
	expect( $output )->toContain( 'margin-bottom: 2rem' );
} );

test( 'quote block renders with border', function (): void {
	$block  = new QuoteBlock();
	$output = $block->render(
		[ 'text' => 'Bordered quote' ],
		[
			'alignment' => 'left',
			'border'    => [
				'width'      => '2',
				'widthUnit'  => 'px',
				'style'      => 'solid',
				'color'      => '#333333',
				'radius'     => '8',
				'radiusUnit' => 'px',
			],
		],
	);

	expect( $output )->toContain( 'border: 2px solid #333333' );
	expect( $output )->toContain( 'border-radius: 8px' );
} );

test( 'quote block renders with background image', function (): void {
	$block  = new QuoteBlock();
	$output = $block->render(
		[ 'text' => 'Background quote' ],
		[
			'alignment'          => 'left',
			'backgroundImage'    => 'https://example.com/image.jpg',
			'backgroundSize'     => 'cover',
			'backgroundPosition' => 'top left',
		],
	);

	expect( $output )->toContain( 'background-image: url(' );
	expect( $output )->toContain( 'https://example.com/image.jpg' );
	expect( $output )->toContain( 'background-size: cover' );
	expect( $output )->toContain( 'background-position: top left' );
} );

test( 'quote block renders block spacing on inner blocks wrapper', function (): void {
	$block  = new QuoteBlock();
	$output = $block->render(
		[ 'text' => 'Spaced quote' ],
		[
			'alignment'    => 'left',
			'blockSpacing' => '1.5rem',
		],
	);

	expect( $output )->toContain( 'gap: 1.5rem' );
	expect( $output )->toContain( 'display: flex' );
	expect( $output )->toContain( 'flex-direction: column' );
} );

test( 'quote block editor renders with border styles', function (): void {
	$block  = new QuoteBlock();
	$output = $block->renderEditor(
		[ 'text' => 'Bordered quote' ],
		[
			'alignment' => 'left',
			'border'    => [
				'width'     => '1',
				'widthUnit' => 'px',
				'style'     => 'dashed',
				'color'     => '#ff0000',
			],
		],
	);

	expect( $output )->toContain( 'border: 1px dashed #ff0000' );
} );

test( 'quote block editor renders with background image', function (): void {
	$block  = new QuoteBlock();
	$output = $block->renderEditor(
		[ 'text' => 'BG quote' ],
		[
			'alignment'       => 'left',
			'backgroundImage' => 'https://example.com/bg.png',
		],
	);

	expect( $output )->toContain( 'background-image: url(' );
	expect( $output )->toContain( 'https://example.com/bg.png' );
} );

test( 'quote block editor renders with font size class', function (): void {
	$block  = new QuoteBlock();
	$output = $block->renderEditor(
		[ 'text' => 'Large quote' ],
		[ 'alignment' => 'left', 'fontSize' => 'xl' ],
	);

	expect( $output )->toContain( 'text-xl' );
} );
