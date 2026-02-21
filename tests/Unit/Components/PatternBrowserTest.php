<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\PatternBrowser;

test( 'pattern browser can be instantiated with defaults', function (): void {
	$component = new PatternBrowser();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->patterns )->toBe( [] );
	expect( $component->categories )->toHaveCount( 6 );
	expect( $component->showSearch )->toBeTrue();
} );

test( 'pattern browser accepts custom props', function (): void {
	$patterns   = [ [ 'name' => 'Hero', 'category' => 'header', 'blocks' => [] ] ];
	$categories = [ [ 'slug' => 'custom', 'label' => 'Custom' ] ];
	$component  = new PatternBrowser(
		id: 'browser',
		patterns: $patterns,
		categories: $categories,
		showSearch: false,
	);

	expect( $component->uuid )->toContain( 'browser' );
	expect( $component->patterns )->toBe( $patterns );
	expect( $component->categories )->toBe( $categories );
	expect( $component->showSearch )->toBeFalse();
} );

test( 'pattern browser uses default categories when none provided', function (): void {
	$component = new PatternBrowser();

	$slugs = array_column( $component->categories, 'slug' );
	expect( $slugs )->toContain( 'text', 'header', 'footer', 'call-to-action', 'gallery', 'testimonial' );
} );

test( 'pattern browser renders', function (): void {
	$view = $this->blade( '<x-ve-pattern-browser />' );
	expect( $view )->not->toBeNull();
} );

test( 'pattern browser renders search input when enabled', function (): void {
	$this->blade( '<x-ve-pattern-browser />' )
		->assertSee( 'search', false );
} );

test( 'pattern browser hides search input when disabled', function (): void {
	$this->blade( '<x-ve-pattern-browser :show-search="false" />' )
		->assertDontSee( 'x-model.debounce.300ms="search"', false );
} );

test( 'pattern browser renders explore all patterns button', function (): void {
	$this->blade( '<x-ve-pattern-browser />' )
		->assertSee( 'Explore all patterns' );
} );

test( 'pattern browser renders no patterns found message', function (): void {
	$this->blade( '<x-ve-pattern-browser />' )
		->assertSee( 'No patterns found.' );
} );
