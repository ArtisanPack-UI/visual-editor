<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\EditorSidebar;

test( 'editor sidebar can be instantiated with defaults', function (): void {
	$component = new EditorSidebar();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->label )->toBeNull();
	expect( $component->activeTab )->toBe( 'block' );
	expect( $component->showTabs )->toBeTrue();
} );

test( 'editor sidebar accepts custom props', function (): void {
	$component = new EditorSidebar(
		id: 'sidebar',
		label: 'Settings sidebar',
		activeTab: 'document',
		showTabs: false,
	);

	expect( $component->uuid )->toContain( 'sidebar' );
	expect( $component->label )->toBe( 'Settings sidebar' );
	expect( $component->activeTab )->toBe( 'document' );
	expect( $component->showTabs )->toBeFalse();
} );

test( 'editor sidebar falls back to block for invalid tab', function (): void {
	$component = new EditorSidebar( activeTab: 'invalid' );

	expect( $component->activeTab )->toBe( 'block' );
} );

test( 'editor sidebar renders', function (): void {
	$view = $this->blade( '<x-ve-editor-sidebar />' );
	expect( $view )->not->toBeNull();
} );

test( 'editor sidebar renders with complementary role', function (): void {
	$this->blade( '<x-ve-editor-sidebar />' )
		->assertSee( 'role="complementary"', false );
} );

test( 'editor sidebar renders tab switcher by default', function (): void {
	$this->blade( '<x-ve-editor-sidebar />' )
		->assertSee( 'role="tablist"', false );
} );

test( 'editor sidebar hides tabs when disabled', function (): void {
	$this->blade( '<x-ve-editor-sidebar :show-tabs="false" />' )
		->assertDontSee( 'role="tablist"', false );
} );

test( 'editor sidebar renders block panel slot', function (): void {
	$this->blade( '
		<x-ve-editor-sidebar>
			<x-slot:blockPanel>Block Settings Content</x-slot:blockPanel>
		</x-ve-editor-sidebar>
	' )
		->assertSee( 'Block Settings Content' );
} );

test( 'editor sidebar renders document panel slot', function (): void {
	$this->blade( '
		<x-ve-editor-sidebar>
			<x-slot:documentPanel>Document Settings Content</x-slot:documentPanel>
		</x-ve-editor-sidebar>
	' )
		->assertSee( 'Document Settings Content' );
} );
