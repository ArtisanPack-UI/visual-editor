<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;
use Illuminate\Support\Facades\Blade;

beforeEach( function (): void {
	app( ResponsiveCssAccumulator::class )->reset();
} );

it( 'emits per-breakpoint grid column classes on the wrapper from attributes.responsive.numColumns', function () {
	$tree = [
		[
			'clientId'   => 'grid-1',
			'name'       => 'artisanpack/grid',
			'attributes' => [
				'numColumns' => 1,
				'responsive' => [
					'numColumns' => [
						'md' => 2,
						'lg' => 4,
					],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	expect( $rendered )->toContain( 'ap-grid-has-1-base-columns' )
		->and( $rendered )->toContain( 'ap-grid-has-2-md-columns' )
		->and( $rendered )->toContain( 'ap-grid-has-4-lg-columns' )
		// Static-class approach: no accumulated <style data-ve-responsive>
		// block is emitted just for the grid family.
		->and( $rendered )->not->toContain( '<style data-ve-responsive>' );
} );

it( 'emits per-breakpoint span classes on grid-item from responsive.gridColumnSpan and gridRowSpan', function () {
	$tree = [
		[
			'clientId'   => 'item-1',
			'name'       => 'artisanpack/grid-item',
			'attributes' => [
				'gridColumnSpan' => 1,
				'gridRowSpan'    => 1,
				'responsive'     => [
					'gridColumnSpan' => [ 'md' => 2, 'lg' => 3 ],
					'gridRowSpan'    => [ 'md' => 2 ],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	expect( $rendered )->toContain( 'ap-grid-item-span-1-base-columns' )
		->and( $rendered )->toContain( 'ap-grid-item-span-1-base-row' )
		->and( $rendered )->toContain( 'ap-grid-item-span-2-md-columns' )
		->and( $rendered )->toContain( 'ap-grid-item-span-3-lg-columns' )
		->and( $rendered )->toContain( 'ap-grid-item-span-2-md-row' );
} );

it( 'skips overrides at unknown breakpoints', function () {
	$tree = [
		[
			'clientId'   => 'grid-1',
			'name'       => 'artisanpack/grid',
			'attributes' => [
				'numColumns' => 2,
				'responsive' => [
					'numColumns' => [ 'orphan' => 6 ],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	expect( $rendered )->toContain( 'ap-grid-has-2-base-columns' )
		->and( $rendered )->not->toContain( '-orphan-' );
} );
