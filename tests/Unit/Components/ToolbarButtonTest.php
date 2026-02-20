<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\ToolbarButton;

test( 'toolbar button can be instantiated with defaults', function (): void {
	$component = new ToolbarButton();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->label )->toBeNull();
	expect( $component->icon )->toBeNull();
	expect( $component->active )->toBeFalse();
	expect( $component->disabled )->toBeFalse();
	expect( $component->tooltip )->toBeNull();
	expect( $component->shortcut )->toBeNull();
	expect( $component->variant )->toBe( 'default' );
} );

test( 'toolbar button accepts custom props', function (): void {
	$component = new ToolbarButton(
		id: 'bold',
		label: 'Bold',
		icon: 'o-bold',
		active: true,
		tooltip: 'Toggle bold',
		shortcut: 'Ctrl+B',
		variant: 'active',
	);

	expect( $component->uuid )->toContain( 'bold' );
	expect( $component->label )->toBe( 'Bold' );
	expect( $component->icon )->toBe( 'o-bold' );
	expect( $component->active )->toBeTrue();
	expect( $component->tooltip )->toBe( 'Toggle bold' );
	expect( $component->shortcut )->toBe( 'Ctrl+B' );
} );

test( 'toolbar button falls back to default for invalid variant', function (): void {
	$component = new ToolbarButton( variant: 'invalid' );

	expect( $component->variant )->toBe( 'default' );
} );

test( 'toolbar button renders', function (): void {
	$view = $this->blade( '<x-ve-toolbar-button label="Bold" />' );
	expect( $view )->not->toBeNull();
} );

test( 'toolbar button renders with label', function (): void {
	$this->blade( '<x-ve-toolbar-button label="Bold" />' )
		->assertSee( 'Bold' );
} );
