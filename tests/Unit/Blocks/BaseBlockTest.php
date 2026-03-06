<?php

declare( strict_types=1 );

use Tests\Unit\Blocks\Stubs\StubBlock;
use Tests\Unit\Blocks\Stubs\StubContainerBlock;

test( 'base block returns correct type', function (): void {
	$block = new StubBlock();

	expect( $block->getType() )->toBe( 'stub' );
} );

test( 'base block returns correct name', function (): void {
	$block = new StubBlock();

	expect( $block->getName() )->toBe( 'Stub Block' );
} );

test( 'base block returns correct description', function (): void {
	$block = new StubBlock();

	expect( $block->getDescription() )->toBe( 'A stub block for testing' );
} );

test( 'base block returns correct icon', function (): void {
	$block = new StubBlock();

	expect( $block->getIcon() )->toBe( 'cube' );
} );

test( 'base block returns correct category', function (): void {
	$block = new StubBlock();

	expect( $block->getCategory() )->toBe( 'text' );
} );

test( 'base block returns correct keywords', function (): void {
	$block = new StubBlock();

	expect( $block->getKeywords() )->toBe( [ 'test', 'stub' ] );
} );

test( 'base block extracts default content from schema', function (): void {
	$block = new StubBlock();

	$defaults = $block->getDefaultContent();

	expect( $defaults )->toBe( [
		'text'  => 'Hello',
		'level' => 'h2',
	] );
} );

test( 'base block extracts default styles from schema', function (): void {
	$block = new StubBlock();

	$defaults = $block->getDefaultStyles();

	expect( $defaults )->toBe( [
		'alignment' => 'left',
	] );
} );

test( 'base block returns null for allowed parents by default', function (): void {
	$block = new StubBlock();

	expect( $block->getAllowedParents() )->toBeNull();
} );

test( 'base block returns null for allowed children by default', function (): void {
	$block = new StubBlock();

	expect( $block->getAllowedChildren() )->toBeNull();
} );

test( 'base block returns empty variations by default', function (): void {
	$block = new StubBlock();

	expect( $block->getVariations() )->toBe( [] );
} );

test( 'base block returns transforms', function (): void {
	$block = new StubBlock();

	expect( $block->getTransforms() )->toHaveKey( 'paragraph' );
} );

test( 'base block returns version 1 by default', function (): void {
	$block = new StubBlock();

	expect( $block->getVersion() )->toBe( 1 );
} );

test( 'base block migrate returns content unchanged by default', function (): void {
	$block   = new StubBlock();
	$content = [ 'text' => 'test' ];

	expect( $block->migrate( $content, 1 ) )->toBe( $content );
} );

test( 'base block is public by default', function (): void {
	$block = new StubBlock();

	expect( $block->isPublic() )->toBeTrue();
} );

test( 'base block advanced schema includes anchor and className', function (): void {
	$block  = new StubBlock();
	$schema = $block->getAdvancedSchema();

	expect( $schema )->toHaveKey( 'anchor' );
	expect( $schema )->toHaveKey( 'className' );
} );

test( 'base block advanced schema maps htmlId support to anchor key', function (): void {
	$block  = new StubBlock();
	$schema = $block->getAdvancedSchema();

	expect( $schema )->toHaveKey( 'anchor' );
	expect( $schema['anchor']['type'] )->toBe( 'text' );
} );

test( 'base block does not support inner blocks by default', function (): void {
	$block = new StubBlock();

	expect( $block->supportsInnerBlocks() )->toBeFalse();
} );

test( 'base block returns vertical inner blocks orientation by default', function (): void {
	$block = new StubBlock();

	expect( $block->getInnerBlocksOrientation() )->toBe( 'vertical' );
} );

test( 'base block does not have JS renderer by default', function (): void {
	$block = new StubBlock();

	expect( $block->hasJsRenderer() )->toBeFalse();
} );

test( 'container block supports inner blocks', function (): void {
	$block = new StubContainerBlock();

	expect( $block->supportsInnerBlocks() )->toBeTrue()
		->and( $block->hasJsRenderer() )->toBeTrue()
		->and( $block->getInnerBlocksOrientation() )->toBe( 'horizontal' );
} );

test( 'base block serializes to array', function (): void {
	$block = new StubBlock();
	$array = $block->toArray();

	expect( $array )->toHaveKeys( [
		'type', 'name', 'description', 'icon', 'category',
		'keywords', 'public', 'supportsInnerBlocks',
		'innerBlocksOrientation', 'allowedChildren',
		'allowedParents', 'hasJsRenderer', 'hasCustomInspector',
		'hasCustomToolbar', 'alignments',
	] )
		->and( $array['type'] )->toBe( 'stub' )
		->and( $array['supportsInnerBlocks'] )->toBeFalse()
		->and( $array['hasJsRenderer'] )->toBeFalse();
} );

test( 'container block toArray includes inner blocks metadata', function (): void {
	$block = new StubContainerBlock();
	$array = $block->toArray();

	expect( $array['supportsInnerBlocks'] )->toBeTrue()
		->and( $array['hasJsRenderer'] )->toBeTrue()
		->and( $array['innerBlocksOrientation'] )->toBe( 'horizontal' )
		->and( $array['allowedChildren'] )->toBe( [ 'stub', 'paragraph' ] );
} );
