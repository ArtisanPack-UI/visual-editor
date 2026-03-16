<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Media\MediaTextBlock;

test( 'media text block has correct type and category', function (): void {
	$block = new MediaTextBlock();

	expect( $block->getType() )->toBe( 'media-text' );
	expect( $block->getCategory() )->toBe( 'media' );
} );

test( 'media text block is public and supports inner blocks', function (): void {
	$block = new MediaTextBlock();

	expect( $block->isPublic() )->toBeTrue();
	expect( $block->supportsInnerBlocks() )->toBeTrue();
	expect( $block->getInnerBlocksOrientation() )->toBe( 'vertical' );
} );

test( 'media text block allows any children', function (): void {
	$block = new MediaTextBlock();

	expect( $block->getAllowedChildren() )->toBeNull();
} );

test( 'media text block content schema has media type url alt and focal point fields', function (): void {
	$block  = new MediaTextBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'mediaType' );
	expect( $schema )->toHaveKey( 'mediaUrl' );
	expect( $schema )->toHaveKey( 'mediaAlt' );
	expect( $schema )->toHaveKey( 'focalPoint' );
} );

test( 'media text block media type has image and video options', function (): void {
	$block  = new MediaTextBlock();
	$schema = $block->getContentSchema();

	expect( $schema['mediaType']['type'] )->toBe( 'select' );
	expect( $schema['mediaType']['options'] )->toHaveKey( 'image' );
	expect( $schema['mediaType']['options'] )->toHaveKey( 'video' );
	expect( $schema['mediaType']['default'] )->toBe( 'image' );
} );

test( 'media text block media url uses media picker type', function (): void {
	$block  = new MediaTextBlock();
	$schema = $block->getContentSchema();

	expect( $schema['mediaUrl']['type'] )->toBe( 'media_picker' );
} );

test( 'media text block style schema has layout fields', function (): void {
	$block  = new MediaTextBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'mediaPosition' );
	expect( $schema )->toHaveKey( 'mediaWidth' );
	expect( $schema )->toHaveKey( 'verticalAlignment' );
	expect( $schema )->toHaveKey( 'isStackedOnMobile' );
	expect( $schema )->toHaveKey( 'gridGap' );
} );

test( 'media text block media position defaults to left', function (): void {
	$block  = new MediaTextBlock();
	$schema = $block->getStyleSchema();

	expect( $schema['mediaPosition']['type'] )->toBe( 'select' );
	expect( $schema['mediaPosition']['options'] )->toHaveKey( 'left' );
	expect( $schema['mediaPosition']['options'] )->toHaveKey( 'right' );
	expect( $schema['mediaPosition']['default'] )->toBe( 'left' );
} );

test( 'media text block media width is a range from 25 to 75', function (): void {
	$block  = new MediaTextBlock();
	$schema = $block->getStyleSchema();

	expect( $schema['mediaWidth']['type'] )->toBe( 'range' );
	expect( $schema['mediaWidth']['min'] )->toBe( 25 );
	expect( $schema['mediaWidth']['max'] )->toBe( 75 );
	expect( $schema['mediaWidth']['default'] )->toBe( 50 );
} );

test( 'media text block vertical alignment options include top center bottom', function (): void {
	$block  = new MediaTextBlock();
	$schema = $block->getStyleSchema();

	expect( $schema['verticalAlignment']['options'] )->toHaveKey( 'top' );
	expect( $schema['verticalAlignment']['options'] )->toHaveKey( 'center' );
	expect( $schema['verticalAlignment']['options'] )->toHaveKey( 'bottom' );
	expect( $schema['verticalAlignment']['default'] )->toBe( 'top' );
} );

test( 'media text block style schema has image fill and border radius fields', function (): void {
	$block  = new MediaTextBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'imageFill' );
	expect( $schema )->toHaveKey( 'mediaBorderRadius' );
	expect( $schema['imageFill']['type'] )->toBe( 'toggle' );
	expect( $schema['imageFill']['default'] )->toBeFalse();
} );

test( 'media text block style schema has content padding and background color fields', function (): void {
	$block  = new MediaTextBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'contentPadding' );
	expect( $schema )->toHaveKey( 'contentBackgroundColor' );
	expect( $schema['contentPadding']['default'] )->toBe( '1rem' );
} );

test( 'media text block defaults to stacked on mobile', function (): void {
	$block    = new MediaTextBlock();
	$defaults = $block->getDefaultStyles();

	expect( $defaults['isStackedOnMobile'] )->toBeTrue();
	expect( $defaults['mediaPosition'] )->toBe( 'left' );
	expect( $defaults['mediaWidth'] )->toBe( 50 );
} );

test( 'media text block has default inner blocks with one paragraph', function (): void {
	$block        = new MediaTextBlock();
	$defaultInner = $block->getDefaultInnerBlocks();

	expect( $defaultInner )->toHaveCount( 1 );
	expect( $defaultInner[0]['type'] )->toBe( 'paragraph' );
} );

test( 'media text block has transforms to cover and columns', function (): void {
	$block      = new MediaTextBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toHaveKey( 'cover' );
	expect( $transforms )->toHaveKey( 'columns' );
} );

test( 'media text block has toolbar controls with layout and media groups', function (): void {
	$block    = new MediaTextBlock();
	$controls = $block->getToolbarControls();

	$groups = array_column( $controls, 'group' );
	expect( $groups )->toContain( 'media-text-layout' );
	expect( $groups )->toContain( 'media-text-media' );

	$layoutGroup = collect( $controls )->firstWhere( 'group', 'media-text-layout' );
	$fields      = array_column( $layoutGroup['controls'], 'field' );
	expect( $fields )->toContain( 'mediaPosition' );
	expect( $fields )->toContain( 'verticalAlignment' );
} );

test( 'media text block supports wide and full alignment', function (): void {
	$block = new MediaTextBlock();

	expect( $block->supportsFeature( 'align' ) )->toBeTrue();
	expect( $block->getSupportedAlignments() )->toContain( 'wide' );
	expect( $block->getSupportedAlignments() )->toContain( 'full' );
} );

test( 'media text block supports text and background color', function (): void {
	$block = new MediaTextBlock();

	expect( $block->supportsFeature( 'color.text' ) )->toBeTrue();
	expect( $block->supportsFeature( 'color.background' ) )->toBeTrue();
} );

test( 'media text block renders side by side layout with image', function (): void {
	$block  = new MediaTextBlock();
	$output = $block->render(
		[ 'mediaType' => 'image', 'mediaUrl' => 'test.jpg', 'mediaAlt' => 'Test' ],
		[ 'mediaPosition' => 'left', 'mediaWidth' => 50, 'verticalAlignment' => 'top', 'contentPadding' => '1rem' ],
	);

	expect( $output )->toContain( 've-block-media-text' );
	expect( $output )->toContain( 'grid-template-columns' );
	expect( $output )->toContain( '<img' );
	expect( $output )->toContain( 'test.jpg' );
} );

test( 'media text block renders media on right when position is right', function (): void {
	$block  = new MediaTextBlock();
	$output = $block->render(
		[ 'mediaType' => 'image', 'mediaUrl' => 'test.jpg', 'mediaAlt' => 'Test' ],
		[ 'mediaPosition' => 'right', 'mediaWidth' => 40, 'verticalAlignment' => 'top', 'contentPadding' => '1rem' ],
	);

	expect( $output )->toContain( 'grid-template-columns: 60% 40%' );
} );

test( 'media text block renders video media', function (): void {
	$block  = new MediaTextBlock();
	$output = $block->render(
		[ 'mediaType' => 'video', 'mediaUrl' => 'test.mp4' ],
		[ 'mediaPosition' => 'left', 'mediaWidth' => 50, 'verticalAlignment' => 'top', 'contentPadding' => '1rem' ],
	);

	expect( $output )->toContain( '<video' );
	expect( $output )->toContain( 'test.mp4' );
	expect( $output )->toContain( 'autoplay' );
	expect( $output )->toContain( 'muted' );
	expect( $output )->toContain( 'loop' );
} );

test( 'media text block renders stacked on mobile class', function (): void {
	$block  = new MediaTextBlock();
	$output = $block->render(
		[ 'mediaType' => 'image', 'mediaUrl' => 'test.jpg' ],
		[ 'mediaPosition' => 'left', 'mediaWidth' => 50, 'verticalAlignment' => 'top', 'isStackedOnMobile' => true, 'contentPadding' => '1rem' ],
	);

	expect( $output )->toContain( 've-media-text--stacked-mobile' );
	expect( $output )->toContain( '@media(max-width: 600px)' );
} );

test( 'media text block renders image fill mode with object fit cover', function (): void {
	$block  = new MediaTextBlock();
	$output = $block->render(
		[ 'mediaType' => 'image', 'mediaUrl' => 'test.jpg', 'mediaAlt' => 'Test', 'focalPoint' => [ 'x' => 0.3, 'y' => 0.7 ] ],
		[ 'mediaPosition' => 'left', 'mediaWidth' => 50, 'verticalAlignment' => 'top', 'imageFill' => true, 'contentPadding' => '1rem' ],
	);

	expect( $output )->toContain( 'object-fit: cover' );
	expect( $output )->toContain( '30% 70%' );
} );

test( 'media text block renders inner blocks in content area', function (): void {
	$block  = new MediaTextBlock();
	$output = $block->render(
		[ 'mediaType' => 'image', 'mediaUrl' => 'test.jpg' ],
		[ 'mediaPosition' => 'left', 'mediaWidth' => 50, 'verticalAlignment' => 'top', 'contentPadding' => '1rem' ],
		[],
		[ '<p>Inner content</p>' ],
	);

	expect( $output )->toContain( 've-media-text__content' );
	expect( $output )->toContain( 'Inner content' );
} );

test( 'media text block renders vertical alignment center', function (): void {
	$block  = new MediaTextBlock();
	$output = $block->render(
		[ 'mediaType' => 'image', 'mediaUrl' => 'test.jpg' ],
		[ 'mediaPosition' => 'left', 'mediaWidth' => 50, 'verticalAlignment' => 'center', 'contentPadding' => '1rem' ],
	);

	expect( $output )->toContain( 'align-items: center' );
} );

test( 'media text block to array includes default inner blocks', function (): void {
	$block = new MediaTextBlock();
	$array = $block->toArray();

	expect( $array['defaultInnerBlocks'] )->toHaveCount( 1 );
	expect( $array['defaultInnerBlocks'][0]['type'] )->toBe( 'paragraph' );
} );

test( 'media text block has keywords', function (): void {
	$block = new MediaTextBlock();

	expect( $block->getKeywords() )->toContain( 'media' );
	expect( $block->getKeywords() )->toContain( 'text' );
	expect( $block->getKeywords() )->toContain( 'image' );
} );
