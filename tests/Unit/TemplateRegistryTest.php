<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Registries\TemplateRegistry;

beforeEach( function (): void {
	$this->registry = new TemplateRegistry();
} );

test( 'it can register a template', function (): void {
	$this->registry->register( 'custom-template', [
		'label'       => 'Custom Template',
		'description' => 'A custom template for testing',
		'icon'        => 'o-document',
	] );

	expect( $this->registry->has( 'custom-template' ) )->toBeTrue();
} );

test( 'it can retrieve a registered template', function (): void {
	$config = [
		'label'       => 'Custom Template',
		'description' => 'A custom template for testing',
		'icon'        => 'o-document',
	];

	$this->registry->register( 'custom-template', $config );

	$template = $this->registry->get( 'custom-template' );

	expect( $template )->not->toBeNull()
		->and( $template['label'] )->toBe( 'Custom Template' );
} );

test( 'it returns null for non-existent template', function (): void {
	expect( $this->registry->get( 'non-existent' ) )->toBeNull();
} );

test( 'it can unregister a template', function (): void {
	$this->registry->register( 'temp-template', [
		'label' => 'Temporary Template',
	] );

	expect( $this->registry->has( 'temp-template' ) )->toBeTrue();

	$this->registry->unregister( 'temp-template' );

	expect( $this->registry->has( 'temp-template' ) )->toBeFalse();
} );

test( 'it can get all registered templates', function (): void {
	$this->registry->register( 'template-one', [ 'label' => 'Template One' ] );
	$this->registry->register( 'template-two', [ 'label' => 'Template Two' ] );

	$all = $this->registry->all();

	expect( $all )->toHaveCount( 2 )
		->and( $all->has( 'template-one' ) )->toBeTrue()
		->and( $all->has( 'template-two' ) )->toBeTrue();
} );

test( 'it can register a template part', function (): void {
	$this->registry->registerPart( 'custom-header', [
		'label' => 'Custom Header',
		'area'  => 'header',
	] );

	$part = $this->registry->getPart( 'custom-header' );

	expect( $part )->not->toBeNull()
		->and( $part['label'] )->toBe( 'Custom Header' );
} );

test( 'it can get all template parts', function (): void {
	$this->registry->registerPart( 'header-one', [
		'label' => 'Header One',
		'area'  => 'header',
	] );

	$this->registry->registerPart( 'footer-one', [
		'label' => 'Footer One',
		'area'  => 'footer',
	] );

	$parts = $this->registry->allParts();

	expect( $parts )->toHaveCount( 2 );
} );

test( 'it can get template parts by area', function (): void {
	$this->registry->registerPart( 'header-one', [
		'label' => 'Header One',
		'area'  => 'header',
	] );

	$this->registry->registerPart( 'header-two', [
		'label' => 'Header Two',
		'area'  => 'header',
	] );

	$this->registry->registerPart( 'footer-one', [
		'label' => 'Footer One',
		'area'  => 'footer',
	] );

	$headerParts = $this->registry->getPartsByArea( 'header' );

	expect( $headerParts )->toHaveCount( 2 )
		->and( $headerParts->has( 'header-one' ) )->toBeTrue()
		->and( $headerParts->has( 'header-two' ) )->toBeTrue();
} );

test( 'it can get the default template name', function (): void {
	$this->registry->register( 'default', [
		'label' => 'Default',
	] );

	$this->registry->register( 'other', [
		'label' => 'Other',
	] );

	// getDefault() returns the default template NAME (string), not the config
	$default = $this->registry->getDefault();

	expect( $default )->toBeString()
		->and( $default )->toBe( 'default' );
} );

test( 'it registers default templates', function (): void {
	$this->registry->registerDefaults();

	expect( $this->registry->has( 'default' ) )->toBeTrue()
		->and( $this->registry->has( 'full-width' ) )->toBeTrue()
		->and( $this->registry->has( 'landing' ) )->toBeTrue()
		->and( $this->registry->has( 'blank' ) )->toBeTrue();
} );

test( 'it registers default template parts', function (): void {
	$this->registry->registerDefaults();

	$parts = $this->registry->allParts();

	expect( $parts )->not->toBeEmpty()
		->and( $this->registry->getPart( 'header' ) )->not->toBeNull()
		->and( $this->registry->getPart( 'footer' ) )->not->toBeNull();
} );

test( 'register returns self for chaining', function (): void {
	$result = $this->registry->register( 'chain-template', [ 'label' => 'Chain Template' ] );

	expect( $result )->toBeInstanceOf( TemplateRegistry::class );
} );
