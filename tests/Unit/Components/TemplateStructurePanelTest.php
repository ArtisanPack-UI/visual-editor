<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\TemplateStructurePanel;

test( 'template structure panel can be instantiated with defaults', function (): void {
	$component = new TemplateStructurePanel();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->id )->toBeNull();
	expect( $component->label )->toBeNull();
	expect( $component->blockNames )->toBe( [] );
} );

test( 'template structure panel accepts custom props', function (): void {
	$blockNames = [
		'paragraph' => 'Paragraph',
		'heading'   => 'Heading',
		'image'     => 'Image',
	];

	$component = new TemplateStructurePanel(
		id: 'structure',
		blockNames: $blockNames,
	);

	expect( $component->uuid )->toContain( 'structure' );
	expect( $component->blockNames )->toBe( $blockNames );
	expect( $component->blockNames )->toHaveCount( 3 );
} );

test( 'template structure panel renders', function (): void {
	$view = $this->blade( '<x-ve-template-structure-panel />' );
	expect( $view )->not->toBeNull();
} );

test( 'template structure panel renders navigation role', function (): void {
	$this->blade( '<x-ve-template-structure-panel />' )
		->assertSee( 'role="navigation"', false );
} );

test( 'template structure panel renders header', function (): void {
	$this->blade( '<x-ve-template-structure-panel />' )
		->assertSee( 'Template Structure' );
} );

test( 'template structure panel renders tree role', function (): void {
	$this->blade( '<x-ve-template-structure-panel />' )
		->assertSee( 'role="tree"', false );
} );

test( 'template structure panel passes block names to alpine', function (): void {
	$this->blade(
		'<x-ve-template-structure-panel :block-names="[\'paragraph\' => \'Paragraph\']" />',
	)->assertSee( 'Paragraph', false );
} );
