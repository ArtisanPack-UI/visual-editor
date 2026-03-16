<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Media\CoverBlock;

test( 'cover block has correct type and category', function (): void {
	$block = new CoverBlock();

	expect( $block->getType() )->toBe( 'cover' );
	expect( $block->getCategory() )->toBe( 'media' );
} );

test( 'cover block is public and supports inner blocks', function (): void {
	$block = new CoverBlock();

	expect( $block->isPublic() )->toBeTrue();
	expect( $block->supportsInnerBlocks() )->toBeTrue();
	expect( $block->getInnerBlocksOrientation() )->toBe( 'vertical' );
} );

test( 'cover block allows any children', function (): void {
	$block = new CoverBlock();

	expect( $block->getAllowedChildren() )->toBeNull();
} );

test( 'cover block content schema has media type url alt focal point and effect fields', function (): void {
	$block  = new CoverBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'mediaType' );
	expect( $schema )->toHaveKey( 'mediaUrl' );
	expect( $schema )->toHaveKey( 'alt' );
	expect( $schema )->toHaveKey( 'focalPoint' );
	expect( $schema )->toHaveKey( 'hasParallax' );
	expect( $schema )->toHaveKey( 'isRepeated' );
} );

test( 'cover block style schema has overlay color and opacity fields', function (): void {
	$block  = new CoverBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'overlayColor' );
	expect( $schema['overlayColor']['type'] )->toBe( 'color' );
	expect( $schema['overlayColor']['default'] )->toBe( '#000000' );

	expect( $schema )->toHaveKey( 'overlayOpacity' );
	expect( $schema['overlayOpacity']['type'] )->toBe( 'range' );
	expect( $schema['overlayOpacity']['min'] )->toBe( 0 );
	expect( $schema['overlayOpacity']['max'] )->toBe( 100 );
	expect( $schema['overlayOpacity']['default'] )->toBe( 50 );
} );

test( 'cover block content schema has content width fields', function (): void {
	$block  = new CoverBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'useContentWidth' );
	expect( $schema['useContentWidth']['type'] )->toBe( 'toggle' );
	expect( $schema['useContentWidth']['default'] )->toBeFalse();

	expect( $schema )->toHaveKey( 'contentMaxWidth' );
	expect( $schema['contentMaxWidth']['type'] )->toBe( 'text' );
} );

test( 'cover block media type has image video and color options', function (): void {
	$block  = new CoverBlock();
	$schema = $block->getContentSchema();

	expect( $schema['mediaType']['type'] )->toBe( 'select' );
	expect( $schema['mediaType']['options'] )->toHaveKey( 'image' );
	expect( $schema['mediaType']['options'] )->toHaveKey( 'video' );
	expect( $schema['mediaType']['options'] )->toHaveKey( 'color' );
	expect( $schema['mediaType']['default'] )->toBe( 'image' );
} );

test( 'cover block media url uses media picker type', function (): void {
	$block  = new CoverBlock();
	$schema = $block->getContentSchema();

	expect( $schema['mediaUrl']['type'] )->toBe( 'media_picker' );
} );

test( 'cover block focal point defaults to center', function (): void {
	$block  = new CoverBlock();
	$schema = $block->getContentSchema();

	expect( $schema['focalPoint']['type'] )->toBe( 'focal_point' );
	expect( $schema['focalPoint']['default'] )->toBe( [ 'x' => 0.5, 'y' => 0.5 ] );
} );

test( 'cover block style schema has min height field', function (): void {
	$block  = new CoverBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'minHeight' );
	expect( $schema['minHeight']['type'] )->toBe( 'text' );
	expect( $schema['minHeight']['default'] )->toBe( '430px' );
} );

test( 'cover block style schema has content alignment and full width as inspector hidden', function (): void {
	$block  = new CoverBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'contentAlignment' );
	expect( $schema['contentAlignment']['inspector'] )->toBeFalse();
	expect( $schema['contentAlignment']['options'] )->toHaveCount( 9 );

	expect( $schema )->toHaveKey( 'fullWidth' );
	expect( $schema['fullWidth']['inspector'] )->toBeFalse();

	expect( $schema )->toHaveKey( 'minHeightUnit' );
	expect( $schema['minHeightUnit']['inspector'] )->toBeFalse();
} );

test( 'cover block defaults to 50 percent overlay opacity and 430px min height', function (): void {
	$block         = new CoverBlock();
	$styleDefaults = $block->getDefaultStyles();

	expect( $styleDefaults['overlayOpacity'] )->toBe( 50 );
	expect( $styleDefaults['overlayColor'] )->toBe( '#000000' );
	expect( $styleDefaults['minHeight'] )->toBe( '430px' );
} );

test( 'cover block has default inner blocks with heading and paragraph', function (): void {
	$block        = new CoverBlock();
	$defaultInner = $block->getDefaultInnerBlocks();

	expect( $defaultInner )->toHaveCount( 2 );
	expect( $defaultInner[0]['type'] )->toBe( 'heading' );
	expect( $defaultInner[1]['type'] )->toBe( 'paragraph' );
} );

test( 'cover block has transforms to image and group', function (): void {
	$block      = new CoverBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toHaveKey( 'image' );
	expect( $transforms )->toHaveKey( 'group' );
} );

test( 'cover block has custom toolbar and inspector', function (): void {
	$block = new CoverBlock();

	expect( $block->hasCustomToolbar() )->toBeTrue();
	expect( $block->hasCustomInspector() )->toBeTrue();
} );

test( 'cover block has toolbar controls with position and actions groups', function (): void {
	$block    = new CoverBlock();
	$controls = $block->getToolbarControls();

	$groups = array_column( $controls, 'group' );
	expect( $groups )->toContain( 'cover-position' );
	expect( $groups )->toContain( 'cover-actions' );

	$actionsGroup = collect( $controls )->firstWhere( 'group', 'cover-actions' );
	$fields       = array_column( $actionsGroup['controls'], 'field' );
	expect( $fields )->toContain( 'replace' );
	expect( $fields )->toContain( 'fullHeight' );

	$positionGroup = collect( $controls )->firstWhere( 'group', 'cover-position' );
	$posFields     = array_column( $positionGroup['controls'], 'field' );
	expect( $posFields )->toContain( 'contentAlignment' );
} );

test( 'cover block supports wide and full alignment', function (): void {
	$block = new CoverBlock();

	expect( $block->supportsFeature( 'align' ) )->toBeTrue();
	expect( $block->getSupportedAlignments() )->toContain( 'wide' );
	expect( $block->getSupportedAlignments() )->toContain( 'full' );
} );

test( 'cover block supports text color and spacing', function (): void {
	$block = new CoverBlock();

	expect( $block->supportsFeature( 'color.text' ) )->toBeTrue();
	expect( $block->supportsFeature( 'spacing.padding' ) )->toBeTrue();
	expect( $block->supportsFeature( 'spacing.margin' ) )->toBeTrue();
} );

test( 'cover block renders with image background', function (): void {
	$block  = new CoverBlock();
	$output = $block->render(
		[ 'mediaType' => 'image', 'mediaUrl' => 'test.jpg', 'alt' => 'Test image', 'focalPoint' => [ 'x' => 0.5, 'y' => 0.5 ] ],
		[ 'minHeight' => '430px', 'contentAlignment' => 'center', 'overlayColor' => '#000000', 'overlayOpacity' => 50 ],
	);

	expect( $output )->toContain( 've-block-cover' );
	expect( $output )->toContain( '<img' );
	expect( $output )->toContain( 'test.jpg' );
	expect( $output )->toContain( 've-block-cover__overlay' );
} );

test( 'cover block renders with video background', function (): void {
	$block  = new CoverBlock();
	$output = $block->render(
		[ 'mediaType' => 'video', 'mediaUrl' => 'test.mp4' ],
		[ 'minHeight' => '430px', 'contentAlignment' => 'center', 'overlayColor' => '#000000', 'overlayOpacity' => 50 ],
	);

	expect( $output )->toContain( '<video' );
	expect( $output )->toContain( 'test.mp4' );
	expect( $output )->toContain( 'autoplay' );
	expect( $output )->toContain( 'muted' );
	expect( $output )->toContain( 'loop' );
	expect( $output )->toContain( 'aria-hidden="true"' );
} );

test( 'cover block renders color only mode without media elements', function (): void {
	$block  = new CoverBlock();
	$output = $block->render(
		[ 'mediaType' => 'color' ],
		[ 'minHeight' => '300px', 'contentAlignment' => 'center', 'overlayColor' => '#ff0000', 'overlayOpacity' => 80 ],
	);

	expect( $output )->toContain( 've-block-cover' );
	expect( $output )->not->toContain( '<img' );
	expect( $output )->not->toContain( '<video' );
	expect( $output )->toContain( 've-block-cover__overlay' );
} );

test( 'cover block renders parallax background style', function (): void {
	$block  = new CoverBlock();
	$output = $block->render(
		[ 'mediaType' => 'image', 'mediaUrl' => 'test.jpg', 'hasParallax' => true, 'focalPoint' => [ 'x' => 0.3, 'y' => 0.7 ] ],
		[ 'minHeight' => '430px', 'contentAlignment' => 'center', 'overlayColor' => '#000000', 'overlayOpacity' => 50 ],
	);

	expect( $output )->toContain( 'background-attachment: fixed' );
	expect( $output )->toContain( '30% 70%' );
} );

test( 'cover block renders inner blocks content area', function (): void {
	$block  = new CoverBlock();
	$output = $block->render(
		[ 'mediaType' => 'image', 'mediaUrl' => 'test.jpg' ],
		[ 'minHeight' => '430px', 'contentAlignment' => 'center', 'overlayColor' => '#000000', 'overlayOpacity' => 50 ],
		[],
		[ '<h2>Hello World</h2>', '<p>Test content</p>' ],
	);

	expect( $output )->toContain( 've-block-cover__content' );
	expect( $output )->toContain( 'Hello World' );
	expect( $output )->toContain( 'Test content' );
} );

test( 'cover block renders content alignment correctly', function (): void {
	$block  = new CoverBlock();
	$output = $block->render(
		[ 'mediaType' => 'image', 'mediaUrl' => 'test.jpg' ],
		[ 'minHeight' => '430px', 'contentAlignment' => 'bottom-right', 'overlayColor' => '#000000', 'overlayOpacity' => 50 ],
	);

	expect( $output )->toContain( 'justify-content: flex-end' );
	expect( $output )->toContain( 'align-items: flex-end' );
} );

test( 'cover block decorative images have aria hidden', function (): void {
	$block  = new CoverBlock();
	$output = $block->render(
		[ 'mediaType' => 'image', 'mediaUrl' => 'test.jpg', 'alt' => '' ],
		[ 'minHeight' => '430px', 'contentAlignment' => 'center', 'overlayColor' => '#000000', 'overlayOpacity' => 50 ],
	);

	expect( $output )->toContain( 'aria-hidden="true"' );
} );

test( 'cover block renders content max width when use content width is enabled', function (): void {
	$block  = new CoverBlock();
	$output = $block->render(
		[ 'mediaType' => 'color', 'useContentWidth' => true, 'contentMaxWidth' => '800px' ],
		[ 'minHeight' => '430px', 'contentAlignment' => 'center', 'overlayColor' => '#000000', 'overlayOpacity' => 50 ],
	);

	expect( $output )->toContain( 'max-width: 800px' );
	expect( $output )->toContain( 'margin-left: auto' );
} );

test( 'cover block to array includes default inner blocks', function (): void {
	$block = new CoverBlock();
	$array = $block->toArray();

	expect( $array['defaultInnerBlocks'] )->toHaveCount( 2 );
	expect( $array['defaultInnerBlocks'][0]['type'] )->toBe( 'heading' );
	expect( $array['defaultInnerBlocks'][1]['type'] )->toBe( 'paragraph' );
} );

test( 'cover block has keywords', function (): void {
	$block = new CoverBlock();

	expect( $block->getKeywords() )->toContain( 'cover' );
	expect( $block->getKeywords() )->toContain( 'hero' );
	expect( $block->getKeywords() )->toContain( 'banner' );
} );
