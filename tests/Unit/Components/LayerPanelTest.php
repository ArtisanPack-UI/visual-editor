<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\LayerPanel;

test( 'layer panel can be instantiated with defaults', function (): void {
	$component = new LayerPanel();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->label )->toBeNull();
	expect( $component->activeView )->toBe( 'list' );
} );

test( 'layer panel accepts custom props', function (): void {
	$component = new LayerPanel(
		id: 'layers',
		label: 'Layers panel',
		activeView: 'outline',
	);

	expect( $component->uuid )->toContain( 'layers' );
	expect( $component->label )->toBe( 'Layers panel' );
	expect( $component->activeView )->toBe( 'outline' );
} );

test( 'layer panel falls back to list for invalid view', function (): void {
	$component = new LayerPanel( activeView: 'invalid' );

	expect( $component->activeView )->toBe( 'list' );
} );

test( 'layer panel renders', function (): void {
	$view = $this->blade( '<x-ve-layer-panel />' );
	expect( $view )->not->toBeNull();
} );

test( 'layer panel renders sub-tab switcher', function (): void {
	$view = $this->blade( '<x-ve-layer-panel />' );

	$view->assertSee( 'List view' );
	$view->assertSee( 'Outline' );
} );

test( 'layer panel renders tablist', function (): void {
	$this->blade( '<x-ve-layer-panel />' )
		->assertSee( 'role="tablist"', false );
} );

test( 'layer panel renders no headings message', function (): void {
	$this->blade( '<x-ve-layer-panel />' )
		->assertSee( 'No headings found.' );
} );

test( 'layer panel renders list view panel', function (): void {
	$this->blade( '<x-ve-layer-panel />' )
		->assertSee( 'list-panel', false );
} );

test( 'layer panel renders outline panel', function (): void {
	$this->blade( '<x-ve-layer-panel />' )
		->assertSee( 'outline-panel', false );
} );
