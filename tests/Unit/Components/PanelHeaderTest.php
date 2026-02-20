<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\PanelHeader;

test( 'panel header can be instantiated with defaults', function (): void {
	$component = new PanelHeader();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->title )->toBeNull();
} );

test( 'panel header accepts custom props', function (): void {
	$component = new PanelHeader(
		id: 'settings-header',
		title: 'Settings',
	);

	expect( $component->uuid )->toContain( 'settings-header' );
	expect( $component->title )->toBe( 'Settings' );
} );

test( 'panel header renders', function (): void {
	$view = $this->blade( '<x-ve-panel-header>Content</x-ve-panel-header>' );
	expect( $view )->not->toBeNull();
} );

test( 'panel header renders with title', function (): void {
	$this->blade( '<x-ve-panel-header title="Block Inspector" />' )
		->assertSee( 'Block Inspector' );
} );
