<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\InsertionPoint;

test( 'insertion point can be instantiated with defaults', function (): void {
	$component = new InsertionPoint();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->label )->toBeNull();
	expect( $component->index )->toBe( 0 );
} );

test( 'insertion point accepts custom props', function (): void {
	$component = new InsertionPoint(
		id: 'insert-3',
		label: 'Add after third',
		index: 3,
	);

	expect( $component->uuid )->toContain( 'insert-3' );
	expect( $component->label )->toBe( 'Add after third' );
	expect( $component->index )->toBe( 3 );
} );

test( 'insertion point renders', function (): void {
	$view = $this->blade( '<x-ve-insertion-point />' );
	expect( $view )->not->toBeNull();
} );

test( 'insertion point renders with presentation role', function (): void {
	$this->blade( '<x-ve-insertion-point />' )
		->assertSee( 'role="presentation"', false );
} );

test( 'insertion point renders with aria label', function (): void {
	$this->blade( '<x-ve-insertion-point :index="2" />' )
		->assertSee( 'aria-label', false );
} );
