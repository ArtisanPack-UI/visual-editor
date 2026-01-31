<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Facades\VisualEditor;
use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;
use ArtisanPackUI\VisualEditor\Registries\SectionRegistry;
use ArtisanPackUI\VisualEditor\Registries\TemplateRegistry;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesManager;

test( 'facade returns version', function (): void {
	$version = VisualEditor::version();

	expect( $version )->toBeString()
		->and( $version )->toBe( '1.0.0' );
} );

test( 'facade returns block registry', function (): void {
	$blocks = VisualEditor::blocks();

	expect( $blocks )->toBeInstanceOf( BlockRegistry::class );
} );

test( 'facade returns section registry', function (): void {
	$sections = VisualEditor::sections();

	expect( $sections )->toBeInstanceOf( SectionRegistry::class );
} );

test( 'facade returns template registry', function (): void {
	$templates = VisualEditor::templates();

	expect( $templates )->toBeInstanceOf( TemplateRegistry::class );
} );

test( 'facade returns global styles manager', function (): void {
	$styles = VisualEditor::styles();

	expect( $styles )->toBeInstanceOf( GlobalStylesManager::class );
} );

test( 'facade can register block', function (): void {
	$result = VisualEditor::registerBlock( 'facade-test-block', [
		'label' => 'Facade Test Block',
	] );

	expect( $result )->toBeInstanceOf( ArtisanPackUI\VisualEditor\VisualEditor::class )
		->and( VisualEditor::blocks()->has( 'facade-test-block' ) )->toBeTrue();
} );

test( 'facade can register section', function (): void {
	$result = VisualEditor::registerSection( 'facade-test-section', [
		'label' => 'Facade Test Section',
	] );

	expect( $result )->toBeInstanceOf( ArtisanPackUI\VisualEditor\VisualEditor::class )
		->and( VisualEditor::sections()->has( 'facade-test-section' ) )->toBeTrue();
} );

test( 'facade can register template', function (): void {
	$result = VisualEditor::registerTemplate( 'facade-test-template', [
		'label' => 'Facade Test Template',
	] );

	expect( $result )->toBeInstanceOf( ArtisanPackUI\VisualEditor\VisualEditor::class )
		->and( VisualEditor::templates()->has( 'facade-test-template' ) )->toBeTrue();
} );
