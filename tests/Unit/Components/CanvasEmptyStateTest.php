<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\CanvasEmptyState;

test( 'canvas empty state can be instantiated with defaults', function (): void {
	$component = new CanvasEmptyState();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->title )->toBeNull();
	expect( $component->description )->toBeNull();
	expect( $component->buttonLabel )->toBeNull();
	expect( $component->icon )->toBeNull();
} );

test( 'canvas empty state accepts custom props', function (): void {
	$component = new CanvasEmptyState(
		id: 'empty',
		title: 'No content',
		description: 'Add something',
		buttonLabel: 'Insert',
		icon: 'o-plus',
	);

	expect( $component->uuid )->toContain( 'empty' );
	expect( $component->title )->toBe( 'No content' );
	expect( $component->description )->toBe( 'Add something' );
	expect( $component->buttonLabel )->toBe( 'Insert' );
	expect( $component->icon )->toBe( 'o-plus' );
} );

test( 'canvas empty state renders', function (): void {
	$view = $this->blade( '<x-ve-canvas-empty-state />' );
	expect( $view )->not->toBeNull();
} );

test( 'canvas empty state renders with status role', function (): void {
	$this->blade( '<x-ve-canvas-empty-state />' )
		->assertSee( 'role="status"', false );
} );

test( 'canvas empty state renders default title', function (): void {
	$this->blade( '<x-ve-canvas-empty-state />' )
		->assertSee( 'Start building' );
} );

test( 'canvas empty state renders custom title', function (): void {
	$this->blade( '<x-ve-canvas-empty-state title="Custom Title" />' )
		->assertSee( 'Custom Title' );
} );
