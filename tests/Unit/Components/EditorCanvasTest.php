<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\EditorCanvas;

test( 'editor canvas can be instantiated with defaults', function (): void {
	$component = new EditorCanvas();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->label )->toBeNull();
	expect( $component->showInsertionPoints )->toBeTrue();
	expect( $component->enableInlineEditing )->toBeTrue();
	expect( $component->enableDragReorder )->toBeTrue();
	expect( $component->enableArrowNavigation )->toBeTrue();
} );

test( 'editor canvas accepts custom props', function (): void {
	$component = new EditorCanvas(
		id: 'main-canvas',
		label: 'Content area',
		showInsertionPoints: false,
		enableInlineEditing: false,
		enableDragReorder: false,
		enableArrowNavigation: false,
	);

	expect( $component->uuid )->toContain( 'main-canvas' );
	expect( $component->label )->toBe( 'Content area' );
	expect( $component->showInsertionPoints )->toBeFalse();
	expect( $component->enableInlineEditing )->toBeFalse();
	expect( $component->enableDragReorder )->toBeFalse();
	expect( $component->enableArrowNavigation )->toBeFalse();
} );

test( 'editor canvas renders', function (): void {
	$view = $this->blade( '<x-ve-editor-canvas>Content</x-ve-editor-canvas>' );
	expect( $view )->not->toBeNull();
} );

test( 'editor canvas renders with main role', function (): void {
	$this->blade( '<x-ve-editor-canvas>Content</x-ve-editor-canvas>' )
		->assertSee( 'role="main"', false );
} );

test( 'editor canvas renders with slot content', function (): void {
	$this->blade( '<x-ve-editor-canvas>Canvas Content</x-ve-editor-canvas>' )
		->assertSee( 'Canvas Content' );
} );

test( 'editor canvas renders with default aria label', function (): void {
	$this->blade( '<x-ve-editor-canvas>Content</x-ve-editor-canvas>' )
		->assertSee( 'Editor canvas' );
} );
