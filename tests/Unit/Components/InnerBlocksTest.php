<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\InnerBlocks;

test( 'inner blocks can be instantiated with defaults', function (): void {
	$component = new InnerBlocks();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->allowedBlocks )->toBeNull();
	expect( $component->orientation )->toBe( 'vertical' );
	expect( $component->placeholder )->toBeNull();
	expect( $component->parentId )->toBeNull();
	expect( $component->innerBlocks )->toBe( [] );
	expect( $component->editing )->toBeFalse();
} );

test( 'inner blocks accepts custom props', function (): void {
	$component = new InnerBlocks(
		id: 'quote-inner',
		allowedBlocks: [ 'paragraph', 'list' ],
		orientation: 'horizontal',
		placeholder: 'Add content...',
		parentId: 'block-123',
		innerBlocks: [ '<p>Hello</p>' ],
		editing: true,
	);

	expect( $component->uuid )->toContain( 'quote-inner' );
	expect( $component->allowedBlocks )->toBe( [ 'paragraph', 'list' ] );
	expect( $component->orientation )->toBe( 'horizontal' );
	expect( $component->placeholder )->toBe( 'Add content...' );
	expect( $component->parentId )->toBe( 'block-123' );
	expect( $component->innerBlocks )->toBe( [ '<p>Hello</p>' ] );
	expect( $component->editing )->toBeTrue();
} );

test( 'inner blocks normalizes invalid orientation to vertical', function (): void {
	$component = new InnerBlocks( orientation: 'diagonal' );

	expect( $component->orientation )->toBe( 'vertical' );
} );

test( 'inner blocks renders with no blocks in edit mode', function (): void {
	$view = $this->blade(
		'<x-ve-inner-blocks :editing="true" />',
	);

	$view->assertSee( 'data-ve-inner-blocks', false );
	$view->assertSee( 've-inner-blocks-placeholder', false );
} );

test( 'inner blocks renders provided blocks in edit mode', function (): void {
	$view = $this->blade(
		'<x-ve-inner-blocks :inner-blocks="$innerBlocks" :editing="true" />',
		[ 'innerBlocks' => [ '<p>Block content</p>' ] ],
	);

	$view->assertSee( 'Block content', false );
	$view->assertDontSee( 've-inner-blocks-placeholder', false );
} );

test( 'inner blocks passes parent id as data attribute', function (): void {
	$view = $this->blade(
		'<x-ve-inner-blocks parent-id="block-abc" :editing="true" />',
	);

	$view->assertSee( 'data-parent-id="block-abc"', false );
} );

test( 'inner blocks passes allowed blocks as data attribute', function (): void {
	$view = $this->blade(
		'<x-ve-inner-blocks :allowed-blocks="$allowed" :editing="true" />',
		[ 'allowed' => [ 'paragraph', 'list' ] ],
	);

	$view->assertSee( 'data-allowed-blocks', false );
} );

test( 'inner blocks renders in save mode without editor attributes', function (): void {
	$view = $this->blade(
		'<x-ve-inner-blocks :inner-blocks="$innerBlocks" />',
		[ 'innerBlocks' => [ '<p>Saved content</p>' ] ],
	);

	$view->assertSee( 'Saved content', false );
	$view->assertDontSee( 'data-ve-inner-blocks', false );
	$view->assertDontSee( 've-inner-blocks-placeholder', false );
} );
