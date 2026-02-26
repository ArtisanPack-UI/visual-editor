<?php

declare( strict_types=1 );

test( 'background control renders with label', function (): void {
	$this->blade( '<x-ve-background-control :active-controls="[\'backgroundImage\']" />' )
		->assertSee( __( 'visual-editor::ve.background' ) );
} );

test( 'background control renders image input when enabled', function (): void {
	$this->blade( '<x-ve-background-control :active-controls="[\'backgroundImage\']" />' )
		->assertSee( __( 'visual-editor::ve.background_image' ) );
} );

test( 'background control renders size select when enabled', function (): void {
	$this->blade( '<x-ve-background-control :active-controls="[\'backgroundSize\']" />' )
		->assertSee( __( 'visual-editor::ve.background_size' ) );
} );

test( 'background control does not render disabled controls', function (): void {
	$this->blade( '<x-ve-background-control :active-controls="[\'backgroundImage\']" />' )
		->assertDontSee( __( 'visual-editor::ve.background_size' ) )
		->assertDontSee( __( 'visual-editor::ve.background_position' ) );
} );
