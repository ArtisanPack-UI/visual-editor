<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\Panel;

test( 'panel can be instantiated with defaults', function (): void {
	$component = new Panel();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->title )->toBeNull();
	expect( $component->maxHeight )->toBe( 'calc(100vh - 200px)' );
} );

test( 'panel accepts custom props', function (): void {
	$component = new Panel(
		id: 'inspector',
		title: 'Block Settings',
		maxHeight: '500px',
	);

	expect( $component->uuid )->toContain( 'inspector' );
	expect( $component->title )->toBe( 'Block Settings' );
	expect( $component->maxHeight )->toBe( '500px' );
} );

test( 'panel renders', function (): void {
	$view = $this->blade( '<x-ve-panel>Content</x-ve-panel>' );
	expect( $view )->not->toBeNull();
} );

test( 'panel renders with title', function (): void {
	$this->blade( '<x-ve-panel title="Settings">Content</x-ve-panel>' )
		->assertSee( 'Settings' );
} );

test( 'panel renders with slot content', function (): void {
	$this->blade( '<x-ve-panel>Panel Content</x-ve-panel>' )
		->assertSee( 'Panel Content' );
} );
