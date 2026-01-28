<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;
use ArtisanPackUI\VisualEditor\Registries\SectionRegistry;
use ArtisanPackUI\VisualEditor\Registries\TemplateRegistry;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesManager;
use ArtisanPackUI\VisualEditor\VisualEditor;

beforeEach( function (): void {
	$this->editor = new VisualEditor();
} );

test( 'it returns version', function (): void {
	$version = $this->editor->version();

	expect( $version )->toBeString()
		->and( $version )->toBe( '1.0.0' );
} );

test( 'it can access block registry', function (): void {
	$blocks = $this->editor->blocks();

	expect( $blocks )->toBeInstanceOf( BlockRegistry::class );
} );

test( 'it can access section registry', function (): void {
	$sections = $this->editor->sections();

	expect( $sections )->toBeInstanceOf( SectionRegistry::class );
} );

test( 'it can access template registry', function (): void {
	$templates = $this->editor->templates();

	expect( $templates )->toBeInstanceOf( TemplateRegistry::class );
} );

test( 'it can access styles manager', function (): void {
	$styles = $this->editor->styles();

	expect( $styles )->toBeInstanceOf( GlobalStylesManager::class );
} );

test( 'it can register a block and chain', function (): void {
	$result = $this->editor->registerBlock( 'test-block', [
		'label' => 'Test Block',
	] );

	expect( $result )->toBeInstanceOf( VisualEditor::class )
		->and( $this->editor->blocks()->has( 'test-block' ) )->toBeTrue();
} );

test( 'it can register a section and chain', function (): void {
	$result = $this->editor->registerSection( 'test-section', [
		'label' => 'Test Section',
	] );

	expect( $result )->toBeInstanceOf( VisualEditor::class )
		->and( $this->editor->sections()->has( 'test-section' ) )->toBeTrue();
} );

test( 'it can register a template and chain', function (): void {
	$result = $this->editor->registerTemplate( 'test-template', [
		'label' => 'Test Template',
	] );

	expect( $result )->toBeInstanceOf( VisualEditor::class )
		->and( $this->editor->templates()->has( 'test-template' ) )->toBeTrue();
} );
