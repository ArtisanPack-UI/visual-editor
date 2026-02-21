<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\PanelBody;

test( 'panel body can be instantiated with defaults', function (): void {
	$component = new PanelBody();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->title )->toBeNull();
	expect( $component->icon )->toBeNull();
	expect( $component->opened )->toBeTrue();
	expect( $component->collapsible )->toBeTrue();
} );

test( 'panel body accepts custom props', function (): void {
	$component = new PanelBody(
		id: 'typography',
		title: 'Typography',
		icon: 'o-font',
		opened: false,
		collapsible: false,
	);

	expect( $component->uuid )->toContain( 'typography' );
	expect( $component->title )->toBe( 'Typography' );
	expect( $component->icon )->toBe( 'o-font' );
	expect( $component->opened )->toBeFalse();
	expect( $component->collapsible )->toBeFalse();
} );

test( 'panel body renders', function (): void {
	$view = $this->blade( '<x-ve-panel-body title="Section">Content</x-ve-panel-body>' );
	expect( $view )->not->toBeNull();
} );

test( 'panel body renders with title', function (): void {
	$this->blade( '<x-ve-panel-body title="Colors">Content</x-ve-panel-body>' )
		->assertSee( 'Colors' );
} );

test( 'panel body renders with slot content', function (): void {
	$this->blade( '<x-ve-panel-body title="Test">Body Content</x-ve-panel-body>' )
		->assertSee( 'Body Content' );
} );
