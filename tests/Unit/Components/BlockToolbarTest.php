<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\BlockToolbar;

test( 'block toolbar can be instantiated with defaults', function (): void {
	$component = new BlockToolbar();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->label )->toBeNull();
	expect( $component->blockType )->toBeNull();
	expect( $component->showMoveControls )->toBeTrue();
	expect( $component->showMoreOptions )->toBeTrue();
	expect( $component->placement )->toBe( 'top' );
} );

test( 'block toolbar accepts custom props', function (): void {
	$component = new BlockToolbar(
		id: 'toolbar',
		label: 'Block actions',
		blockType: 'Paragraph',
		showMoveControls: false,
		showMoreOptions: false,
		placement: 'bottom',
	);

	expect( $component->uuid )->toContain( 'toolbar' );
	expect( $component->label )->toBe( 'Block actions' );
	expect( $component->blockType )->toBe( 'Paragraph' );
	expect( $component->showMoveControls )->toBeFalse();
	expect( $component->showMoreOptions )->toBeFalse();
	expect( $component->placement )->toBe( 'bottom' );
} );

test( 'block toolbar falls back to top for invalid placement', function (): void {
	$component = new BlockToolbar( placement: 'invalid' );

	expect( $component->placement )->toBe( 'top' );
} );

test( 'block toolbar renders', function (): void {
	$view = $this->blade( '<x-ve-block-toolbar>Controls</x-ve-block-toolbar>' );
	expect( $view )->not->toBeNull();
} );

test( 'block toolbar renders with toolbar role', function (): void {
	$this->blade( '<x-ve-block-toolbar>Controls</x-ve-block-toolbar>' )
		->assertSee( 'role="toolbar"', false );
} );

test( 'block toolbar renders slot content', function (): void {
	$this->blade( '<x-ve-block-toolbar>Custom Controls</x-ve-block-toolbar>' )
		->assertSee( 'Custom Controls' );
} );

test( 'block toolbar renders block type indicator', function (): void {
	$this->blade( '<x-ve-block-toolbar block-type="Paragraph">Controls</x-ve-block-toolbar>' )
		->assertSee( 'Paragraph' );
} );

test( 'block toolbar renders move controls by default', function (): void {
	$this->blade( '<x-ve-block-toolbar>Controls</x-ve-block-toolbar>' )
		->assertSee( 'Move up' );
} );

test( 'block toolbar hides move controls when disabled', function (): void {
	$this->blade( '<x-ve-block-toolbar :show-move-controls="false">Controls</x-ve-block-toolbar>' )
		->assertDontSee( 'Move up' );
} );

test( 'block toolbar renders transform dropdown when block type is set', function (): void {
	$this->blade( '<x-ve-block-toolbar block-type="Paragraph">Controls</x-ve-block-toolbar>' )
		->assertSee( 'Transform block' );
} );
