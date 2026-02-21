<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\EditorLayout;

test( 'editor layout can be instantiated with defaults', function (): void {
	$component = new EditorLayout();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->label )->toBeNull();
	expect( $component->sidebarPosition )->toBe( 'right' );
	expect( $component->sidebarWidth )->toBe( '280px' );
	expect( $component->sidebarCollapsible )->toBeTrue();
	expect( $component->leftSidebarWidth )->toBe( '280px' );
} );

test( 'editor layout accepts custom props', function (): void {
	$component = new EditorLayout(
		id: 'editor',
		label: 'Page editor',
		sidebarPosition: 'left',
		sidebarWidth: '320px',
		sidebarCollapsible: false,
		leftSidebarWidth: '300px',
	);

	expect( $component->uuid )->toContain( 'editor' );
	expect( $component->label )->toBe( 'Page editor' );
	expect( $component->sidebarPosition )->toBe( 'left' );
	expect( $component->sidebarWidth )->toBe( '320px' );
	expect( $component->sidebarCollapsible )->toBeFalse();
	expect( $component->leftSidebarWidth )->toBe( '300px' );
} );

test( 'editor layout falls back to right for invalid sidebar position', function (): void {
	$component = new EditorLayout( sidebarPosition: 'invalid' );

	expect( $component->sidebarPosition )->toBe( 'right' );
} );

test( 'editor layout renders', function (): void {
	$view = $this->blade( '<x-ve-editor-layout />' );
	expect( $view )->not->toBeNull();
} );

test( 'editor layout renders with aria label', function (): void {
	$this->blade( '<x-ve-editor-layout />' )
		->assertSee( 'aria-label=', false );
} );

test( 'editor layout renders toolbar slot', function (): void {
	$this->blade( '
		<x-ve-editor-layout>
			<x-slot:toolbar>Toolbar Content</x-slot:toolbar>
		</x-ve-editor-layout>
	' )
		->assertSee( 'Toolbar Content' );
} );

test( 'editor layout renders canvas slot', function (): void {
	$this->blade( '
		<x-ve-editor-layout>
			<x-slot:canvas>Canvas Content</x-slot:canvas>
		</x-ve-editor-layout>
	' )
		->assertSee( 'Canvas Content' );
} );

test( 'editor layout renders sidebar slot', function (): void {
	$this->blade( '
		<x-ve-editor-layout>
			<x-slot:sidebar>Sidebar Content</x-slot:sidebar>
		</x-ve-editor-layout>
	' )
		->assertSee( 'Sidebar Content' );
} );

test( 'editor layout renders statusbar slot', function (): void {
	$this->blade( '
		<x-ve-editor-layout>
			<x-slot:statusbar>Status Content</x-slot:statusbar>
		</x-ve-editor-layout>
	' )
		->assertSee( 'Status Content' );
} );

test( 'editor layout applies sidebar width', function (): void {
	$this->blade( '<x-ve-editor-layout sidebar-width="350px" />' )
		->assertSee( 'width: 350px', false );
} );

test( 'editor layout renders left sidebar slot', function (): void {
	$this->blade( '
		<x-ve-editor-layout>
			<x-slot:leftSidebar>Left Sidebar Content</x-slot:leftSidebar>
		</x-ve-editor-layout>
	' )
		->assertSee( 'Left Sidebar Content' );
} );

test( 'editor layout applies left sidebar width', function (): void {
	$this->blade( '
		<x-ve-editor-layout left-sidebar-width="300px">
			<x-slot:leftSidebar>Left Content</x-slot:leftSidebar>
		</x-ve-editor-layout>
	' )
		->assertSee( 'width: 300px', false );
} );
