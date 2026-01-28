<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;

beforeEach( function (): void {
	$this->registry = new BlockRegistry();
} );

test( 'it can register a block type', function (): void {
	$this->registry->register( 'custom-block', [
		'label'       => 'Custom Block',
		'description' => 'A custom block for testing',
		'category'    => 'common',
		'icon'        => 'o-cube',
	] );

	expect( $this->registry->has( 'custom-block' ) )->toBeTrue();
} );

test( 'it can retrieve a registered block', function (): void {
	$config = [
		'label'       => 'Custom Block',
		'description' => 'A custom block for testing',
		'category'    => 'common',
		'icon'        => 'o-cube',
	];

	$this->registry->register( 'custom-block', $config );

	$block = $this->registry->get( 'custom-block' );

	expect( $block )->not->toBeNull()
		->and( $block['label'] )->toBe( 'Custom Block' )
		->and( $block['category'] )->toBe( 'common' );
} );

test( 'it returns null for non-existent block', function (): void {
	expect( $this->registry->get( 'non-existent' ) )->toBeNull();
} );

test( 'it can unregister a block', function (): void {
	$this->registry->register( 'temp-block', [
		'label' => 'Temporary Block',
	] );

	expect( $this->registry->has( 'temp-block' ) )->toBeTrue();

	$this->registry->unregister( 'temp-block' );

	expect( $this->registry->has( 'temp-block' ) )->toBeFalse();
} );

test( 'it can get all registered blocks', function (): void {
	$this->registry->register( 'block-one', [ 'label' => 'Block One' ] );
	$this->registry->register( 'block-two', [ 'label' => 'Block Two' ] );

	$all = $this->registry->all();

	expect( $all )->toHaveCount( 2 )
		->and( $all->has( 'block-one' ) )->toBeTrue()
		->and( $all->has( 'block-two' ) )->toBeTrue();
} );

test( 'it can get blocks by category', function (): void {
	$this->registry->register( 'block-common', [
		'label'    => 'Common Block',
		'category' => 'common',
	] );

	$this->registry->register( 'block-media', [
		'label'    => 'Media Block',
		'category' => 'media',
	] );

	$commonBlocks = $this->registry->getByCategory( 'common' );

	expect( $commonBlocks )->toHaveCount( 1 )
		->and( $commonBlocks->has( 'block-common' ) )->toBeTrue();
} );

test( 'it can get blocks grouped by category', function (): void {
	// Use predefined categories from BlockRegistry: text, media, interactive, layout, dynamic
	$this->registry->register( 'block-text-1', [
		'label'    => 'Text Block 1',
		'category' => 'text',
	] );

	$this->registry->register( 'block-text-2', [
		'label'    => 'Text Block 2',
		'category' => 'text',
	] );

	$this->registry->register( 'block-media', [
		'label'    => 'Media Block',
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
	$result = $this->registry->register( 'chain-block', [ 'label' => 'Chain Block' ] );

	expect( $result )->toBeInstanceOf( BlockRegistry::class );
} );
