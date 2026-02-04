<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;

beforeEach( function (): void {
	$this->registry = new BlockRegistry();
} );

test( 'it can register a block type', function (): void {
	$this->registry->register( 'custom-block', [
		'name'        => 'Custom Block',
		'description' => 'A custom block for testing',
		'category'    => 'text',
		'icon'        => 'fas.cube',
	] );

	expect( $this->registry->has( 'custom-block' ) )->toBeTrue();
} );

test( 'it can retrieve a registered block', function (): void {
	$config = [
		'name'        => 'Custom Block',
		'description' => 'A custom block for testing',
		'category'    => 'text',
		'icon'        => 'fas.cube',
	];

	$this->registry->register( 'custom-block', $config );

	$block = $this->registry->get( 'custom-block' );

	expect( $block )->not->toBeNull()
		->and( $block['name'] )->toBe( 'Custom Block' )
		->and( $block['category'] )->toBe( 'text' );
} );

test( 'it returns null for non-existent block', function (): void {
	expect( $this->registry->get( 'non-existent' ) )->toBeNull();
} );

test( 'it can unregister a block', function (): void {
	$this->registry->register( 'temp-block', [
		'name' => 'Temporary Block',
	] );

	expect( $this->registry->has( 'temp-block' ) )->toBeTrue();

	$this->registry->unregister( 'temp-block' );

	expect( $this->registry->has( 'temp-block' ) )->toBeFalse();
} );

test( 'it can get all registered blocks', function (): void {
	$this->registry->register( 'block-one', [ 'name' => 'Block One' ] );
	$this->registry->register( 'block-two', [ 'name' => 'Block Two' ] );

	$all = $this->registry->all();

	expect( $all )->toHaveCount( 2 )
		->and( $all->has( 'block-one' ) )->toBeTrue()
		->and( $all->has( 'block-two' ) )->toBeTrue();
} );

test( 'it can get blocks by category', function (): void {
	$this->registry->register( 'block-text', [
		'name'     => 'Text Block',
		'category' => 'text',
	] );

	$this->registry->register( 'block-media', [
		'name'     => 'Media Block',
		'category' => 'media',
	] );

	$textBlocks = $this->registry->getByCategory( 'text' );

	expect( $textBlocks )->toHaveCount( 1 )
		->and( $textBlocks->has( 'block-text' ) )->toBeTrue();
} );

test( 'it can get blocks grouped by category', function (): void {
	$this->registry->register( 'block-text-1', [
		'name'     => 'Text Block 1',
		'category' => 'text',
	] );

	$this->registry->register( 'block-text-2', [
		'name'     => 'Text Block 2',
		'category' => 'text',
	] );

	$this->registry->register( 'block-media', [
		'name'     => 'Media Block',
		'category' => 'media',
	] );

	$grouped = $this->registry->getGroupedByCategory();

	expect( $grouped )->toHaveCount( 2 )
		->and( $grouped->has( 'text' ) )->toBeTrue()
		->and( $grouped->has( 'media' ) )->toBeTrue()
		->and( $grouped['text']['blocks'] )->toHaveCount( 2 );
} );

test( 'it registers default blocks', function (): void {
	$this->registry->registerDefaults();

	expect( $this->registry->has( 'heading' ) )->toBeTrue()
		->and( $this->registry->has( 'text' ) )->toBeTrue()
		->and( $this->registry->has( 'image' ) )->toBeTrue()
		->and( $this->registry->has( 'button' ) )->toBeTrue();
} );

test( 'register returns self for chaining', function (): void {
	$result = $this->registry->register( 'chain-block', [ 'name' => 'Chain Block' ] );

	expect( $result )->toBeInstanceOf( BlockRegistry::class );
} );

// =========================================
// Validation Tests
// =========================================

test( 'it throws exception for empty block type', function (): void {
	$this->registry->register( '', [ 'name' => 'Empty Type' ] );
} )->throws( InvalidArgumentException::class, 'Block type cannot be empty.' );

test( 'it throws exception for whitespace-only block type', function (): void {
	$this->registry->register( '   ', [ 'name' => 'Whitespace Type' ] );
} )->throws( InvalidArgumentException::class, 'Block type cannot be empty.' );

test( 'it throws exception for block type with invalid characters', function (): void {
	$this->registry->register( 'my block!', [ 'name' => 'Invalid Type' ] );
} )->throws( InvalidArgumentException::class, 'contains invalid characters' );

test( 'it throws exception for unregistered category', function (): void {
	$this->registry->register( 'my-block', [
		'name'     => 'My Block',
		'category' => 'nonexistent-category',
	] );
} )->throws( InvalidArgumentException::class, 'is not registered' );

test( 'it allows block type with hyphens and underscores', function (): void {
	$this->registry->register( 'my-custom_block', [ 'name' => 'Hyphen Underscore Block' ] );

	expect( $this->registry->has( 'my-custom_block' ) )->toBeTrue();
} );

test( 'it allows registration with a custom registered category', function (): void {
	$this->registry->registerCategory( 'custom', [
		'name' => 'Custom',
		'icon' => 'fas.star',
	] );

	$this->registry->register( 'custom-block', [
		'name'     => 'Custom Block',
		'category' => 'custom',
	] );

	expect( $this->registry->has( 'custom-block' ) )->toBeTrue()
		->and( $this->registry->get( 'custom-block' )['category'] )->toBe( 'custom' );
} );

// =========================================
// Default Config Fields Tests
// =========================================

test( 'registered block includes all default config fields', function (): void {
	$this->registry->register( 'test-block', [
		'name' => 'Test Block',
	] );

	$block = $this->registry->get( 'test-block' );

	expect( $block )->toHaveKeys( [
		'name',
		'description',
		'icon',
		'category',
		'keywords',
		'content_schema',
		'settings_schema',
		'component',
		'editor_component',
		'supports',
		'toolbar',
		'example',
	] )
		->and( $block['description'] )->toBe( '' )
		->and( $block['keywords'] )->toBe( [] )
		->and( $block['component'] )->toBeNull()
		->and( $block['editor_component'] )->toBeNull()
		->and( $block['example'] )->toBe( [] );
} );

test( 'registered block config values override defaults', function (): void {
	$this->registry->register( 'test-block', [
		'name'             => 'Test Block',
		'description'      => 'A test block',
		'keywords'         => [ 'test', 'demo' ],
		'component'        => 'visual-editor::blocks.test',
		'editor_component' => 'TestBlockEditor',
		'example'          => [ 'text' => 'Hello' ],
	] );

	$block = $this->registry->get( 'test-block' );

	expect( $block['description'] )->toBe( 'A test block' )
		->and( $block['keywords'] )->toBe( [ 'test', 'demo' ] )
		->and( $block['component'] )->toBe( 'visual-editor::blocks.test' )
		->and( $block['editor_component'] )->toBe( 'TestBlockEditor' )
		->and( $block['example'] )->toBe( [ 'text' => 'Hello' ] );
} );

// =========================================
// Categories Tests
// =========================================

test( 'it includes the embed category by default', function (): void {
	$categories = $this->registry->getCategories();

	expect( $categories )->toHaveKey( 'embed' )
		->and( $categories['embed']['name'] )->toBe( 'Embed' )
		->and( $categories['embed']['icon'] )->toBe( 'fas.code' );
} );

test( 'it includes all default categories', function (): void {
	$categories = $this->registry->getCategories();

	expect( $categories )->toHaveKeys( [ 'text', 'media', 'interactive', 'layout', 'embed', 'dynamic' ] );
} );

test( 'it can register a custom category', function (): void {
	$this->registry->registerCategory( 'widgets', [
		'name' => 'Widgets',
		'icon' => 'fas.puzzle-piece',
	] );

	$categories = $this->registry->getCategories();

	expect( $categories )->toHaveKey( 'widgets' )
		->and( $categories['widgets']['name'] )->toBe( 'Widgets' );
} );

// =========================================
// Toolbar Configuration Tests
// =========================================

test( 'registered block includes toolbar field defaulting to empty array', function (): void {
	$this->registry->register( 'no-toolbar-block', [
		'name' => 'No Toolbar Block',
	] );

	$block = $this->registry->get( 'no-toolbar-block' );

	expect( $block['toolbar'] )->toBe( [] );
} );

test( 'registered block preserves toolbar configuration', function (): void {
	$this->registry->register( 'toolbar-block', [
		'name'    => 'Toolbar Block',
		'toolbar' => [ 'align', 'richtext' ],
	] );

	$block = $this->registry->get( 'toolbar-block' );

	expect( $block['toolbar'] )->toBe( [ 'align', 'richtext' ] );
} );

test( 'default heading block has richtext align and heading_level toolbar tools', function (): void {
	$this->registry->registerDefaults();

	$heading = $this->registry->get( 'heading' );

	expect( $heading['toolbar'] )->toContain( 'align' )
		->and( $heading['toolbar'] )->toContain( 'richtext' )
		->and( $heading['toolbar'] )->toContain( 'heading_level' );
} );

test( 'default text block has richtext and align toolbar tools', function (): void {
	$this->registry->registerDefaults();

	$text = $this->registry->get( 'text' );

	expect( $text['toolbar'] )->toContain( 'align' )
		->and( $text['toolbar'] )->toContain( 'richtext' );
} );

test( 'default heading block uses richtext content type', function (): void {
	$this->registry->registerDefaults();

	$heading = $this->registry->get( 'heading' );

	expect( $heading['content_schema']['text']['type'] )->toBe( 'richtext' );
} );

test( 'default image block has align toolbar tool', function (): void {
	$this->registry->registerDefaults();

	$image = $this->registry->get( 'image' );

	expect( $image['toolbar'] )->toContain( 'align' );
} );

test( 'default divider block has empty toolbar', function (): void {
	$this->registry->registerDefaults();

	$divider = $this->registry->get( 'divider' );

	expect( $divider['toolbar'] )->toBe( [] );
} );

// =========================================
// Supports Configuration Tests
// =========================================

test( 'registered block includes supports field defaulting to sizing', function (): void {
	$this->registry->register( 'no-supports-block', [
		'name' => 'No Supports Block',
	] );

	$block = $this->registry->get( 'no-supports-block' );

	expect( $block['supports'] )->toBe( [ 'sizing' ] );
} );

test( 'registered block preserves supports configuration', function (): void {
	$this->registry->register( 'supports-block', [
		'name'     => 'Supports Block',
		'supports' => [ 'sizing', 'typography', 'colors', 'borders' ],
	] );

	$block = $this->registry->get( 'supports-block' );

	expect( $block['supports'] )->toBe( [ 'sizing', 'typography', 'colors', 'borders' ] );
} );

test( 'default heading block supports sizing typography and colors', function (): void {
	$this->registry->registerDefaults();

	$heading = $this->registry->get( 'heading' );

	expect( $heading['supports'] )->toContain( 'sizing' )
		->and( $heading['supports'] )->toContain( 'typography' )
		->and( $heading['supports'] )->toContain( 'colors' );
} );

test( 'default text block supports sizing typography and colors', function (): void {
	$this->registry->registerDefaults();

	$text = $this->registry->get( 'text' );

	expect( $text['supports'] )->toContain( 'sizing' )
		->and( $text['supports'] )->toContain( 'typography' )
		->and( $text['supports'] )->toContain( 'colors' );
} );

test( 'default image block supports sizing and borders', function (): void {
	$this->registry->registerDefaults();

	$image = $this->registry->get( 'image' );

	expect( $image['supports'] )->toContain( 'sizing' )
		->and( $image['supports'] )->toContain( 'borders' )
		->and( $image['supports'] )->not->toContain( 'typography' );
} );

test( 'default button block supports sizing typography colors and borders', function (): void {
	$this->registry->registerDefaults();

	$button = $this->registry->get( 'button' );

	expect( $button['supports'] )->toContain( 'sizing' )
		->and( $button['supports'] )->toContain( 'typography' )
		->and( $button['supports'] )->toContain( 'colors' )
		->and( $button['supports'] )->toContain( 'borders' );
} );

test( 'default spacer block supports only sizing', function (): void {
	$this->registry->registerDefaults();

	$spacer = $this->registry->get( 'spacer' );

	expect( $spacer['supports'] )->toBe( [ 'sizing' ] );
} );

test( 'default divider block supports sizing colors and borders', function (): void {
	$this->registry->registerDefaults();

	$divider = $this->registry->get( 'divider' );

	expect( $divider['supports'] )->toContain( 'sizing' )
		->and( $divider['supports'] )->toContain( 'colors' )
		->and( $divider['supports'] )->toContain( 'borders' );
} );

// =========================================
// Text Block Registration Tests
// =========================================

test( 'default heading block has anchor setting in settings_schema', function (): void {
	$this->registry->registerDefaults();

	$heading = $this->registry->get( 'heading' );

	expect( $heading['settings_schema'] )->toHaveKey( 'anchor' )
		->and( $heading['settings_schema']['anchor']['type'] )->toBe( 'text' );
} );

test( 'default text block has drop_cap setting in settings_schema', function (): void {
	$this->registry->registerDefaults();

	$text = $this->registry->get( 'text' );

	expect( $text['settings_schema'] )->toHaveKey( 'drop_cap' )
		->and( $text['settings_schema']['drop_cap']['type'] )->toBe( 'toggle' )
		->and( $text['settings_schema']['drop_cap']['default'] )->toBeFalse();
} );

test( 'default list block uses richtext text field', function (): void {
	$this->registry->registerDefaults();

	$list = $this->registry->get( 'list' );

	expect( $list['content_schema'] )->toHaveKey( 'text' )
		->and( $list['content_schema']['text']['type'] )->toBe( 'richtext' );
} );

test( 'default list block has style select field', function (): void {
	$this->registry->registerDefaults();

	$list = $this->registry->get( 'list' );

	expect( $list['content_schema'] )->toHaveKey( 'style' )
		->and( $list['content_schema']['style']['type'] )->toBe( 'select' )
		->and( $list['content_schema']['style']['options'] )->toBe( [ 'bullet', 'number' ] )
		->and( $list['content_schema']['style']['default'] )->toBe( 'bullet' );
} );

test( 'default list block has richtext align and list_style toolbar tools', function (): void {
	$this->registry->registerDefaults();

	$list = $this->registry->get( 'list' );

	expect( $list['toolbar'] )->toContain( 'align' )
		->and( $list['toolbar'] )->toContain( 'richtext' )
		->and( $list['toolbar'] )->toContain( 'list_style' );
} );

test( 'default list block supports sizing typography and colors', function (): void {
	$this->registry->registerDefaults();

	$list = $this->registry->get( 'list' );

	expect( $list['supports'] )->toContain( 'sizing' )
		->and( $list['supports'] )->toContain( 'typography' )
		->and( $list['supports'] )->toContain( 'colors' );
} );

// =========================================
// Layout Block Registration Tests
// =========================================

test( 'default columns block is registered in layout category', function (): void {
	$this->registry->registerDefaults();

	$columns = $this->registry->get( 'columns' );

	expect( $columns )->not->toBeNull()
		->and( $columns['name'] )->toBe( 'Columns' )
		->and( $columns['category'] )->toBe( 'layout' )
		->and( $columns['icon'] )->toBe( 'fas.columns' );
} );

test( 'default columns block has preset setting with layout options', function (): void {
	$this->registry->registerDefaults();

	$columns = $this->registry->get( 'columns' );

	expect( $columns['settings_schema'] )->toHaveKey( 'preset' )
		->and( $columns['settings_schema']['preset']['type'] )->toBe( 'select' )
		->and( $columns['settings_schema']['preset']['default'] )->toBe( '50-50' )
		->and( $columns['settings_schema']['preset']['options'] )->toContain( '50-50' )
		->and( $columns['settings_schema']['preset']['options'] )->toContain( '33-33-33' )
		->and( $columns['settings_schema']['preset']['options'] )->toContain( '66-33' )
		->and( $columns['settings_schema']['preset']['options'] )->toContain( '33-66' );
} );

test( 'default columns block has gap setting', function (): void {
	$this->registry->registerDefaults();

	$columns = $this->registry->get( 'columns' );

	expect( $columns['settings_schema'] )->toHaveKey( 'gap' )
		->and( $columns['settings_schema']['gap']['type'] )->toBe( 'select' )
		->and( $columns['settings_schema']['gap']['default'] )->toBe( 'medium' )
		->and( $columns['settings_schema']['gap']['options'] )->toBe( [ 'none', 'small', 'medium', 'large' ] );
} );

test( 'default columns block has vertical alignment setting', function (): void {
	$this->registry->registerDefaults();

	$columns = $this->registry->get( 'columns' );

	expect( $columns['settings_schema'] )->toHaveKey( 'vertical_alignment' )
		->and( $columns['settings_schema']['vertical_alignment']['type'] )->toBe( 'select' )
		->and( $columns['settings_schema']['vertical_alignment']['default'] )->toBe( 'top' )
		->and( $columns['settings_schema']['vertical_alignment']['options'] )->toBe( [ 'top', 'center', 'bottom', 'stretch' ] );
} );

test( 'default columns block has stack on mobile toggle', function (): void {
	$this->registry->registerDefaults();

	$columns = $this->registry->get( 'columns' );

	expect( $columns['settings_schema'] )->toHaveKey( 'stack_on_mobile' )
		->and( $columns['settings_schema']['stack_on_mobile']['type'] )->toBe( 'toggle' )
		->and( $columns['settings_schema']['stack_on_mobile']['default'] )->toBeTrue();
} );

test( 'default columns block supports sizing colors and borders', function (): void {
	$this->registry->registerDefaults();

	$columns = $this->registry->get( 'columns' );

	expect( $columns['supports'] )->toContain( 'sizing' )
		->and( $columns['supports'] )->toContain( 'colors' )
		->and( $columns['supports'] )->toContain( 'borders' );
} );

test( 'default columns block has responsive column count settings', function (): void {
	$this->registry->registerDefaults();

	$columns = $this->registry->get( 'columns' );

	expect( $columns['settings_schema'] )->toHaveKey( 'columns' )
		->and( $columns['settings_schema'] )->toHaveKey( 'columns_sm' )
		->and( $columns['settings_schema'] )->toHaveKey( 'columns_md' )
		->and( $columns['settings_schema'] )->toHaveKey( 'columns_lg' )
		->and( $columns['settings_schema'] )->toHaveKey( 'columns_xl' )
		->and( $columns['settings_schema']['columns']['options'] )->toHaveCount( 12 )
		->and( $columns['settings_schema']['columns']['default'] )->toBe( '' )
		->and( $columns['settings_schema']['columns_sm']['default'] )->toBe( '' )
		->and( $columns['settings_schema']['columns_xl']['options'] )->toContain( '12' );
} );

// =========================================
// Column Block Registration Tests
// =========================================

test( 'default column block is registered in layout category', function (): void {
	$this->registry->registerDefaults();

	$column = $this->registry->get( 'column' );

	expect( $column )->not->toBeNull()
		->and( $column['name'] )->toBe( 'Column' )
		->and( $column['category'] )->toBe( 'layout' )
		->and( $column['icon'] )->toBe( 'fas.table-columns' );
} );

test( 'default column block has width setting', function (): void {
	$this->registry->registerDefaults();

	$column = $this->registry->get( 'column' );

	expect( $column['settings_schema'] )->toHaveKey( 'width' )
		->and( $column['settings_schema']['width']['type'] )->toBe( 'text' )
		->and( $column['settings_schema']['width']['default'] )->toBe( '' );
} );

test( 'default column block has flex alignment settings', function (): void {
	$this->registry->registerDefaults();

	$column = $this->registry->get( 'column' );

	expect( $column['settings_schema'] )->toHaveKey( 'flex_direction' )
		->and( $column['settings_schema'] )->toHaveKey( 'align_items' )
		->and( $column['settings_schema'] )->toHaveKey( 'justify_content' )
		->and( $column['settings_schema']['flex_direction']['default'] )->toBe( 'column' )
		->and( $column['settings_schema']['align_items']['default'] )->toBe( 'stretch' )
		->and( $column['settings_schema']['justify_content']['default'] )->toBe( 'start' );
} );

test( 'default column block has inner_blocks content schema', function (): void {
	$this->registry->registerDefaults();

	$column = $this->registry->get( 'column' );

	expect( $column['content_schema'] )->toHaveKey( 'inner_blocks' )
		->and( $column['content_schema']['inner_blocks']['type'] )->toBe( 'repeater' );
} );

test( 'default column block supports sizing colors and borders', function (): void {
	$this->registry->registerDefaults();

	$column = $this->registry->get( 'column' );

	expect( $column['supports'] )->toBe( [ 'sizing', 'colors', 'borders' ] );
} );

test( 'default group block is registered in layout category', function (): void {
	$this->registry->registerDefaults();

	$group = $this->registry->get( 'group' );

	expect( $group )->not->toBeNull()
		->and( $group['name'] )->toBe( 'Group' )
		->and( $group['category'] )->toBe( 'layout' )
		->and( $group['icon'] )->toBe( 'fas.object-group' );
} );


test( 'default group block has flex layout settings', function (): void {
	$this->registry->registerDefaults();

	$group = $this->registry->get( 'group' );

	expect( $group['settings_schema'] )->toHaveKey( 'constrained' )
		->and( $group['settings_schema']['constrained']['default'] )->toBeFalse()
		->and( $group['settings_schema'] )->toHaveKey( 'flex_direction' )
		->and( $group['settings_schema']['flex_direction']['default'] )->toBe( 'column' )
		->and( $group['settings_schema'] )->toHaveKey( 'flex_wrap' )
		->and( $group['settings_schema']['flex_wrap']['default'] )->toBe( 'nowrap' )
		->and( $group['settings_schema'] )->toHaveKey( 'align_items' )
		->and( $group['settings_schema']['align_items']['default'] )->toBe( 'stretch' )
		->and( $group['settings_schema'] )->toHaveKey( 'justify_content' )
		->and( $group['settings_schema']['justify_content']['default'] )->toBe( 'start' );
} );

test( 'default group block supports sizing colors and borders', function (): void {
	$this->registry->registerDefaults();

	$group = $this->registry->get( 'group' );

	expect( $group['supports'] )->toContain( 'sizing' )
		->and( $group['supports'] )->toContain( 'colors' )
		->and( $group['supports'] )->toContain( 'borders' );
} );

test( 'default spacer block has height unit and responsive settings', function (): void {
	$this->registry->registerDefaults();

	$spacer = $this->registry->get( 'spacer' );

	expect( $spacer['settings_schema'] )->toHaveKey( 'height' )
		->and( $spacer['settings_schema']['height']['default'] )->toBe( '40' )
		->and( $spacer['settings_schema'] )->toHaveKey( 'unit' )
		->and( $spacer['settings_schema']['unit']['options'] )->toBe( [ 'px', 'rem' ] )
		->and( $spacer['settings_schema']['unit']['default'] )->toBe( 'px' )
		->and( $spacer['settings_schema'] )->toHaveKey( 'responsive' )
		->and( $spacer['settings_schema']['responsive']['type'] )->toBe( 'toggle' )
		->and( $spacer['settings_schema']['responsive']['default'] )->toBeFalse();
} );

test( 'default separator block is registered in layout category', function (): void {
	$this->registry->registerDefaults();

	$separator = $this->registry->get( 'separator' );

	expect( $separator )->not->toBeNull()
		->and( $separator['name'] )->toBe( 'Separator' )
		->and( $separator['category'] )->toBe( 'layout' )
		->and( $separator['icon'] )->toBe( 'fas.grip-lines' );
} );

test( 'default separator block has style color and width settings', function (): void {
	$this->registry->registerDefaults();

	$separator = $this->registry->get( 'separator' );

	expect( $separator['settings_schema'] )->toHaveKey( 'style' )
		->and( $separator['settings_schema']['style']['options'] )->toBe( [ 'solid', 'dashed', 'dotted', 'wide' ] )
		->and( $separator['settings_schema']['style']['default'] )->toBe( 'solid' )
		->and( $separator['settings_schema'] )->toHaveKey( 'color' )
		->and( $separator['settings_schema']['color']['type'] )->toBe( 'color' )
		->and( $separator['settings_schema'] )->toHaveKey( 'width' )
		->and( $separator['settings_schema']['width']['options'] )->toBe( [ 'full', 'wide', 'narrow', 'short' ] )
		->and( $separator['settings_schema']['width']['default'] )->toBe( 'full' );
} );

test( 'default separator block supports sizing and colors', function (): void {
	$this->registry->registerDefaults();

	$separator = $this->registry->get( 'separator' );

	expect( $separator['supports'] )->toContain( 'sizing' )
		->and( $separator['supports'] )->toContain( 'colors' );
} );

test( 'all layout blocks are registered after registerDefaults', function (): void {
	$this->registry->registerDefaults();

	expect( $this->registry->has( 'columns' ) )->toBeTrue()
		->and( $this->registry->has( 'group' ) )->toBeTrue()
		->and( $this->registry->has( 'divider' ) )->toBeTrue()
		->and( $this->registry->has( 'spacer' ) )->toBeTrue()
		->and( $this->registry->has( 'separator' ) )->toBeTrue();
} );

test( 'layout blocks appear in layout category when grouped', function (): void {
	$this->registry->registerDefaults();

	$grouped = $this->registry->getGroupedByCategory();

	expect( $grouped )->toHaveKey( 'layout' );

	$layoutBlocks = $grouped['layout']['blocks'];

	expect( $layoutBlocks->has( 'columns' ) )->toBeTrue()
		->and( $layoutBlocks->has( 'column' ) )->toBeTrue()
		->and( $layoutBlocks->has( 'group' ) )->toBeTrue()
		->and( $layoutBlocks->has( 'grid' ) )->toBeTrue()
		->and( $layoutBlocks->has( 'grid_item' ) )->toBeTrue()
		->and( $layoutBlocks->has( 'divider' ) )->toBeTrue()
		->and( $layoutBlocks->has( 'spacer' ) )->toBeTrue()
		->and( $layoutBlocks->has( 'separator' ) )->toBeTrue();
} );

// =========================================
// Grid Block Registration Tests
// =========================================

test( 'default grid block is registered in layout category', function (): void {
	$this->registry->registerDefaults();

	$grid = $this->registry->get( 'grid' );

	expect( $grid )->not->toBeNull()
		->and( $grid['name'] )->toBe( 'Grid' )
		->and( $grid['category'] )->toBe( 'layout' )
		->and( $grid['icon'] )->toBe( 'fas.table-cells' );
} );

test( 'default grid block has columns setting with 1-12 options', function (): void {
	$this->registry->registerDefaults();

	$grid = $this->registry->get( 'grid' );

	expect( $grid['settings_schema'] )->toHaveKey( 'columns' )
		->and( $grid['settings_schema']['columns']['type'] )->toBe( 'select' )
		->and( $grid['settings_schema']['columns']['default'] )->toBe( '3' )
		->and( $grid['settings_schema']['columns']['options'] )->toHaveCount( 12 )
		->and( $grid['settings_schema']['columns']['options'] )->toContain( '1' )
		->and( $grid['settings_schema']['columns']['options'] )->toContain( '12' );
} );

test( 'default grid block has responsive column settings for sm md lg xl', function (): void {
	$this->registry->registerDefaults();

	$grid = $this->registry->get( 'grid' );

	expect( $grid['settings_schema'] )->toHaveKey( 'columns_sm' )
		->and( $grid['settings_schema'] )->toHaveKey( 'columns_md' )
		->and( $grid['settings_schema'] )->toHaveKey( 'columns_lg' )
		->and( $grid['settings_schema'] )->toHaveKey( 'columns_xl' )
		->and( $grid['settings_schema']['columns_sm']['default'] )->toBe( '' )
		->and( $grid['settings_schema']['columns_xl']['options'] )->toContain( '12' );
} );

test( 'default grid block has gap setting', function (): void {
	$this->registry->registerDefaults();

	$grid = $this->registry->get( 'grid' );

	expect( $grid['settings_schema'] )->toHaveKey( 'gap' )
		->and( $grid['settings_schema']['gap']['default'] )->toBe( 'medium' )
		->and( $grid['settings_schema']['gap']['options'] )->toBe( [ 'none', 'small', 'medium', 'large' ] );
} );

test( 'default grid block has directional gap settings', function (): void {
	$this->registry->registerDefaults();

	$grid = $this->registry->get( 'grid' );

	expect( $grid['settings_schema'] )->toHaveKey( 'gap_x' )
		->and( $grid['settings_schema'] )->toHaveKey( 'gap_y' )
		->and( $grid['settings_schema']['gap_x']['default'] )->toBe( '' )
		->and( $grid['settings_schema']['gap_y']['default'] )->toBe( '' )
		->and( $grid['settings_schema']['gap_x']['options'] )->toContain( 'large' );
} );

test( 'default grid block supports sizing colors and borders', function (): void {
	$this->registry->registerDefaults();

	$grid = $this->registry->get( 'grid' );

	expect( $grid['supports'] )->toBe( [ 'sizing', 'colors', 'borders' ] );
} );

// =========================================
// Grid Item Block Registration Tests
// =========================================

test( 'default grid_item block is registered in layout category', function (): void {
	$this->registry->registerDefaults();

	$gridItem = $this->registry->get( 'grid_item' );

	expect( $gridItem )->not->toBeNull()
		->and( $gridItem['name'] )->toBe( 'Grid Item' )
		->and( $gridItem['category'] )->toBe( 'layout' )
		->and( $gridItem['icon'] )->toBe( 'fas.table-cells-large' );
} );

test( 'default grid_item block has col_span setting with 1-12 options', function (): void {
	$this->registry->registerDefaults();

	$gridItem = $this->registry->get( 'grid_item' );

	expect( $gridItem['settings_schema'] )->toHaveKey( 'col_span' )
		->and( $gridItem['settings_schema']['col_span']['default'] )->toBe( '1' )
		->and( $gridItem['settings_schema']['col_span']['options'] )->toHaveCount( 12 );
} );

test( 'default grid_item block has responsive col_span settings', function (): void {
	$this->registry->registerDefaults();

	$gridItem = $this->registry->get( 'grid_item' );

	expect( $gridItem['settings_schema'] )->toHaveKey( 'col_span_sm' )
		->and( $gridItem['settings_schema'] )->toHaveKey( 'col_span_md' )
		->and( $gridItem['settings_schema'] )->toHaveKey( 'col_span_lg' )
		->and( $gridItem['settings_schema'] )->toHaveKey( 'col_span_xl' )
		->and( $gridItem['settings_schema']['col_span_sm']['default'] )->toBe( '' );
} );

test( 'default grid_item block has row_span setting with 1-12 options', function (): void {
	$this->registry->registerDefaults();

	$gridItem = $this->registry->get( 'grid_item' );

	expect( $gridItem['settings_schema'] )->toHaveKey( 'row_span' )
		->and( $gridItem['settings_schema']['row_span']['default'] )->toBe( '1' )
		->and( $gridItem['settings_schema']['row_span']['options'] )->toHaveCount( 12 );
} );

test( 'default grid_item block has responsive row_span settings', function (): void {
	$this->registry->registerDefaults();

	$gridItem = $this->registry->get( 'grid_item' );

	expect( $gridItem['settings_schema'] )->toHaveKey( 'row_span_sm' )
		->and( $gridItem['settings_schema'] )->toHaveKey( 'row_span_md' )
		->and( $gridItem['settings_schema'] )->toHaveKey( 'row_span_lg' )
		->and( $gridItem['settings_schema'] )->toHaveKey( 'row_span_xl' );
} );

test( 'default grid_item block has flex alignment settings', function (): void {
	$this->registry->registerDefaults();

	$gridItem = $this->registry->get( 'grid_item' );

	expect( $gridItem['settings_schema'] )->toHaveKey( 'flex_direction' )
		->and( $gridItem['settings_schema'] )->toHaveKey( 'align_items' )
		->and( $gridItem['settings_schema'] )->toHaveKey( 'justify_content' )
		->and( $gridItem['settings_schema']['flex_direction']['default'] )->toBe( 'column' )
		->and( $gridItem['settings_schema']['align_items']['default'] )->toBe( 'stretch' )
		->and( $gridItem['settings_schema']['justify_content']['default'] )->toBe( 'start' );
} );

test( 'default grid_item block supports sizing colors and borders', function (): void {
	$this->registry->registerDefaults();

	$gridItem = $this->registry->get( 'grid_item' );

	expect( $gridItem['supports'] )->toBe( [ 'sizing', 'colors', 'borders' ] );
} );

// =========================================
// Group Block Flex Alignment Tests
// =========================================

test( 'default group block has flex alignment settings', function (): void {
	$this->registry->registerDefaults();

	$group = $this->registry->get( 'group' );

	expect( $group['settings_schema'] )->toHaveKey( 'flex_direction' )
		->and( $group['settings_schema'] )->toHaveKey( 'align_items' )
		->and( $group['settings_schema'] )->toHaveKey( 'justify_content' )
		->and( $group['settings_schema']['flex_direction']['options'] )->toBe( [ 'column', 'row' ] )
		->and( $group['settings_schema']['align_items']['options'] )->toBe( [ 'stretch', 'start', 'center', 'end' ] )
		->and( $group['settings_schema']['justify_content']['options'] )->toBe( [ 'start', 'center', 'end', 'between', 'around', 'evenly' ] );
} );

// =========================================
// Block Variations Tests
// =========================================

test( 'it can register a variation for a block', function (): void {
	$this->registry->register( 'test-block', [
		'name'     => 'Test Block',
		'category' => 'text',
	] );

	$this->registry->registerVariation( 'test-block', 'variation-one', [
		'title'       => 'Variation One',
		'description' => 'First variation',
		'icon'        => 'fas.star',
		'attributes'  => [
			'settings' => [
				'color' => 'blue',
			],
		],
	] );

	expect( $this->registry->hasVariations( 'test-block' ) )->toBeTrue();
} );

test( 'it can get all variations for a block', function (): void {
	$this->registry->register( 'test-block', [
		'name'     => 'Test Block',
		'category' => 'text',
	] );

	$this->registry->registerVariation( 'test-block', 'var-one', [
		'title' => 'Variation One',
	] );

	$this->registry->registerVariation( 'test-block', 'var-two', [
		'title' => 'Variation Two',
	] );

	$variations = $this->registry->getVariations( 'test-block' );

	expect( $variations )->toHaveCount( 2 )
		->and( $variations )->toHaveKeys( [ 'var-one', 'var-two' ] )
		->and( $variations['var-one']['title'] )->toBe( 'Variation One' );
} );

test( 'it can get a specific variation', function (): void {
	$this->registry->register( 'test-block', [
		'name'     => 'Test Block',
		'category' => 'text',
	] );

	$this->registry->registerVariation( 'test-block', 'special', [
		'title'       => 'Special Variation',
		'description' => 'A special one',
		'icon'        => 'fas.magic',
	] );

	$variation = $this->registry->getVariation( 'test-block', 'special' );

	expect( $variation )->not->toBeNull()
		->and( $variation['title'] )->toBe( 'Special Variation' )
		->and( $variation['description'] )->toBe( 'A special one' )
		->and( $variation['icon'] )->toBe( 'fas.magic' );
} );

test( 'it returns null for non-existent variation', function (): void {
	$this->registry->register( 'test-block', [
		'name'     => 'Test Block',
		'category' => 'text',
	] );

	expect( $this->registry->getVariation( 'test-block', 'nonexistent' ) )->toBeNull();
} );

test( 'it returns empty array for block with no variations', function (): void {
	$this->registry->register( 'test-block', [
		'name'     => 'Test Block',
		'category' => 'text',
	] );

	expect( $this->registry->getVariations( 'test-block' ) )->toBe( [] )
		->and( $this->registry->hasVariations( 'test-block' ) )->toBeFalse();
} );

test( 'variation includes all default fields', function (): void {
	$this->registry->register( 'test-block', [
		'name'     => 'Test Block',
		'category' => 'text',
	] );

	$this->registry->registerVariation( 'test-block', 'test-var', [
		'title' => 'Test Variation',
	] );

	$variation = $this->registry->getVariation( 'test-block', 'test-var' );

	expect( $variation )->toHaveKeys( [
		'name',
		'title',
		'description',
		'icon',
		'isDefault',
		'attributes',
		'innerBlocks',
		'scope',
	] )
		->and( $variation['name'] )->toBe( 'test-var' )
		->and( $variation['description'] )->toBe( '' )
		->and( $variation['icon'] )->toBeNull()
		->and( $variation['isDefault'] )->toBeFalse()
		->and( $variation['attributes'] )->toBe( [] )
		->and( $variation['innerBlocks'] )->toBe( [] )
		->and( $variation['scope'] )->toBe( [ 'block', 'inserter', 'transform' ] );
} );

test( 'variation config values override defaults', function (): void {
	$this->registry->register( 'test-block', [
		'name'     => 'Test Block',
		'category' => 'text',
	] );

	$this->registry->registerVariation( 'test-block', 'custom-var', [
		'title'       => 'Custom Variation',
		'description' => 'A custom variation',
		'icon'        => 'fas.star',
		'isDefault'   => true,
		'attributes'  => [
			'settings' => [
				'color' => 'red',
			],
		],
		'innerBlocks' => [
			[ 'name' => 'text', 'attributes' => [] ],
		],
		'scope' => [ 'inserter' ],
	] );

	$variation = $this->registry->getVariation( 'test-block', 'custom-var' );

	expect( $variation['title'] )->toBe( 'Custom Variation' )
		->and( $variation['description'] )->toBe( 'A custom variation' )
		->and( $variation['icon'] )->toBe( 'fas.star' )
		->and( $variation['isDefault'] )->toBeTrue()
		->and( $variation['attributes'] )->toHaveKey( 'settings' )
		->and( $variation['attributes']['settings']['color'] )->toBe( 'red' )
		->and( $variation['innerBlocks'] )->toHaveCount( 1 )
		->and( $variation['scope'] )->toBe( [ 'inserter' ] );
} );

test( 'it throws exception when registering variation for non-existent block', function (): void {
	$this->registry->registerVariation( 'nonexistent-block', 'test-var', [
		'title' => 'Test Variation',
	] );
} )->throws( InvalidArgumentException::class, 'Cannot register variation for unregistered block type' );

test( 'it throws exception for empty variation name', function (): void {
	$this->registry->register( 'test-block', [
		'name'     => 'Test Block',
		'category' => 'text',
	] );

	$this->registry->registerVariation( 'test-block', '', [
		'title' => 'Test Variation',
	] );
} )->throws( InvalidArgumentException::class, 'Variation name cannot be empty' );

test( 'registerVariation returns self for chaining', function (): void {
	$this->registry->register( 'test-block', [
		'name'     => 'Test Block',
		'category' => 'text',
	] );

	$result = $this->registry->registerVariation( 'test-block', 'test-var', [
		'title' => 'Test Variation',
	] );

	expect( $result )->toBeInstanceOf( BlockRegistry::class );
} );

test( 'default group block has row stack and grid variations', function (): void {
	$this->registry->registerDefaults();

	expect( $this->registry->hasVariations( 'group' ) )->toBeTrue();

	$variations = $this->registry->getVariations( 'group' );

	expect( $variations )->toHaveKeys( [ 'group', 'row', 'stack', 'grid' ] )
		->and( $variations['group']['isDefault'] )->toBeTrue()
		->and( $variations['row']['isDefault'] )->toBeFalse()
		->and( $variations['stack']['isDefault'] )->toBeFalse()
		->and( $variations['grid']['isDefault'] )->toBeFalse();
} );

test( 'group block row variation has correct attributes', function (): void {
	$this->registry->registerDefaults();

	$row = $this->registry->getVariation( 'group', 'row' );

	expect( $row )->not->toBeNull()
		->and( $row['title'] )->toBe( 'Row' )
		->and( $row['description'] )->toBe( 'Arrange blocks horizontally.' )
		->and( $row['icon'] )->toBe( 'fas.grip-lines' )
		->and( $row['attributes']['settings']['flex_direction'] )->toBe( 'row' )
		->and( $row['attributes']['settings']['flex_wrap'] )->toBe( 'nowrap' )
		->and( $row['attributes']['settings']['align_items'] )->toBe( 'center' );
} );

test( 'group block stack variation has correct attributes', function (): void {
	$this->registry->registerDefaults();

	$stack = $this->registry->getVariation( 'group', 'stack' );

	expect( $stack )->not->toBeNull()
		->and( $stack['title'] )->toBe( 'Stack' )
		->and( $stack['description'] )->toBe( 'Arrange blocks vertically.' )
		->and( $stack['icon'] )->toBe( 'fas.grip-lines-vertical' )
		->and( $stack['attributes']['settings']['flex_direction'] )->toBe( 'column' )
		->and( $stack['attributes']['settings']['flex_wrap'] )->toBe( 'nowrap' )
		->and( $stack['attributes']['settings']['align_items'] )->toBe( 'stretch' );
} );

test( 'group block grid variation has correct attributes', function (): void {
	$this->registry->registerDefaults();

	$grid = $this->registry->getVariation( 'group', 'grid' );

	expect( $grid )->not->toBeNull()
		->and( $grid['title'] )->toBe( 'Grid' )
		->and( $grid['description'] )->toBe( 'Arrange blocks in a grid.' )
		->and( $grid['icon'] )->toBe( 'fas.table-cells' )
		->and( $grid['attributes']['settings']['flex_direction'] )->toBe( 'row' )
		->and( $grid['attributes']['settings']['flex_wrap'] )->toBe( 'wrap' );
} );

test( 'group block default variation has correct attributes', function (): void {
	$this->registry->registerDefaults();

	$default = $this->registry->getVariation( 'group', 'group' );

	expect( $default )->not->toBeNull()
		->and( $default['title'] )->toBe( 'Group' )
		->and( $default['description'] )->toBe( 'Gather blocks in a container.' )
		->and( $default['icon'] )->toBe( 'fas.object-group' )
		->and( $default['isDefault'] )->toBeTrue()
		->and( $default['attributes']['settings']['flex_direction'] )->toBe( 'column' )
		->and( $default['attributes']['settings']['flex_wrap'] )->toBe( 'nowrap' );
} );
