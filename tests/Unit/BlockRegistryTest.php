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
