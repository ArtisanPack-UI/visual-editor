<?php

declare( strict_types=1 );

test( 'inspector controls component renders tabs', function (): void {
	$this->blade( '<x-ve-inspector-controls block-type="heading" />' )
		->assertSee( __( 'visual-editor::ve.settings_tab' ) )
		->assertSee( __( 'visual-editor::ve.styles_tab' ) )
		->assertSee( __( 'visual-editor::ve.advanced_tab' ) );
} );

test( 'inspector controls renders placeholder when no block selected', function (): void {
	$this->blade( '<x-ve-inspector-controls />' )
		->assertSee( __( 'visual-editor::ve.block_settings' ) );
} );

test( 'inspector section renders with target attribute', function (): void {
	$this->blade( '<x-ve-inspector-section target="styles"><p>Content</p></x-ve-inspector-section>' )
		->assertSee( 'data-inspector-target="styles"', false )
		->assertSee( 'Content' );
} );

test( 'inspector section defaults to settings target', function (): void {
	$this->blade( '<x-ve-inspector-section><p>Content</p></x-ve-inspector-section>' )
		->assertSee( 'data-inspector-target="settings"', false );
} );
