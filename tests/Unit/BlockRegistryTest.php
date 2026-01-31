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
