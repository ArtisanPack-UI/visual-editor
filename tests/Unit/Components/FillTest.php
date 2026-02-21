<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\Fill;

test( 'fill can be instantiated with defaults', function (): void {
	$component = new Fill( slotName: 'test-slot' );

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->slotName )->toBe( 'test-slot' );
	expect( $component->priority )->toBe( 10 );
} );

test( 'fill throws exception when slotName is empty', function (): void {
	new Fill();
} )->throws( InvalidArgumentException::class, 'Fill requires a non-empty slotName to target a SlotContainer.' );

test( 'fill accepts custom props', function (): void {
	$component = new Fill(
		id: 'bold-button',
		slotName: 'toolbar-controls',
		priority: 5,
	);

	expect( $component->uuid )->toContain( 'bold-button' );
	expect( $component->slotName )->toBe( 'toolbar-controls' );
	expect( $component->priority )->toBe( 5 );
} );

test( 'fill renders', function (): void {
	$view = $this->blade( '<x-ve-fill slotName="test">Fill Content</x-ve-fill>' );
	expect( $view )->not->toBeNull();
} );
