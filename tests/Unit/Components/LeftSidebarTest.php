<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\LeftSidebar;

test( 'left sidebar can be instantiated with defaults', function (): void {
	$component = new LeftSidebar();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->label )->toBeNull();
	expect( $component->activeTab )->toBe( 'blocks' );
	expect( $component->width )->toBe( '280px' );
} );

test( 'left sidebar accepts custom props', function (): void {
	$component = new LeftSidebar(
		id: 'inserter',
		label: 'Inserter panel',
		activeTab: 'patterns',
		width: '320px',
	);

	expect( $component->uuid )->toContain( 'inserter' );
	expect( $component->label )->toBe( 'Inserter panel' );
	expect( $component->activeTab )->toBe( 'patterns' );
	expect( $component->width )->toBe( '320px' );
} );

test( 'left sidebar falls back to blocks for invalid tab', function (): void {
	$component = new LeftSidebar( activeTab: 'invalid' );

	expect( $component->activeTab )->toBe( 'blocks' );
} );

test( 'left sidebar renders', function (): void {
	$view = $this->blade( '<x-ve-left-sidebar />' );
	expect( $view )->not->toBeNull();
} );

test( 'left sidebar renders with complementary role', function (): void {
	$this->blade( '<x-ve-left-sidebar />' )
		->assertSee( 'role="complementary"', false );
} );

test( 'left sidebar renders tablist', function (): void {
	$this->blade( '<x-ve-left-sidebar />' )
		->assertSee( 'role="tablist"', false );
} );

test( 'left sidebar renders three tabs', function (): void {
	$view = $this->blade( '<x-ve-left-sidebar />' );

	$view->assertSee( 'Blocks' );
	$view->assertSee( 'Patterns' );
	$view->assertSee( 'Layers' );
} );

test( 'left sidebar renders blocks panel slot', function (): void {
	$this->blade( '
		<x-ve-left-sidebar>
			<x-slot:blocksPanel>Blocks Content</x-slot:blocksPanel>
		</x-ve-left-sidebar>
	' )
		->assertSee( 'Blocks Content' );
} );

test( 'left sidebar renders patterns panel slot', function (): void {
	$this->blade( '
		<x-ve-left-sidebar>
			<x-slot:patternsPanel>Patterns Content</x-slot:patternsPanel>
		</x-ve-left-sidebar>
	' )
		->assertSee( 'Patterns Content' );
} );

test( 'left sidebar renders layers panel slot', function (): void {
	$this->blade( '
		<x-ve-left-sidebar>
			<x-slot:layersPanel>Layers Content</x-slot:layersPanel>
		</x-ve-left-sidebar>
	' )
		->assertSee( 'Layers Content' );
} );

test( 'left sidebar renders close button', function (): void {
	$this->blade( '<x-ve-left-sidebar />' )
		->assertSee( 'showInserter = false', false );
} );
