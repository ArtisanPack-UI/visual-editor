<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\ToolbarDropdown;

test( 'toolbar dropdown can be instantiated with defaults', function (): void {
	$component = new ToolbarDropdown();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->label )->toBeNull();
	expect( $component->icon )->toBeNull();
	expect( $component->tooltip )->toBeNull();
	expect( $component->disabled )->toBeFalse();
	expect( $component->placement )->toBe( 'bottom-start' );
} );

test( 'toolbar dropdown accepts custom props', function (): void {
	$component = new ToolbarDropdown(
		id: 'more-options',
		label: 'More',
		icon: 'o-ellipsis-vertical',
		tooltip: 'More options',
		placement: 'bottom-end',
	);

	expect( $component->uuid )->toContain( 'more-options' );
	expect( $component->label )->toBe( 'More' );
	expect( $component->icon )->toBe( 'o-ellipsis-vertical' );
	expect( $component->tooltip )->toBe( 'More options' );
	expect( $component->placement )->toBe( 'bottom-end' );
} );

test( 'toolbar dropdown renders', function (): void {
	$view = $this->blade( '<x-ve-toolbar-dropdown label="More">Menu</x-ve-toolbar-dropdown>' );
	expect( $view )->not->toBeNull();
} );

test( 'toolbar dropdown renders with label', function (): void {
	$this->blade( '<x-ve-toolbar-dropdown label="Options">Menu</x-ve-toolbar-dropdown>' )
		->assertSee( 'Options' );
} );

test( 'toolbar dropdown renders menu role', function (): void {
	$this->blade( '<x-ve-toolbar-dropdown>Menu Items</x-ve-toolbar-dropdown>' )
		->assertSee( 'role="menu"', false );
} );
