<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\Toolbar;

test( 'toolbar can be instantiated with defaults', function (): void {
	$component = new Toolbar();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->label )->toBeNull();
	expect( $component->orientation )->toBe( 'horizontal' );
} );

test( 'toolbar accepts custom props', function (): void {
	$component = new Toolbar(
		id: 'block-toolbar',
		label: 'Block tools',
		orientation: 'vertical',
	);

	expect( $component->uuid )->toContain( 'block-toolbar' );
	expect( $component->label )->toBe( 'Block tools' );
	expect( $component->orientation )->toBe( 'vertical' );
} );

test( 'toolbar falls back to horizontal for invalid orientation', function (): void {
	$component = new Toolbar( orientation: 'invalid' );

	expect( $component->orientation )->toBe( 'horizontal' );
} );

test( 'toolbar renders', function (): void {
	$view = $this->blade( '<x-ve-toolbar>Content</x-ve-toolbar>' );
	expect( $view )->not->toBeNull();
} );

test( 'toolbar renders with toolbar role', function (): void {
	$this->blade( '<x-ve-toolbar>Content</x-ve-toolbar>' )
		->assertSee( 'role="toolbar"', false );
} );

test( 'toolbar renders with label', function (): void {
	$this->blade( '<x-ve-toolbar label="Block tools">Content</x-ve-toolbar>' )
		->assertSee( 'Block tools' );
} );
