<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;
use ArtisanPackUI\VisualEditor\Registries\SectionRegistry;
use ArtisanPackUI\VisualEditor\Registries\TemplateRegistry;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesManager;
use ArtisanPackUI\VisualEditor\VisualEditor;

test( 'visualEditor helper returns VisualEditor instance', function (): void {
	expect( visualEditor() )->toBeInstanceOf( VisualEditor::class );
} );

test( 'veBlocks helper returns BlockRegistry instance', function (): void {
	expect( veBlocks() )->toBeInstanceOf( BlockRegistry::class );
} );

test( 'veSections helper returns SectionRegistry instance', function (): void {
	expect( veSections() )->toBeInstanceOf( SectionRegistry::class );
} );

test( 'veTemplates helper returns TemplateRegistry instance', function (): void {
	expect( veTemplates() )->toBeInstanceOf( TemplateRegistry::class );
} );

test( 'veStyles helper returns GlobalStylesManager instance', function (): void {
	expect( veStyles() )->toBeInstanceOf( GlobalStylesManager::class );
} );

test( 'veRegisterBlock registers a block and returns registry', function (): void {
	$blockName = 'helper-test-block-' . uniqid();
	$result    = veRegisterBlock( $blockName, [
		'label' => 'Helper Test Block',
	] );

	expect( $result )->toBeInstanceOf( BlockRegistry::class )
		->and( veBlocks()->has( $blockName ) )->toBeTrue();
} );

test( 'veRegisterSection registers a section and returns registry', function (): void {
	$sectionName = 'helper-test-section-' . uniqid();
	$result      = veRegisterSection( $sectionName, [
		'label' => 'Helper Test Section',
	] );

	expect( $result )->toBeInstanceOf( SectionRegistry::class )
		->and( veSections()->has( $sectionName ) )->toBeTrue();
} );

test( 'veRegisterTemplate registers a template and returns registry', function (): void {
	$templateName = 'helper-test-template-' . uniqid();
	$result       = veRegisterTemplate( $templateName, [
		'label' => 'Helper Test Template',
	] );

	expect( $result )->toBeInstanceOf( TemplateRegistry::class )
		->and( veTemplates()->has( $templateName ) )->toBeTrue();
} );

test( 'veGetBlock returns registered block config', function (): void {
	veRegisterBlock( 'get-test-block', [
		'label'    => 'Get Test Block',
		'category' => 'text',
	] );

	$block = veGetBlock( 'get-test-block' );

	expect( $block )->toBeArray()
		->and( $block['label'] )->toBe( 'Get Test Block' );
} );

test( 'veBlockExists returns true for registered blocks', function (): void {
	expect( veBlockExists( 'heading' ) )->toBeTrue();
} );

test( 'veBlockExists returns false for unregistered blocks', function (): void {
	expect( veBlockExists( 'nonexistent-block' ) )->toBeFalse();
} );

test( 'veGetSection returns registered section config', function (): void {
	veRegisterSection( 'get-test-section', [
		'label'    => 'Get Test Section',
		'category' => 'content',
	] );

	$section = veGetSection( 'get-test-section' );

	expect( $section )->toBeArray()
		->and( $section['label'] )->toBe( 'Get Test Section' );
} );

test( 'veGetTemplate returns registered template config', function (): void {
	veRegisterTemplate( 'get-test-template', [
		'label' => 'Get Test Template',
	] );

	$template = veGetTemplate( 'get-test-template' );

	expect( $template )->toBeArray()
		->and( $template['label'] )->toBe( 'Get Test Template' );
} );

test( 'veGetGlobalStyles returns styles array', function (): void {
	$styles = veGetGlobalStyles();

	expect( $styles )->toBeArray()
		->and( $styles )->toHaveKey( 'colors' );
} );

test( 'veGetStyleValue returns style value', function (): void {
	$primaryColor = veGetStyleValue( 'colors.primary' );

	expect( $primaryColor )->toBeString()
		->and( $primaryColor )->toStartWith( '#' );
} );

test( 'veGetStyleValue returns default for non-existent', function (): void {
	$value = veGetStyleValue( 'non.existent', 'default' );

	expect( $value )->toBe( 'default' );
} );

test( 'veGenerateCss returns CSS string', function (): void {
	$css = veGenerateCss( false );

	expect( $css )->toBeString()
		->and( $css )->toContain( ':root' );
} );

test( 'veGetAvailableBlocks returns collection', function (): void {
	$blocks = veGetAvailableBlocks();

	expect( $blocks )->toBeInstanceOf( Illuminate\Support\Collection::class );
} );

test( 'veGetBlocksByCategory returns collection grouped by category', function (): void {
	$grouped = veGetBlocksByCategory();

	expect( $grouped )->toBeInstanceOf( Illuminate\Support\Collection::class );
} );

test( 'veGetSectionsByCategory returns collection grouped by category', function (): void {
	$grouped = veGetSectionsByCategory();

	expect( $grouped )->toBeInstanceOf( Illuminate\Support\Collection::class );
} );

test( 'veGetAllTemplates returns collection of templates', function (): void {
	$templates = veGetAllTemplates();

	expect( $templates )->toBeInstanceOf( Illuminate\Support\Collection::class );
} );
