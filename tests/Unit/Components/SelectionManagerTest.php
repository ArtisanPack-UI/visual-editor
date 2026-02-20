<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\SelectionManager;

test( 'selection manager can be instantiated with defaults', function (): void {
	$component = new SelectionManager();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->multiSelect )->toBeTrue();
	expect( $component->enableClipboard )->toBeTrue();
	expect( $component->selectionClass )->toBe( 'ring-2 ring-primary' );
	expect( $component->multiSelectionClass )->toBe( 'ring-2 ring-primary/60' );
	expect( $component->cutClass )->toBe( 'opacity-50' );
} );

test( 'selection manager accepts custom props', function (): void {
	$component = new SelectionManager(
		id: 'editor',
		multiSelect: false,
		enableClipboard: false,
		selectionClass: 'border-2 border-blue-500',
		multiSelectionClass: 'border-2 border-blue-300',
		cutClass: 'opacity-30',
	);

	expect( $component->uuid )->toContain( 'editor' );
	expect( $component->multiSelect )->toBeFalse();
	expect( $component->enableClipboard )->toBeFalse();
	expect( $component->selectionClass )->toBe( 'border-2 border-blue-500' );
	expect( $component->cutClass )->toBe( 'opacity-30' );
} );

test( 'selection manager renders', function (): void {
	$view = $this->blade( '<x-ve-selection-manager>Content</x-ve-selection-manager>' );
	expect( $view )->not->toBeNull();
} );

test( 'selection manager renders with slot content', function (): void {
	$this->blade( '<x-ve-selection-manager>Manager Content</x-ve-selection-manager>' )
		->assertSee( 'Manager Content' );
} );
