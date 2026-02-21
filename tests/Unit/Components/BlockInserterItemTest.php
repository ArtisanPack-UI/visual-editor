<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\BlockInserterItem;

test( 'block inserter item can be instantiated with defaults', function (): void {
	$component = new BlockInserterItem();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->name )->toBe( '' );
	expect( $component->label )->toBeNull();
	expect( $component->description )->toBeNull();
	expect( $component->icon )->toBeNull();
	expect( $component->category )->toBe( 'text' );
	expect( $component->draggable )->toBeTrue();
} );

test( 'block inserter item accepts custom props', function (): void {
	$component = new BlockInserterItem(
		id: 'paragraph',
		name: 'paragraph',
		label: 'Paragraph',
		description: 'A paragraph block',
		icon: 'o-document-text',
		category: 'text',
		draggable: false,
	);

	expect( $component->uuid )->toContain( 'paragraph' );
	expect( $component->name )->toBe( 'paragraph' );
	expect( $component->label )->toBe( 'Paragraph' );
	expect( $component->description )->toBe( 'A paragraph block' );
	expect( $component->icon )->toBe( 'o-document-text' );
	expect( $component->category )->toBe( 'text' );
	expect( $component->draggable )->toBeFalse();
} );

test( 'block inserter item renders', function (): void {
	$view = $this->blade( '<x-ve-block-inserter-item name="paragraph" />' );
	expect( $view )->not->toBeNull();
} );

test( 'block inserter item renders with option role', function (): void {
	$this->blade( '<x-ve-block-inserter-item name="paragraph" />' )
		->assertSee( 'role="option"', false );
} );

test( 'block inserter item renders with aria label', function (): void {
	$this->blade( '<x-ve-block-inserter-item name="paragraph" label="Paragraph" />' )
		->assertSee( 'aria-label', false );
} );

test( 'block inserter item renders draggable by default', function (): void {
	$this->blade( '<x-ve-block-inserter-item name="paragraph" />' )
		->assertSee( 'draggable="true"', false );
} );
