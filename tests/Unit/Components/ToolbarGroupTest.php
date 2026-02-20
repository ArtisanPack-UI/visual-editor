<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\ToolbarGroup;

test( 'toolbar group can be instantiated with defaults', function (): void {
	$component = new ToolbarGroup();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->label )->toBeNull();
} );

test( 'toolbar group accepts custom props', function (): void {
	$component = new ToolbarGroup(
		id: 'formatting',
		label: 'Text formatting',
	);

	expect( $component->uuid )->toContain( 'formatting' );
	expect( $component->label )->toBe( 'Text formatting' );
} );

test( 'toolbar group renders', function (): void {
	$view = $this->blade( '<x-ve-toolbar-group>Content</x-ve-toolbar-group>' );
	expect( $view )->not->toBeNull();
} );

test( 'toolbar group renders with group role', function (): void {
	$this->blade( '<x-ve-toolbar-group>Content</x-ve-toolbar-group>' )
		->assertSee( 'role="group"', false );
} );
