<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Layout\GroupBlock;

test( 'group block has correct type and category', function (): void {
	$block = new GroupBlock();

	expect( $block->getType() )->toBe( 'group' );
	expect( $block->getCategory() )->toBe( 'layout' );
} );

test( 'group block content schema has tag field with header and footer options', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'tag' );
	expect( $schema['tag']['type'] )->toBe( 'select' );
	expect( $schema['tag']['options'] )->toHaveKey( 'div' );
	expect( $schema['tag']['options'] )->toHaveKey( 'section' );
	expect( $schema['tag']['options'] )->toHaveKey( 'header' );
	expect( $schema['tag']['options'] )->toHaveKey( 'footer' );
} );

test( 'group block content schema has flexDirection field with inspector false', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'flexDirection' );
	expect( $schema['flexDirection']['type'] )->toBe( 'select' );
	expect( $schema['flexDirection']['default'] )->toBe( 'column' );
	expect( $schema['flexDirection']['inspector'] )->toBeFalse();
} );

test( 'group block content schema has flexWrap field with inspector false', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'flexWrap' );
	expect( $schema['flexWrap']['type'] )->toBe( 'select' );
	expect( $schema['flexWrap']['default'] )->toBe( 'nowrap' );
	expect( $schema['flexWrap']['inspector'] )->toBeFalse();
} );

test( 'group block content schema has justifyContent field with inspector false', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'justifyContent' );
	expect( $schema['justifyContent']['type'] )->toBe( 'select' );
	expect( $schema['justifyContent']['default'] )->toBe( 'flex-start' );
	expect( $schema['justifyContent']['options'] )->toHaveKey( 'flex-start' );
	expect( $schema['justifyContent']['options'] )->toHaveKey( 'center' );
	expect( $schema['justifyContent']['options'] )->toHaveKey( 'flex-end' );
	expect( $schema['justifyContent']['options'] )->toHaveKey( 'space-between' );
	expect( $schema['justifyContent']['inspector'] )->toBeFalse();
} );

test( 'group block content schema has useContentWidth field', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'useContentWidth' );
	expect( $schema['useContentWidth']['type'] )->toBe( 'toggle' );
	expect( $schema['useContentWidth']['default'] )->toBeFalse();
	expect( $schema['useContentWidth']['inspector'] )->toBeFalse();
} );

test( 'group block content schema has contentWidth field', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'contentWidth' );
	expect( $schema['contentWidth']['type'] )->toBe( 'text' );
	expect( $schema['contentWidth']['default'] )->toBe( '' );
	expect( $schema['contentWidth']['inspector'] )->toBeFalse();
} );

test( 'group block content schema has wideWidth field', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'wideWidth' );
	expect( $schema['wideWidth']['type'] )->toBe( 'text' );
	expect( $schema['wideWidth']['default'] )->toBe( '' );
	expect( $schema['wideWidth']['inspector'] )->toBeFalse();
} );

test( 'group block style schema has all expected fields', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'backgroundColor' );
	expect( $schema )->toHaveKey( 'padding' );
	expect( $schema )->toHaveKey( 'margin' );
	expect( $schema )->toHaveKey( 'border' );
	expect( $schema['border']['type'] )->toBe( 'border' );
	expect( $schema )->toHaveKey( 'minHeight' );
	expect( $schema )->toHaveKey( 'verticalAlignment' );
} );

test( 'group block style schema has useFlexbox field', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'useFlexbox' );
	expect( $schema['useFlexbox']['type'] )->toBe( 'toggle' );
	expect( $schema['useFlexbox']['default'] )->toBeFalse();
	expect( $schema['useFlexbox']['inspector'] )->toBeFalse();
} );

test( 'group block style schema has fillHeight field', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'fillHeight' );
	expect( $schema['fillHeight']['type'] )->toBe( 'toggle' );
	expect( $schema['fillHeight']['default'] )->toBeFalse();
	expect( $schema['fillHeight']['inspector'] )->toBeFalse();
} );

test( 'group block style schema has innerSpacing field', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'innerSpacing' );
	expect( $schema['innerSpacing']['type'] )->toBe( 'select' );
	expect( $schema['innerSpacing']['default'] )->toBe( 'normal' );
	expect( $schema['innerSpacing']['inspector'] )->toBeFalse();
} );

test( 'group block defaults to div tag', function (): void {
	$block    = new GroupBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['tag'] )->toBe( 'div' );
} );

test( 'group block renders with correct html tag', function (): void {
	$block  = new GroupBlock();
	$output = $block->render( [ 'tag' => 'section' ], [ 'verticalAlignment' => 'top' ] );

	expect( $output )->toContain( '<section' );
	expect( $output )->toContain( '</section>' );
	expect( $output )->toContain( 've-block-group' );
} );

test( 'group block renders with background color', function (): void {
	$block  = new GroupBlock();
	$output = $block->render( [ 'tag' => 'div' ], [ 'backgroundColor' => '#ff0000', 'verticalAlignment' => 'top' ] );

	expect( $output )->toContain( 'background-color: #ff0000' );
} );

test( 'group block supports spacing and border', function (): void {
	$block = new GroupBlock();

	expect( $block->supportsFeature( 'spacing.margin' ) )->toBeTrue();
	expect( $block->supportsFeature( 'spacing.padding' ) )->toBeTrue();
	expect( $block->supportsFeature( 'border' ) )->toBeTrue();
	expect( $block->supportsFeature( 'color.background' ) )->toBeTrue();
} );

test( 'group block renders with flex direction row', function (): void {
	$block  = new GroupBlock();
	$output = $block->render(
		[ 'tag' => 'div', 'flexDirection' => 'row', 'flexWrap' => 'nowrap' ],
		[ 'verticalAlignment' => 'top' ],
	);

	expect( $output )->toContain( 'flex-direction: row' );
	expect( $output )->toContain( 'flex-wrap: nowrap' );
} );

test( 'group block has three variations', function (): void {
	$block      = new GroupBlock();
	$variations = $block->getVariations();

	expect( $variations )->toHaveCount( 3 );
} );

test( 'group block variations include group row and stack', function (): void {
	$block = new GroupBlock();
	$names = array_column( $block->getVariations(), 'name' );

	expect( $names )->toContain( 'group' );
	expect( $names )->toContain( 'row' );
	expect( $names )->toContain( 'stack' );
} );

test( 'group block has one default variation', function (): void {
	$block    = new GroupBlock();
	$defaults = array_filter( $block->getVariations(), fn ( $v ) => $v['isDefault'] );

	expect( $defaults )->toHaveCount( 1 );
	expect( array_values( $defaults )[0]['name'] )->toBe( 'group' );
} );

test( 'group block row variation has row flex direction', function (): void {
	$block      = new GroupBlock();
	$variations = $block->getVariations();
	$row        = array_values( array_filter( $variations, fn ( $v ) => 'row' === $v['name'] ) )[0];

	expect( $row['attributes']['flexDirection'] )->toBe( 'row' );
} );

test( 'group block style schema has textColor field', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'textColor' );
	expect( $schema['textColor']['type'] )->toBe( 'color' );
	expect( $schema['textColor']['default'] )->toBeNull();
} );

test( 'group block style schema has gap field', function (): void {
	$block  = new GroupBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'gap' );
	expect( $schema['gap']['type'] )->toBe( 'text' );
	expect( $schema['gap']['default'] )->toBeNull();
} );

test( 'group block renders with text color', function (): void {
	$block  = new GroupBlock();
	$output = $block->render(
		[ 'tag' => 'div' ],
		[ 'textColor' => '#333333', 'verticalAlignment' => 'top' ],
	);

	expect( $output )->toContain( 'color: #333333' );
} );

test( 'group block renders with justify content', function (): void {
	$block  = new GroupBlock();
	$output = $block->render(
		[ 'tag' => 'div', 'flexDirection' => 'row', 'justifyContent' => 'center' ],
		[ 'verticalAlignment' => 'top' ],
	);

	expect( $output )->toContain( 'justify-content: center' );
} );

test( 'group block renders with gap', function (): void {
	$block  = new GroupBlock();
	$output = $block->render(
		[ 'tag' => 'div' ],
		[ 'gap' => '1rem', 'verticalAlignment' => 'top' ],
	);

	expect( $output )->toContain( 'gap: 1rem' );
} );

test( 'group block supports color text', function (): void {
	$block = new GroupBlock();

	expect( $block->supportsFeature( 'color.text' ) )->toBeTrue();
} );

test( 'group block supports spacing block gap', function (): void {
	$block = new GroupBlock();

	expect( $block->supportsFeature( 'spacing.blockGap' ) )->toBeTrue();
} );

test( 'group block row variation includes justify content', function (): void {
	$block      = new GroupBlock();
	$variations = $block->getVariations();
	$row        = array_values( array_filter( $variations, fn ( $v ) => 'row' === $v['name'] ) )[0];

	expect( $row['attributes'] )->toHaveKey( 'justifyContent' );
	expect( $row['attributes']['justifyContent'] )->toBe( 'flex-start' );
} );

test( 'group block renders with header tag', function (): void {
	$block  = new GroupBlock();
	$output = $block->render( [ 'tag' => 'header' ], [ 'verticalAlignment' => 'top' ] );

	expect( $output )->toContain( '<header' );
	expect( $output )->toContain( '</header>' );
} );

test( 'group block renders with footer tag', function (): void {
	$block  = new GroupBlock();
	$output = $block->render( [ 'tag' => 'footer' ], [ 'verticalAlignment' => 'top' ] );

	expect( $output )->toContain( '<footer' );
	expect( $output )->toContain( '</footer>' );
} );

test( 'group block has keywords', function (): void {
	$block = new GroupBlock();

	expect( $block->getKeywords() )->toContain( 'container' );
	expect( $block->getKeywords() )->toContain( 'group' );
} );

test( 'group block supports shadow min height and all background sub-keys', function (): void {
	$block = new GroupBlock();

	expect( $block->supportsFeature( 'shadow' ) )->toBeTrue();
	expect( $block->supportsFeature( 'dimensions.minHeight' ) )->toBeTrue();
	expect( $block->supportsFeature( 'dimensions.aspectRatio' ) )->toBeFalse();
	expect( $block->supportsFeature( 'background.backgroundImage' ) )->toBeTrue();
	expect( $block->supportsFeature( 'background.backgroundSize' ) )->toBeTrue();
	expect( $block->supportsFeature( 'background.backgroundPosition' ) )->toBeTrue();
	expect( $block->supportsFeature( 'background.backgroundGradient' ) )->toBeTrue();
} );

test( 'group block active style supports include shadow dimensions and background', function (): void {
	$block  = new GroupBlock();
	$active = $block->getActiveStyleSupports();

	expect( $active )->toContain( 'shadow' );
	expect( $active )->toContain( 'dimensions.minHeight' );
	expect( $active )->not->toContain( 'dimensions.aspectRatio' );
	expect( $active )->toContain( 'background.backgroundImage' );
	expect( $active )->toContain( 'background.backgroundSize' );
	expect( $active )->toContain( 'background.backgroundPosition' );
	expect( $active )->toContain( 'background.backgroundGradient' );
} );

test( 'group block renders with fill height', function (): void {
	$block  = new GroupBlock();
	$output = $block->render(
		[ 'tag' => 'div' ],
		[ 'verticalAlignment' => 'top', 'fillHeight' => true ],
	);

	expect( $output )->toContain( 'height: 100%' );
} );

test( 'group block renders with useFlexbox and innerSpacing large', function (): void {
	$block  = new GroupBlock();
	$output = $block->render(
		[ 'tag' => 'div' ],
		[ 'verticalAlignment' => 'top', 'useFlexbox' => true, 'innerSpacing' => 'large' ],
	);

	expect( $output )->toContain( 'gap: 2rem' );
} );

test( 'group block renders with useFlexbox and innerSpacing none', function (): void {
	$block  = new GroupBlock();
	$output = $block->render(
		[ 'tag' => 'div' ],
		[ 'verticalAlignment' => 'top', 'useFlexbox' => true, 'innerSpacing' => 'none' ],
	);

	expect( $output )->toContain( 'gap: 0' );
} );
