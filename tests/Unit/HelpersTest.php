<?php

declare( strict_types=1 );

use Tests\Unit\Blocks\Stubs\StubBlock;

test( 'veRegisterBlock registers a block', function (): void {
	$block = new StubBlock();

	veRegisterBlock( $block );

	expect( veBlockExists( 'stub' ) )->toBeTrue();
} );

test( 'veBlockExists returns false for unregistered block', function (): void {
	expect( veBlockExists( 'nonexistent' ) )->toBeFalse();
} );

test( 'veGetBlock returns registered block', function (): void {
	$block = new StubBlock();

	veRegisterBlock( $block );

	expect( veGetBlock( 'stub' ) )->toBe( $block );
} );

test( 'veGetBlock returns null for unregistered block', function (): void {
	expect( veGetBlock( 'nonexistent' ) )->toBeNull();
} );

test( 'visualEditor helper returns VisualEditor instance', function (): void {
	expect( visualEditor() )->toBeInstanceOf( ArtisanPackUI\VisualEditor\VisualEditor::class );
} );
