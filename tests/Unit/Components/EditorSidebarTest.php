<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\EditorSidebar;

test( 'editor sidebar can be instantiated with defaults', function (): void {
	$component = new EditorSidebar();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->label )->toBeNull();
	expect( $component->activeTab )->toBe( 'block' );
	expect( $component->showTabs )->toBeTrue();
	expect( $component->activeBlockSubTab )->toBe( 'settings' );
} );

test( 'editor sidebar accepts custom props', function (): void {
	$component = new EditorSidebar(
		id: 'sidebar',
		label: 'Settings sidebar',
		activeTab: 'document',
		showTabs: false,
		activeBlockSubTab: 'styles',
	);

	expect( $component->uuid )->toContain( 'sidebar' );
	expect( $component->label )->toBe( 'Settings sidebar' );
	expect( $component->activeTab )->toBe( 'document' );
	expect( $component->showTabs )->toBeFalse();
	expect( $component->activeBlockSubTab )->toBe( 'styles' );
} );

test( 'editor sidebar falls back to block for invalid tab', function (): void {
	$component = new EditorSidebar( activeTab: 'invalid' );

	expect( $component->activeTab )->toBe( 'block' );
} );

test( 'editor sidebar falls back to settings for invalid block sub-tab', function (): void {
	$component = new EditorSidebar( activeBlockSubTab: 'invalid' );

	expect( $component->activeBlockSubTab )->toBe( 'settings' );
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

test( 'editor sidebar renders block sub-tabs', function (): void {
	$view = $this->blade( '<x-ve-editor-sidebar />' );

	$view->assertSee( 'Settings' );
	$view->assertSee( 'Styles' );
} );

test( 'editor sidebar renders block panel slot in settings sub-tab', function (): void {
	$this->blade( '
		<x-ve-editor-sidebar>
			<x-slot:blockPanel>Block Settings Content</x-slot:blockPanel>
		</x-ve-editor-sidebar>
	' )
		->assertSee( 'Block Settings Content' );
} );

test( 'editor sidebar renders settings panel slot', function (): void {
	$this->blade( '
		<x-ve-editor-sidebar>
			<x-slot:settingsPanel>Settings Panel Content</x-slot:settingsPanel>
		</x-ve-editor-sidebar>
	' )
		->assertSee( 'Settings Panel Content' );
} );

test( 'editor sidebar renders styles panel slot', function (): void {
	$this->blade( '
		<x-ve-editor-sidebar>
			<x-slot:stylesPanel>Styles Panel Content</x-slot:stylesPanel>
		</x-ve-editor-sidebar>
	' )
		->assertSee( 'Styles Panel Content' );
} );

test( 'editor sidebar does not render advanced sub-tab', function (): void {
	$this->blade( '<x-ve-editor-sidebar />' )
		->assertDontSee( 'Advanced' );
} );

test( 'editor sidebar renders document panel slot', function (): void {
	$this->blade( '
		<x-ve-editor-sidebar>
			<x-slot:documentPanel>Document Settings Content</x-slot:documentPanel>
		</x-ve-editor-sidebar>
	' )
		->assertSee( 'Document Settings Content' );
} );

test( 'editor sidebar accepts custom second tab label', function (): void {
	$component = new EditorSidebar( secondTabLabel: 'Template' );

	expect( $component->secondTabLabel )->toBe( 'Template' );
} );

test( 'editor sidebar defaults to null second tab label', function (): void {
	$component = new EditorSidebar();

	expect( $component->secondTabLabel )->toBeNull();
} );

test( 'editor sidebar renders custom second tab label', function (): void {
	$this->blade( '<x-ve-editor-sidebar second-tab-label="Template" />' )
		->assertSee( 'Template' );
} );

test( 'editor sidebar renders default document tab label when no custom label', function (): void {
	$this->blade( '<x-ve-editor-sidebar />' )
		->assertSee( 'Document' );
} );
