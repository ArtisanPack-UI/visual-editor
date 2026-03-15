<?php

declare( strict_types=1 );

use Tests\Unit\Blocks\Stubs\StubDynamicBlock;

test( 'dynamic block is dynamic', function (): void {
	$block = new StubDynamicBlock();

	expect( $block->isDynamic() )->toBeTrue();
} );

test( 'dynamic block returns component class', function (): void {
	$block = new StubDynamicBlock();

	expect( $block->getComponent() )->toBe( 'App\\Livewire\\StubDynamicComponent' );
} );

test( 'dynamic block returns component tag', function (): void {
	$block = new StubDynamicBlock();

	expect( $block->getComponentTag() )->toBe( 'visual-editor.blocks.stub-dynamic-component' );
} );

test( 'dynamic block toArray includes dynamic metadata', function (): void {
	$block = new StubDynamicBlock();
	$array = $block->toArray();

	expect( $array['dynamic'] )->toBeTrue()
		->and( $array['component'] )->toBe( 'App\\Livewire\\StubDynamicComponent' )
		->and( $array['componentTag'] )->toBe( 'visual-editor.blocks.stub-dynamic-component' );
} );

test( 'dynamic block has correct category', function (): void {
	$block = new StubDynamicBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'dynamic block has content schema', function (): void {
	$block = new StubDynamicBlock();

	expect( $block->getContentSchema() )->toHaveKey( 'count' )
		->and( $block->getContentSchema()['count']['type'] )->toBe( 'range' );
} );

test( 'dynamic block extracts default content', function (): void {
	$block = new StubDynamicBlock();

	expect( $block->getDefaultContent() )->toBe( [
		'count' => 5,
	] );
} );

test( 'dynamic block does not support inner blocks by default', function (): void {
	$block = new StubDynamicBlock();

	expect( $block->supportsInnerBlocks() )->toBeFalse();
} );

test( 'dynamic block does not have JS renderer by default', function (): void {
	$block = new StubDynamicBlock();

	expect( $block->hasJsRenderer() )->toBeFalse();
} );
