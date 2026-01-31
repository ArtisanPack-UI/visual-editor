<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Registries\SectionRegistry;

beforeEach( function (): void {
	$this->registry = new SectionRegistry();
} );

test( 'it can register a section type', function (): void {
	$this->registry->register( 'custom-section', [
		'label'       => 'Custom Section',
		'description' => 'A custom section for testing',
		'category'    => 'headers',
		'icon'        => 'o-rectangle-group',
	] );

	expect( $this->registry->has( 'custom-section' ) )->toBeTrue();
} );

test( 'it can retrieve a registered section', function (): void {
	$config = [
		'label'       => 'Custom Section',
		'description' => 'A custom section for testing',
		'category'    => 'headers',
		'icon'        => 'o-rectangle-group',
	];

	$this->registry->register( 'custom-section', $config );

	$section = $this->registry->get( 'custom-section' );

	expect( $section )->not->toBeNull()
		->and( $section['label'] )->toBe( 'Custom Section' )
		->and( $section['category'] )->toBe( 'headers' );
} );

test( 'it returns null for non-existent section', function (): void {
	expect( $this->registry->get( 'non-existent' ) )->toBeNull();
} );

test( 'it can unregister a section', function (): void {
	$this->registry->register( 'temp-section', [
		'label' => 'Temporary Section',
	] );

	expect( $this->registry->has( 'temp-section' ) )->toBeTrue();

	$this->registry->unregister( 'temp-section' );

	expect( $this->registry->has( 'temp-section' ) )->toBeFalse();
} );

test( 'it can get all registered sections', function (): void {
	$this->registry->register( 'section-one', [ 'label' => 'Section One' ] );
	$this->registry->register( 'section-two', [ 'label' => 'Section Two' ] );

	$all = $this->registry->all();

	expect( $all )->toHaveCount( 2 )
		->and( $all->has( 'section-one' ) )->toBeTrue()
		->and( $all->has( 'section-two' ) )->toBeTrue();
} );

test( 'it can get sections by category', function (): void {
	$this->registry->register( 'section-hero', [
		'label'    => 'Hero Section',
		'category' => 'headers',
	] );

	$this->registry->register( 'section-content', [
		'label'    => 'Content Section',
		'category' => 'content',
	] );

	$heroSections = $this->registry->getByCategory( 'headers' );

	expect( $heroSections )->toHaveCount( 1 )
		->and( $heroSections->has( 'section-hero' ) )->toBeTrue();
} );

test( 'it can get sections grouped by category', function (): void {
	// Predefined categories: headers, content, features, social_proof, cta, contact
	$this->registry->register( 'section-content-1', [
		'label'    => 'Content Section 1',
		'category' => 'content',
	] );

	$this->registry->register( 'section-content-2', [
		'label'    => 'Content Section 2',
		'category' => 'content',
	] );

	$this->registry->register( 'section-cta', [
		'label'    => 'CTA Section',
		'category' => 'cta',
	] );

	$grouped = $this->registry->getGroupedByCategory();

	expect( $grouped )->toHaveCount( 2 )
		->and( $grouped->has( 'content' ) )->toBeTrue()
		->and( $grouped->has( 'cta' ) )->toBeTrue()
		->and( $grouped['content']['sections'] )->toHaveCount( 2 );
} );

test( 'it can get default settings for a section', function (): void {
	$this->registry->register( 'custom-section', [
		'label'            => 'Custom Section',
		'default_settings' => [
			'background_color' => '#ffffff',
			'padding'          => '60px',
		],
	] );

	$settings = $this->registry->getDefaultSettings( 'custom-section' );

	expect( $settings )->not->toBeNull()
		->and( $settings['background_color'] )->toBe( '#ffffff' )
		->and( $settings['padding'] )->toBe( '60px' );
} );

test( 'it can get default blocks for a section', function (): void {
	$this->registry->register( 'custom-section', [
		'label'          => 'Custom Section',
		'default_blocks' => [
			[ 'type' => 'heading', 'content' => 'Section Title' ],
			[ 'type' => 'text', 'content' => 'Section content here.' ],
		],
	] );

	$blocks = $this->registry->getDefaultBlocks( 'custom-section' );

	expect( $blocks )->toHaveCount( 2 )
		->and( $blocks[0]['type'] )->toBe( 'heading' );
} );

test( 'it registers default sections', function (): void {
	$this->registry->registerDefaults();

	expect( $this->registry->has( 'hero' ) )->toBeTrue()
		->and( $this->registry->has( 'text' ) )->toBeTrue()
		->and( $this->registry->has( 'features' ) )->toBeTrue()
		->and( $this->registry->has( 'cta' ) )->toBeTrue();
} );

test( 'register returns self for chaining', function (): void {
	$result = $this->registry->register( 'chain-section', [ 'label' => 'Chain Section' ] );

	expect( $result )->toBeInstanceOf( SectionRegistry::class );
} );

// =========================================
// Validation Tests
// =========================================

test( 'it throws exception for empty section type', function (): void {
	$this->registry->register( '', [ 'name' => 'Empty Type' ] );
} )->throws( InvalidArgumentException::class, 'Section type cannot be empty.' );

test( 'it throws exception for whitespace-only section type', function (): void {
	$this->registry->register( '   ', [ 'name' => 'Whitespace Type' ] );
} )->throws( InvalidArgumentException::class, 'Section type cannot be empty.' );

test( 'it throws exception for section type with invalid characters', function (): void {
	$this->registry->register( 'my section!', [ 'name' => 'Invalid Type' ] );
} )->throws( InvalidArgumentException::class, 'contains invalid characters' );

test( 'it throws exception for unregistered section category', function (): void {
	$this->registry->register( 'my-section', [
		'name'     => 'My Section',
		'category' => 'nonexistent-category',
	] );
} )->throws( InvalidArgumentException::class, 'is not registered' );

test( 'it allows section type with hyphens and underscores', function (): void {
	$this->registry->register( 'my-custom_section', [ 'name' => 'Hyphen Underscore Section' ] );

	expect( $this->registry->has( 'my-custom_section' ) )->toBeTrue();
} );

test( 'it allows section registration with a custom registered category', function (): void {
	$this->registry->registerCategory( 'custom', [
		'name' => 'Custom',
		'icon' => 'fas.star',
	] );

	$this->registry->register( 'custom-section', [
		'name'     => 'Custom Section',
		'category' => 'custom',
	] );

	expect( $this->registry->has( 'custom-section' ) )->toBeTrue()
		->and( $this->registry->get( 'custom-section' )['category'] )->toBe( 'custom' );
} );

// =========================================
// Default Config Fields Tests
// =========================================

test( 'registered section includes all default config fields', function (): void {
	$this->registry->register( 'test-section', [
		'name' => 'Test Section',
	] );

	$section = $this->registry->get( 'test-section' );

	expect( $section )->toHaveKeys( [
		'name',
		'description',
		'icon',
		'category',
		'default_blocks',
		'default_settings',
		'settings_schema',
	] )
		->and( $section['description'] )->toBe( '' )
		->and( $section['default_blocks'] )->toBe( [] )
		->and( $section['default_settings'] )->toBe( [] )
		->and( $section['settings_schema'] )->toBe( [] );
} );

test( 'registered section config values override defaults', function (): void {
	$this->registry->register( 'test-section', [
		'name'             => 'Test Section',
		'description'      => 'A test section',
		'default_blocks'   => [ [ 'type' => 'heading' ] ],
		'default_settings' => [ 'background' => 'blue' ],
	] );

	$section = $this->registry->get( 'test-section' );

	expect( $section['description'] )->toBe( 'A test section' )
		->and( $section['default_blocks'] )->toHaveCount( 1 )
		->and( $section['default_settings']['background'] )->toBe( 'blue' );
} );

test( 'it includes all default categories', function (): void {
	$categories = $this->registry->getCategories();

	expect( $categories )->toHaveKeys( [ 'headers', 'content', 'features', 'social_proof', 'cta', 'contact' ] );
} );

test( 'default hero section has default blocks with heading and button_group', function (): void {
	$this->registry->registerDefaults();

	$hero   = $this->registry->get( 'hero' );
	$blocks = $hero['default_blocks'];
	$types  = array_column( $blocks, 'type' );

	expect( $types )->toContain( 'heading' )
		->and( $types )->toContain( 'button_group' );
} );

test( 'default hero section has settings schema', function (): void {
	$this->registry->registerDefaults();

	$hero = $this->registry->get( 'hero' );

	expect( $hero['settings_schema'] )->toHaveKey( 'background' )
		->and( $hero['settings_schema'] )->toHaveKey( 'padding' );
} );
