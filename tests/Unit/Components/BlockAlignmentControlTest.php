<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\BlockAlignmentControl;

test( 'block alignment control can be instantiated with defaults', function (): void {
	$component = new BlockAlignmentControl();

	expect( $component->uuid )->toStartWith( 've-ba-' );
	expect( $component->value )->toBe( 'none' );
	expect( $component->options )->toBe( [] );
	expect( $component->label )->toBeNull();
} );

test( 'block alignment control prepends none to options', function (): void {
	$component = new BlockAlignmentControl(
		options: [ 'left', 'center', 'right', 'wide', 'full' ],
	);

	expect( $component->resolvedOptions )->toBe( [ 'none', 'left', 'center', 'right', 'wide', 'full' ] );
} );

test( 'block alignment control does not duplicate none option', function (): void {
	$component = new BlockAlignmentControl(
		options: [ 'none', 'wide', 'full' ],
	);

	expect( $component->resolvedOptions )->toBe( [ 'none', 'wide', 'full' ] );
	expect( array_count_values( $component->resolvedOptions )['none'] )->toBe( 1 );
} );

test( 'block alignment control accepts custom value', function (): void {
	$component = new BlockAlignmentControl(
		value: 'wide',
		options: [ 'wide', 'full' ],
	);

	expect( $component->value )->toBe( 'wide' );
} );

test( 'block alignment control accepts custom label', function (): void {
	$component = new BlockAlignmentControl(
		label: 'Custom label',
	);

	expect( $component->label )->toBe( 'Custom label' );
} );

test( 'block alignment control renders with listbox role', function (): void {
	$this->blade( '<x-ve-block-alignment-control :options="[\'wide\', \'full\']" />' )
		->assertSee( 'listbox', false );
} );

test( 'block alignment control renders with label', function (): void {
	$this->blade( '<x-ve-block-alignment-control :label="\'Test label\'" :options="[\'wide\', \'full\']" />' )
		->assertSee( 'Test label', false );
} );
