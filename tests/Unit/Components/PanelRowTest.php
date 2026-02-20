<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\PanelRow;

test( 'panel row can be instantiated with defaults', function (): void {
	$component = new PanelRow();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->label )->toBeNull();
	expect( $component->help )->toBeNull();
	expect( $component->fullWidth )->toBeFalse();
} );

test( 'panel row accepts custom props', function (): void {
	$component = new PanelRow(
		id: 'font-size',
		label: 'Font Size',
		help: 'Set the font size for this block.',
		fullWidth: true,
	);

	expect( $component->uuid )->toContain( 'font-size' );
	expect( $component->label )->toBe( 'Font Size' );
	expect( $component->help )->toBe( 'Set the font size for this block.' );
	expect( $component->fullWidth )->toBeTrue();
} );

test( 'panel row renders', function (): void {
	$view = $this->blade( '<x-ve-panel-row>Control</x-ve-panel-row>' );
	expect( $view )->not->toBeNull();
} );

test( 'panel row renders with label', function (): void {
	$this->blade( '<x-ve-panel-row label="Font Size">Control</x-ve-panel-row>' )
		->assertSee( 'Font Size' );
} );

test( 'panel row renders with help text', function (): void {
	$this->blade( '<x-ve-panel-row help="Help text here">Control</x-ve-panel-row>' )
		->assertSee( 'Help text here' );
} );
