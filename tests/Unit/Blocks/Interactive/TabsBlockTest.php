<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Interactive\TabsBlock;

test( 'tabs block has correct type and category', function (): void {
	$block = new TabsBlock();

	expect( $block->getType() )->toBe( 'tabs' );
	expect( $block->getCategory() )->toBe( 'interactive' );
} );

test( 'tabs block is public and tab-panel is its only allowed child', function (): void {
	$block = new TabsBlock();

	expect( $block->isPublic() )->toBeTrue();
	expect( $block->getAllowedChildren() )->toBe( [ 'tab-panel' ] );
} );

test( 'tabs block supports inner blocks with horizontal orientation', function (): void {
	$block = new TabsBlock();

	expect( $block->supportsInnerBlocks() )->toBeTrue();
	expect( $block->getInnerBlocksOrientation() )->toBe( 'horizontal' );
} );

test( 'tabs block content schema has tab position and remember active fields', function (): void {
	$block  = new TabsBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'tabPosition' );
	expect( $schema )->toHaveKey( 'rememberActive' );
	expect( $schema['tabPosition']['type'] )->toBe( 'select' );
	expect( $schema['rememberActive']['type'] )->toBe( 'toggle' );
} );

test( 'tabs block tab position options include all four positions', function (): void {
	$block  = new TabsBlock();
	$schema = $block->getContentSchema();

	expect( $schema['tabPosition']['options'] )->toHaveKey( 'top' );
	expect( $schema['tabPosition']['options'] )->toHaveKey( 'bottom' );
	expect( $schema['tabPosition']['options'] )->toHaveKey( 'left' );
	expect( $schema['tabPosition']['options'] )->toHaveKey( 'right' );
} );

test( 'tabs block style schema has tab style size and color fields', function (): void {
	$block  = new TabsBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'tabStyle' );
	expect( $schema )->toHaveKey( 'tabSize' );
	expect( $schema )->toHaveKey( 'fullWidth' );
	expect( $schema )->toHaveKey( 'fadeTransition' );
	expect( $schema )->toHaveKey( 'tabTextColor' );
	expect( $schema )->toHaveKey( 'activeTabColor' );
	expect( $schema )->toHaveKey( 'contentBackground' );
} );

test( 'tabs block tab style options include daisy ui styles', function (): void {
	$block  = new TabsBlock();
	$schema = $block->getStyleSchema();

	expect( $schema['tabStyle']['options'] )->toHaveKey( 'default' );
	expect( $schema['tabStyle']['options'] )->toHaveKey( 'boxed' );
	expect( $schema['tabStyle']['options'] )->toHaveKey( 'bordered' );
	expect( $schema['tabStyle']['options'] )->toHaveKey( 'lifted' );
} );

test( 'tabs block defaults to top position and default style', function (): void {
	$block           = new TabsBlock();
	$contentDefaults = $block->getDefaultContent();
	$styleDefaults   = $block->getDefaultStyles();

	expect( $contentDefaults['tabPosition'] )->toBe( 'top' );
	expect( $styleDefaults['tabStyle'] )->toBe( 'default' );
	expect( $styleDefaults['tabSize'] )->toBe( 'md' );
	expect( $styleDefaults['fullWidth'] )->toBeFalse();
	expect( $styleDefaults['fadeTransition'] )->toBeTrue();
	expect( $contentDefaults['rememberActive'] )->toBeFalse();
} );

test( 'tabs block has default inner blocks with two tab panels', function (): void {
	$block        = new TabsBlock();
	$defaultInner = $block->getDefaultInnerBlocks();

	expect( $defaultInner )->toHaveCount( 2 );
	expect( $defaultInner[0]['type'] )->toBe( 'tab-panel' );
	expect( $defaultInner[1]['type'] )->toBe( 'tab-panel' );
} );

test( 'tabs block has transforms to accordion', function (): void {
	$block      = new TabsBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toHaveKey( 'accordion' );
	expect( $transforms['accordion'] )->toBe( [ 'tab-panel' => 'accordion-section' ] );
} );

test( 'tabs block has keywords', function (): void {
	$block = new TabsBlock();

	expect( $block->getKeywords() )->toContain( 'tabs' );
	expect( $block->getKeywords() )->toContain( 'tabbed' );
} );

test( 'tabs block supports alignment and anchor', function (): void {
	$block = new TabsBlock();

	expect( $block->supportsFeature( 'anchor' ) )->toBeTrue();
	expect( $block->supportsFeature( 'className' ) )->toBeTrue();
	expect( $block->supportsFeature( 'align' ) )->toBeTrue();
} );

test( 'tabs block has custom toolbar', function (): void {
	$block = new TabsBlock();

	expect( $block->hasCustomToolbar() )->toBeTrue();
} );

test( 'tabs block renders with tablist role', function (): void {
	$block  = new TabsBlock();
	$output = $block->render(
		[ 'tabPosition' => 'top', 'rememberActive' => false ],
		[ 'tabStyle' => 'default', 'tabSize' => 'md', 'fullWidth' => false, 'fadeTransition' => true ],
	);

	expect( $output )->toContain( 've-block-tabs' );
	expect( $output )->toContain( 'role="tablist"' );
} );

test( 'tabs block renders vertical layout for left position', function (): void {
	$block  = new TabsBlock();
	$output = $block->render(
		[ 'tabPosition' => 'left', 'rememberActive' => false ],
		[ 'tabStyle' => 'default', 'tabSize' => 'md', 'fullWidth' => false, 'fadeTransition' => true ],
	);

	expect( $output )->toContain( 've-tabs-vertical' );
	expect( $output )->toContain( 've-tabs-left' );
	expect( $output )->toContain( 'display: flex' );
} );

test( 'tabs block to array includes default inner blocks', function (): void {
	$block = new TabsBlock();
	$array = $block->toArray();

	expect( $array['defaultInnerBlocks'] )->toHaveCount( 2 );
	expect( $array['defaultInnerBlocks'][0]['type'] )->toBe( 'tab-panel' );
} );
