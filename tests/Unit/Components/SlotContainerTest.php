<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\SlotContainer;

test( 'slot container can be instantiated with defaults', function (): void {
	$component = new SlotContainer( name: 'test-slot' );

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->name )->toBe( 'test-slot' );
} );

test( 'slot container throws exception when name is empty', function (): void {
	new SlotContainer();
} )->throws( InvalidArgumentException::class, 'SlotContainer requires a non-empty name for slot-fill matching.' );

test( 'slot container accepts custom props', function (): void {
	$component = new SlotContainer(
		id: 'toolbar-controls',
		name: 'toolbar-controls',
	);

	expect( $component->uuid )->toContain( 'toolbar-controls' );
	expect( $component->name )->toBe( 'toolbar-controls' );
} );

test( 'slot container renders', function (): void {
	$view = $this->blade( '<x-ve-slot-container name="test">Default</x-ve-slot-container>' );
	expect( $view )->not->toBeNull();
} );

test( 'slot container renders with default content', function (): void {
	$this->blade( '<x-ve-slot-container name="test">Default Content</x-ve-slot-container>' )
		->assertSee( 'Default Content' );
} );

test( 'slot container renders data attribute', function (): void {
	$this->blade( '<x-ve-slot-container name="my-slot">Content</x-ve-slot-container>' )
		->assertSee( 'data-ve-slot="my-slot"', false );
} );
