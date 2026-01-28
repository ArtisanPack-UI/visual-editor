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
		'category'    => 'hero',
		'icon'        => 'o-rectangle-group',
	] );

	expect( $this->registry->has( 'custom-section' ) )->toBeTrue();
} );

test( 'it can retrieve a registered section', function (): void {
	$config = [
		'label'       => 'Custom Section',
		'description' => 'A custom section for testing',
		'category'    => 'hero',
		'icon'        => 'o-rectangle-group',
	];

	$this->registry->register( 'custom-section', $config );

	$section = $this->registry->get( 'custom-section' );

	expect( $section )->not->toBeNull()
		->and( $section['label'] )->toBe( 'Custom Section' )
		->and( $section['category'] )->toBe( 'hero' );
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
		'category' => 'hero',
	] );

	$this->registry->register( 'section-content', [
		'label'    => 'Content Section',
		'category' => 'content',
	] );

	$heroSections = $this->registry->getByCategory( 'hero' );

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
