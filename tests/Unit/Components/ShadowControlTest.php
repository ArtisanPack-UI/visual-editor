<?php

declare( strict_types=1 );

test( 'shadow control renders with label', function (): void {
	$this->blade( '<x-ve-shadow-control />' )
		->assertSee( __( 'visual-editor::ve.shadow' ) );
} );

test( 'shadow control renders preset buttons', function (): void {
	$this->blade( '<x-ve-shadow-control />' )
		->assertSee( 'None' )
		->assertSee( 'Sm' )
		->assertSee( 'Md' )
		->assertSee( 'Lg' );
} );

test( 'shadow control renders custom input toggle', function (): void {
	$this->blade( '<x-ve-shadow-control />' )
		->assertSee( __( 'visual-editor::ve.custom' ) );
} );
