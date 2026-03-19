<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\TemplatePartSlot;

test( 'template part slot can be instantiated with defaults', function (): void {
	$component = new TemplatePartSlot();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->id )->toBeNull();
	expect( $component->label )->toBeNull();
	expect( $component->area )->toBe( 'custom' );
	expect( $component->assignedSlug )->toBeNull();
	expect( $component->assignedName )->toBeNull();
	expect( $component->availableParts )->toBe( [] );
	expect( $component->isEditing )->toBeFalse();
	expect( $component->isLocked )->toBeFalse();
} );

test( 'template part slot accepts custom props', function (): void {
	$component = new TemplatePartSlot(
		id: 'header-slot',
		area: 'header',
		assignedSlug: 'main-header',
		assignedName: 'Main Header',
		isEditing: true,
		isLocked: false,
	);

	expect( $component->uuid )->toContain( 'header-slot' );
	expect( $component->area )->toBe( 'header' );
	expect( $component->assignedSlug )->toBe( 'main-header' );
	expect( $component->assignedName )->toBe( 'Main Header' );
	expect( $component->isEditing )->toBeTrue();
} );

test( 'template part slot falls back to custom for invalid area', function (): void {
	$component = new TemplatePartSlot( area: 'invalid-area' );

	expect( $component->area )->toBe( 'custom' );
} );

test( 'template part slot accepts all valid areas', function (): void {
	foreach ( [ 'header', 'footer', 'sidebar', 'custom' ] as $area ) {
		$component = new TemplatePartSlot( area: $area );
		expect( $component->area )->toBe( $area );
	}
} );

test( 'template part slot hasAssignment returns true when slug assigned', function (): void {
	$component = new TemplatePartSlot( assignedSlug: 'main-header' );

	expect( $component->hasAssignment() )->toBeTrue();
} );

test( 'template part slot hasAssignment returns false when null', function (): void {
	$component = new TemplatePartSlot( assignedSlug: null );

	expect( $component->hasAssignment() )->toBeFalse();
} );

test( 'template part slot hasAssignment returns false when empty string', function (): void {
	$component = new TemplatePartSlot( assignedSlug: '' );

	expect( $component->hasAssignment() )->toBeFalse();
} );

test( 'template part slot areaLabel returns translated string for each area', function (): void {
	foreach ( [ 'header', 'footer', 'sidebar', 'custom' ] as $area ) {
		$component = new TemplatePartSlot( area: $area );
		expect( $component->areaLabel() )->toBeString();
		expect( $component->areaLabel() )->not->toBeEmpty();
	}
} );

test( 'template part slot renders', function (): void {
	$view = $this->blade( '<x-ve-template-part-slot area="header" />' );
	expect( $view )->not->toBeNull();
} );

test( 'template part slot renders area label badge', function (): void {
	$this->blade( '<x-ve-template-part-slot area="header" />' )
		->assertSee( 'Header' );
} );

test( 'template part slot renders empty state when no assignment', function (): void {
	$this->blade( '<x-ve-template-part-slot area="footer" />' )
		->assertSee( 'Add Template Part' );
} );

test( 'template part slot renders data attribute for area', function (): void {
	$this->blade( '<x-ve-template-part-slot area="sidebar" />' )
		->assertSee( 'data-template-area="sidebar"', false );
} );

test( 'template part slot renders assigned part name', function (): void {
	$this->blade( '<x-ve-template-part-slot area="header" assigned-slug="main-header" assigned-name="Main Header" />' )
		->assertSee( 'Main Header' );
} );

test( 'template part slot renders edit button for assigned part', function (): void {
	$this->blade( '<x-ve-template-part-slot area="header" assigned-slug="main-header" />' )
		->assertSee( 'Edit Part' );
} );

test( 'template part slot hides edit button when locked', function (): void {
	$this->blade( '<x-ve-template-part-slot area="header" assigned-slug="main-header" :is-locked="true" />' )
		->assertDontSee( 'Edit Part' );
} );
