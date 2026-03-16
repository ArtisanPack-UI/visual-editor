<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Interactive\Form\FormBlock;
use ArtisanPackUI\VisualEditor\Livewire\Blocks\FormBlockComponent;

test( 'form block has correct type', function (): void {
	$block = new FormBlock();

	expect( $block->getType() )->toBe( 'form' );
} );

test( 'form block is dynamic', function (): void {
	$block = new FormBlock();

	expect( $block->isDynamic() )->toBeTrue();
} );

test( 'form block has correct category', function (): void {
	$block = new FormBlock();

	expect( $block->getCategory() )->toBe( 'interactive' );
} );

test( 'form block returns correct component', function (): void {
	$block = new FormBlock();

	expect( $block->getComponent() )->toBe( FormBlockComponent::class );
} );

test( 'form block has content schema with form fields', function (): void {
	$block  = new FormBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [
		'formId', 'displayStyle', 'submitButtonText',
		'submitButtonColor', 'submitButtonSize', 'successMessage',
		'redirectUrl', 'showLabels', 'layout', 'columns',
		'useAjax', 'enableHoneypot', 'customClass',
	] );
} );

test( 'form block default content has correct values', function (): void {
	$block    = new FormBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['displayStyle'] )->toBe( 'embedded' )
		->and( $defaults['submitButtonColor'] )->toBe( 'primary' )
		->and( $defaults['submitButtonSize'] )->toBe( 'md' )
		->and( $defaults['showLabels'] )->toBeTrue()
		->and( $defaults['layout'] )->toBe( 'stacked' )
		->and( $defaults['columns'] )->toBe( 2 )
		->and( $defaults['useAjax'] )->toBeTrue()
		->and( $defaults['enableHoneypot'] )->toBeTrue();
} );

test( 'form block has style schema with field spacing', function (): void {
	$block  = new FormBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'fieldSpacing' )
		->and( $schema['fieldSpacing']['type'] )->toBe( 'unit' )
		->and( $schema['fieldSpacing']['default'] )->toBe( '1rem' );
} );

test( 'form block has toolbar controls', function (): void {
	$block    = new FormBlock();
	$controls = $block->getToolbarControls();

	expect( $controls )->toHaveCount( 1 )
		->and( $controls[0]['controls'][0]['field'] )->toBe( 'displayStyle' );
} );

test( 'form block toArray includes dynamic metadata', function (): void {
	$block = new FormBlock();
	$array = $block->toArray();

	expect( $array['dynamic'] )->toBeTrue()
		->and( $array['component'] )->toBe( FormBlockComponent::class )
		->and( $array['type'] )->toBe( 'form' );
} );

test( 'form block has keywords', function (): void {
	$block = new FormBlock();

	expect( $block->getKeywords() )->toContain( 'form' )
		->and( $block->getKeywords() )->toContain( 'contact' )
		->and( $block->getKeywords() )->toContain( 'submit' );
} );

test( 'form block display style options include all styles', function (): void {
	$block   = new FormBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['displayStyle']['options'] );

	expect( $options )->toContain( 'embedded' )
		->and( $options )->toContain( 'modal' )
		->and( $options )->toContain( 'slide-over' );
} );

test( 'form block layout options include all layouts', function (): void {
	$block   = new FormBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['layout']['options'] );

	expect( $options )->toContain( 'stacked' )
		->and( $options )->toContain( 'inline' )
		->and( $options )->toContain( 'grid' );
} );

test( 'form block columns range is 1 to 4', function (): void {
	$block  = new FormBlock();
	$schema = $block->getContentSchema();

	expect( $schema['columns']['min'] )->toBe( 1 )
		->and( $schema['columns']['max'] )->toBe( 4 )
		->and( $schema['columns']['default'] )->toBe( 2 );
} );

test( 'form block form id is a select field', function (): void {
	$block  = new FormBlock();
	$schema = $block->getContentSchema();

	expect( $schema['formId']['type'] )->toBe( 'select' )
		->and( $schema['formId']['options'] )->toBeArray()
		->and( $schema['formId']['options'] )->toHaveKey( '' );
} );

test( 'form block content schema fields are organized into panels', function (): void {
	$block  = new FormBlock();
	$schema = $block->getContentSchema();

	$panelFields = array_filter( $schema, fn ( $field ) => isset( $field['panel'] ) );

	expect( $panelFields )->not->toBeEmpty();

	$panels = array_unique( array_column( $panelFields, 'panel' ) );

	expect( count( $panels ) )->toBeGreaterThanOrEqual( 4 );
} );

test( 'form block columns field has show when condition for grid layout', function (): void {
	$block  = new FormBlock();
	$schema = $block->getContentSchema();

	expect( $schema['columns'] )->toHaveKey( 'showWhen' )
		->and( $schema['columns']['showWhen'] )->toBe( [ 'layout' => 'grid' ] );
} );

test( 'form block redirect url uses url field type', function (): void {
	$block  = new FormBlock();
	$schema = $block->getContentSchema();

	expect( $schema['redirectUrl']['type'] )->toBe( 'url' );
} );
