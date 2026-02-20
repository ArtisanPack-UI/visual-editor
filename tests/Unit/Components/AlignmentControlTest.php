<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\AlignmentControl;

test( 'alignment control can be instantiated with defaults', function (): void {
	$component = new AlignmentControl();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->mode )->toBe( 'horizontal' );
	expect( $component->value )->toBeNull();
	expect( $component->options )->toBeNull();
} );

test( 'alignment control resolves horizontal options by default', function (): void {
	$component = new AlignmentControl();
	$options   = $component->resolvedOptions();

	expect( $options )->toBe( [ 'left', 'center', 'right', 'justify' ] );
} );

test( 'alignment control resolves vertical options', function (): void {
	$component = new AlignmentControl( mode: 'vertical' );
	$options   = $component->resolvedOptions();

	expect( $options )->toBe( [ 'top', 'center', 'bottom' ] );
} );

test( 'alignment control uses custom options when provided', function (): void {
	$custom    = [ 'start', 'end' ];
	$component = new AlignmentControl( options: $custom );
	$options   = $component->resolvedOptions();

	expect( $options )->toBe( $custom );
} );

test( 'alignment control generates matrix options', function (): void {
	$component = new AlignmentControl( mode: 'matrix' );
	$matrix    = $component->matrixOptions();

	expect( $matrix )->toHaveCount( 9 );
	expect( $matrix[0] )->toBe( [
		'horizontal' => 'left',
		'vertical'   => 'top',
		'value'      => 'top-left',
	] );
	expect( $matrix[8] )->toBe( [
		'horizontal' => 'right',
		'vertical'   => 'bottom',
		'value'      => 'bottom-right',
	] );
} );

test( 'alignment control renders in horizontal mode', function (): void {
	$this->blade( '<x-ve-alignment-control />' )
		->assertSee( 'radiogroup', false );
} );

test( 'alignment control renders in matrix mode', function (): void {
	$view = $this->blade( '<x-ve-alignment-control mode="matrix" />' );
	expect( $view )->not->toBeNull();
} );

test( 'alignment control renders with label', function (): void {
	$this->blade( '<x-ve-alignment-control label="Text Alignment" />' )
		->assertSee( 'Text Alignment' );
} );
