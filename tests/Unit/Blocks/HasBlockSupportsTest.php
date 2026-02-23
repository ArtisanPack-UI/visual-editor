<?php

declare( strict_types=1 );

use Tests\Unit\Blocks\Stubs\StubBlock;

test( 'supports feature returns true for supported feature', function (): void {
	$block = new StubBlock();

	expect( $block->supportsFeature( 'align' ) )->toBeTrue();
} );

test( 'supports feature returns false for unsupported feature', function (): void {
	$block = new StubBlock();

	expect( $block->supportsFeature( 'border' ) )->toBeFalse();
} );

test( 'supports feature checks nested features with dot notation', function (): void {
	$block = new StubBlock();

	expect( $block->supportsFeature( 'color.text' ) )->toBeTrue();
	expect( $block->supportsFeature( 'color.background' ) )->toBeFalse();
	expect( $block->supportsFeature( 'typography.fontSize' ) )->toBeTrue();
	expect( $block->supportsFeature( 'typography.fontFamily' ) )->toBeFalse();
} );

test( 'supports feature returns false for nonexistent feature', function (): void {
	$block = new StubBlock();

	expect( $block->supportsFeature( 'nonexistent' ) )->toBeFalse();
	expect( $block->supportsFeature( 'color.nonexistent' ) )->toBeFalse();
} );

test( 'get supported alignments returns all alignments when align is true', function (): void {
	$block = new StubBlock();

	$alignments = $block->getSupportedAlignments();

	expect( $alignments )->toBe( [ 'left', 'center', 'right', 'wide', 'full' ] );
} );

test( 'get supports returns full supports array', function (): void {
	$block    = new StubBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'align' );
	expect( $supports )->toHaveKey( 'color' );
	expect( $supports )->toHaveKey( 'typography' );
	expect( $supports )->toHaveKey( 'spacing' );
	expect( $supports )->toHaveKey( 'border' );
	expect( $supports )->toHaveKey( 'anchor' );
	expect( $supports )->toHaveKey( 'htmlId' );
	expect( $supports )->toHaveKey( 'className' );
} );
