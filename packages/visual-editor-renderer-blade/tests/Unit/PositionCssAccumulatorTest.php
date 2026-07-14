<?php

/**
 * Unit tests for {@see PositionCssAccumulator} (#640).
 *
 * @since 1.4.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Services\PositionCssAccumulator;

describe( 'PositionCssAccumulator', function (): void {
	it( 'dedupes identical (scope, rules) pushes', function (): void {
		$accumulator = new PositionCssAccumulator();
		$accumulator->push( '.ve-pos-abc', '.ve-pos-abc{position:sticky !important}' );
		$accumulator->push( '.ve-pos-abc', '.ve-pos-abc{position:sticky !important}' );

		$flushed = $accumulator->flush();
		// One occurrence of the rule inside a single `<style data-ve-position>` block.
		expect( substr_count( $flushed, 'position:sticky !important' ) )->toBe( 1 );
	} );

	it( 'keeps BOTH entries when the same scope arrives with different rules', function (): void {
		// Regression: prior scope-only keying dropped the second push
		// entirely, so a `_positionScopeId` collision (paste races,
		// legacy content) made one block silently inherit the other's
		// position. Now both entries emit; CSS cascade decides.
		$accumulator = new PositionCssAccumulator();
		$accumulator->push( '.ve-pos-abc', '.ve-pos-abc{position:sticky !important}' );
		$accumulator->push( '.ve-pos-abc', '.ve-pos-abc{position:absolute !important}' );

		$flushed = $accumulator->flush();
		expect( $flushed )->toContain( 'position:sticky !important' );
		expect( $flushed )->toContain( 'position:absolute !important' );
	} );

	it( 'drops empty scope or empty rules silently', function (): void {
		$accumulator = new PositionCssAccumulator();
		$accumulator->push( '', 'something' );
		$accumulator->push( '.ve-pos-x', '' );

		expect( $accumulator->flush() )->toBe( '' );
	} );

	it( 'clears state on flush', function (): void {
		$accumulator = new PositionCssAccumulator();
		$accumulator->push( '.ve-pos-x', '.ve-pos-x{position:sticky}' );
		expect( $accumulator->flush() )->not->toBe( '' );
		expect( $accumulator->flush() )->toBe( '' );
	} );
} );
