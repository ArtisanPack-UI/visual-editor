<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\TopToolbar;

test( 'top toolbar can be instantiated with defaults', function (): void {
	$component = new TopToolbar();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->label )->toBeNull();
	expect( $component->showInserterToggle )->toBeTrue();
	expect( $component->showUndoRedo )->toBeTrue();
	expect( $component->showDevicePreview )->toBeTrue();
	expect( $component->showSaveButton )->toBeTrue();
	expect( $component->showSettingsToggle )->toBeTrue();
} );

test( 'top toolbar accepts custom props', function (): void {
	$component = new TopToolbar(
		id: 'main-toolbar',
		label: 'Main toolbar',
		showInserterToggle: false,
		showUndoRedo: false,
		showDevicePreview: false,
		showSaveButton: false,
		showSettingsToggle: false,
	);

	expect( $component->uuid )->toContain( 'main-toolbar' );
	expect( $component->label )->toBe( 'Main toolbar' );
	expect( $component->showInserterToggle )->toBeFalse();
	expect( $component->showUndoRedo )->toBeFalse();
	expect( $component->showDevicePreview )->toBeFalse();
	expect( $component->showSaveButton )->toBeFalse();
	expect( $component->showSettingsToggle )->toBeFalse();
} );

test( 'top toolbar renders', function (): void {
	$view = $this->blade( '<x-ve-top-toolbar />' );
	expect( $view )->not->toBeNull();
} );

test( 'top toolbar renders with toolbar role', function (): void {
	$this->blade( '<x-ve-top-toolbar />' )
		->assertSee( 'role="toolbar"', false );
} );

test( 'top toolbar renders undo button by default', function (): void {
	$this->blade( '<x-ve-top-toolbar />' )
		->assertSee( 'Undo' );
} );

test( 'top toolbar renders save button by default', function (): void {
	$this->blade( '<x-ve-top-toolbar />' )
		->assertSee( 'Save' );
} );

test( 'top toolbar hides undo redo when disabled', function (): void {
	$this->blade( '<x-ve-top-toolbar :show-undo-redo="false" />' )
		->assertDontSee( 'Undo' );
} );

test( 'top toolbar hides save button when disabled', function (): void {
	$this->blade( '<x-ve-top-toolbar :show-save-button="false" />' )
		->assertDontSee( 'Save' );
} );

test( 'top toolbar renders center slot', function (): void {
	$this->blade( '
		<x-ve-top-toolbar>
			<x-slot:center>Center Content</x-slot:center>
		</x-ve-top-toolbar>
	' )
		->assertSee( 'Center Content' );
} );
