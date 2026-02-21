<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\BlockInserterCategory;

test( 'block inserter category can be instantiated with defaults', function (): void {
	$component = new BlockInserterCategory();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->name )->toBe( '' );
	expect( $component->label )->toBeNull();
	expect( $component->icon )->toBeNull();
	expect( $component->count )->toBe( 0 );
} );

test( 'block inserter category accepts custom props', function (): void {
	$component = new BlockInserterCategory(
		id: 'cat-text',
		name: 'text',
		label: 'Text Blocks',
		icon: 'o-document-text',
		count: 5,
	);

	expect( $component->uuid )->toContain( 'cat-text' );
	expect( $component->name )->toBe( 'text' );
	expect( $component->label )->toBe( 'Text Blocks' );
	expect( $component->icon )->toBe( 'o-document-text' );
	expect( $component->count )->toBe( 5 );
} );

test( 'block inserter category renders', function (): void {
	$view = $this->blade( '<x-ve-block-inserter-category name="text">Items</x-ve-block-inserter-category>' );
	expect( $view )->not->toBeNull();
} );

test( 'block inserter category renders with group role', function (): void {
	$this->blade( '<x-ve-block-inserter-category name="text">Items</x-ve-block-inserter-category>' )
		->assertSee( 'role="group"', false );
} );

test( 'block inserter category renders with slot content', function (): void {
	$this->blade( '<x-ve-block-inserter-category name="text">Category Items</x-ve-block-inserter-category>' )
		->assertSee( 'Category Items' );
} );
