<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\BlockInserter;

test( 'block inserter can be instantiated with defaults', function (): void {
	$component = new BlockInserter();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->mode )->toBe( 'panel' );
	expect( $component->blocks )->toBe( [] );
	expect( $component->categories )->toBe( BlockInserter::DEFAULT_CATEGORIES );
	expect( $component->showSearch )->toBeTrue();
	expect( $component->showCategories )->toBeTrue();
	expect( $component->showRecentlyUsed )->toBeTrue();
	expect( $component->recentlyUsedMax )->toBe( 6 );
	expect( $component->enableDragToInsert )->toBeTrue();
	expect( $component->insertAt )->toBeNull();
} );

test( 'block inserter accepts custom props', function (): void {
	$blocks = [
		[ 'name' => 'paragraph', 'label' => 'Paragraph', 'category' => 'text' ],
	];
	$component = new BlockInserter(
		id: 'inserter',
		mode: 'inline',
		blocks: $blocks,
		categories: [ 'text', 'media' ],
		showSearch: false,
		showCategories: false,
		showRecentlyUsed: false,
		recentlyUsedMax: 3,
		enableDragToInsert: false,
		insertAt: 5,
	);

	expect( $component->uuid )->toContain( 'inserter' );
	expect( $component->mode )->toBe( 'inline' );
	expect( $component->blocks )->toBe( $blocks );
	expect( $component->categories )->toBe( [ 'text', 'media' ] );
	expect( $component->showSearch )->toBeFalse();
	expect( $component->showCategories )->toBeFalse();
	expect( $component->showRecentlyUsed )->toBeFalse();
	expect( $component->recentlyUsedMax )->toBe( 3 );
	expect( $component->enableDragToInsert )->toBeFalse();
	expect( $component->insertAt )->toBe( 5 );
} );

test( 'block inserter falls back to panel for invalid mode', function (): void {
	$component = new BlockInserter( mode: 'invalid' );

	expect( $component->mode )->toBe( 'panel' );
} );

test( 'block inserter uses default categories when empty', function (): void {
	$component = new BlockInserter( categories: [] );

	expect( $component->categories )->toBe( BlockInserter::DEFAULT_CATEGORIES );
} );

test( 'block inserter renders', function (): void {
	$view = $this->blade( '<x-ve-block-inserter />' );
	expect( $view )->not->toBeNull();
} );

test( 'block inserter renders with region role', function (): void {
	$this->blade( '<x-ve-block-inserter />' )
		->assertSee( 'role="region"', false );
} );

test( 'block inserter renders with list role', function (): void {
	$this->blade( '<x-ve-block-inserter />' )
		->assertSee( 'role="list"', false );
} );

test( 'block inserter renders search by default', function (): void {
	$this->blade( '<x-ve-block-inserter />' )
		->assertSee( 'Search blocks' );
} );

test( 'block inserter hides search when disabled', function (): void {
	$this->blade( '<x-ve-block-inserter :show-search="false" />' )
		->assertDontSee( 'Search blocks' );
} );
