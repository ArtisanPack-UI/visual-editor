<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\FontSizePicker;

test( 'font size picker can be instantiated with defaults', function (): void {
	$component = new FontSizePicker();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->presets )->toBe( FontSizePicker::DEFAULT_PRESETS );
	expect( $component->showCustom )->toBeTrue();
	expect( $component->unit )->toBe( 'rem' );
} );

test( 'font size picker default presets are correct', function (): void {
	expect( FontSizePicker::DEFAULT_PRESETS )->toBe( [
		'S'  => '0.875rem',
		'M'  => '1rem',
		'L'  => '1.25rem',
		'XL' => '1.5rem',
	] );
} );

test( 'font size picker accepts custom presets', function (): void {
	$presets   = [ 'SM' => '12px', 'MD' => '16px', 'LG' => '24px' ];
	$component = new FontSizePicker( presets: $presets );

	expect( $component->presets )->toBe( $presets );
} );

test( 'font size picker detects active preset', function (): void {
	$component = new FontSizePicker( value: '1rem' );

	expect( $component->activePreset() )->toBe( 'M' );
} );

test( 'font size picker returns null for non-preset value', function (): void {
	$component = new FontSizePicker( value: '2rem' );

	expect( $component->activePreset() )->toBeNull();
} );

test( 'font size picker returns null for null value', function (): void {
	$component = new FontSizePicker();

	expect( $component->activePreset() )->toBeNull();
} );

test( 'font size picker renders', function (): void {
	$this->blade( '<x-ve-font-size-picker />' )
		->assertSee( 'radiogroup', false );
} );

test( 'font size picker renders preset buttons', function (): void {
	$this->blade( '<x-ve-font-size-picker />' )
		->assertSee( 'radiogroup', false );
} );

test( 'font size picker renders with label', function (): void {
	$this->blade( '<x-ve-font-size-picker label="Font Size" />' )
		->assertSee( 'Font Size' );
} );
