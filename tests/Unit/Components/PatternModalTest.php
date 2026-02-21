<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\PatternModal;

test( 'pattern modal can be instantiated with defaults', function (): void {
	$component = new PatternModal();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->patterns )->toBe( [] );
	expect( $component->categories )->toHaveCount( 6 );
} );

test( 'pattern modal accepts custom props', function (): void {
	$patterns   = [ [ 'name' => 'Hero', 'category' => 'header', 'blocks' => [] ] ];
	$categories = [ [ 'slug' => 'custom', 'label' => 'Custom' ] ];
	$component  = new PatternModal(
		id: 'modal',
		patterns: $patterns,
		categories: $categories,
	);

	expect( $component->uuid )->toContain( 'modal' );
	expect( $component->patterns )->toBe( $patterns );
	expect( $component->categories )->toBe( $categories );
} );

test( 'pattern modal uses default categories from pattern browser', function (): void {
	$component = new PatternModal();

	$slugs = array_column( $component->categories, 'slug' );
	expect( $slugs )->toContain( 'text', 'header', 'footer', 'call-to-action', 'gallery', 'testimonial' );
} );

test( 'pattern modal renders', function (): void {
	$view = $this->blade( '<x-ve-pattern-modal />' );
	expect( $view )->not->toBeNull();
} );

test( 'pattern modal renders as dialog element', function (): void {
	$this->blade( '<x-ve-pattern-modal />' )
		->assertSee( '<dialog', false );
} );

test( 'pattern modal renders title', function (): void {
	$this->blade( '<x-ve-pattern-modal />' )
		->assertSee( 'Patterns' );
} );

test( 'pattern modal renders search input', function (): void {
	$this->blade( '<x-ve-pattern-modal />' )
		->assertSee( 'search', false );
} );

test( 'pattern modal listens for open event', function (): void {
	$this->blade( '<x-ve-pattern-modal />' )
		->assertSee( 've-open-pattern-modal', false );
} );

test( 'pattern modal renders all categories button', function (): void {
	$this->blade( '<x-ve-pattern-modal />' )
		->assertSee( 'All' );
} );
