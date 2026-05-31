<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;
use Illuminate\Support\Facades\Blade;

beforeEach( function (): void {
	app( ResponsiveCssAccumulator::class )->reset();
} );

it( 'consolidates spacing overrides into a single <style data-ve-responsive> block at the top', function () {
	$tree = [
		[
			'clientId'   => 'cols-1',
			'name'       => 'artisanpack/columns',
			'attributes' => [
				'responsive' => [
					'style.spacing.padding' => [ 'md' => '2rem' ],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	// One consolidated style block, not one per scope class.
	expect( $rendered )->toContain( '<style data-ve-responsive>' );
	expect( substr_count( $rendered, '<style data-ve-responsive>' ) )->toBe( 1 );
	expect( $rendered )->toContain( '@media (min-width:768px)' );
	expect( $rendered )->toContain( '{padding:2rem!important}' );
	expect( $rendered )->toMatch( '/class="wp-block-columns is-stacked-on-mobile ve-r-[a-f0-9]+"/' );

	// Style block lives BEFORE the column wrapper, not inside it.
	$styleEnd      = strpos( $rendered, '</style>' );
	$wrapperOpens  = strpos( $rendered, '<div class="wp-block-columns' );
	expect( $styleEnd )->toBeLessThan( $wrapperOpens );

	// No per-block `<style data-ve-r="..">` tags remain inside the
	// flex container — the whole point of #509.
	expect( $rendered )->not->toContain( '<style data-ve-r=' );
} );

it( 'consolidates columnCount overrides through the same accumulator', function () {
	$tree = [
		[
			'clientId'   => 'cols-1',
			'name'       => 'artisanpack/columns',
			'attributes' => [
				'responsive' => [
					'columnCount' => [ 'md' => 4 ],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	expect( $rendered )->toContain( '<style data-ve-responsive>' );
	expect( $rendered )->toContain( 'grid-template-columns:repeat(4,minmax(0,1fr))!important' );
	expect( $rendered )->toMatch( '/class="wp-block-columns is-stacked-on-mobile ve-cols-[a-f0-9]+"/' );
} );

it( 'merges spacing + columnCount + multiple blocks into one style block', function () {
	$tree = [
		[
			'clientId'   => 'cols-1',
			'name'       => 'artisanpack/columns',
			'attributes' => [
				'responsive' => [
					'style.spacing.padding' => [ 'md' => '2rem' ],
					'columnCount'           => [ 'md' => 4 ],
				],
			],
			'innerBlocks' => [],
		],
		[
			'clientId'   => 'cols-2',
			'name'       => 'artisanpack/columns',
			'attributes' => [
				'responsive' => [
					'style.spacing.padding' => [ 'lg' => '4rem' ],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	// One and only one consolidated style block, even though
	// three distinct rule sets contributed to it.
	expect( substr_count( $rendered, '<style data-ve-responsive>' ) )->toBe( 1 );
	expect( $rendered )->toContain( '{padding:2rem!important}' );
	expect( $rendered )->toContain( '{padding:4rem!important}' );
	expect( $rendered )->toContain( 'grid-template-columns:repeat(4' );
} );

it( 'skips the consolidated style block when no responsive overrides are present', function () {
	$tree = [
		[
			'clientId'    => 'cols-1',
			'name'        => 'artisanpack/columns',
			'attributes'  => [],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	expect( $rendered )->not->toContain( '<style data-ve-responsive>' );
	expect( $rendered )->not->toContain( '<style data-ve-r=' );
} );

it( 'skips invalid columnCount overrides (zero / negative)', function () {
	$tree = [
		[
			'clientId'   => 'cols-1',
			'name'       => 'artisanpack/columns',
			'attributes' => [
				'responsive' => [
					'columnCount' => [ 'md' => 0 ],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	expect( $rendered )->not->toContain( 'grid-template-columns' );
} );
