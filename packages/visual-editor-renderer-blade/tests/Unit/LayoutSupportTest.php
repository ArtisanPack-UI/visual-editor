<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

describe( 'LayoutSupport::layoutClass', function (): void {
	it( 'resolves each supported layout type', function ( string $type ): void {
		expect( LayoutSupport::layoutClass( [ 'layout' => [ 'type' => $type ] ] ) )
			->toBe( 'is-layout-' . $type );
	} )->with( [ 'constrained', 'flex', 'flow', 'grid' ] );

	it( 'trims surrounding whitespace on the stored type', function (): void {
		expect( LayoutSupport::layoutClass( [ 'layout' => [ 'type' => '  constrained ' ] ] ) )
			->toBe( 'is-layout-constrained' );
	} );

	it( 'falls back to flow when no layout is stored', function (): void {
		expect( LayoutSupport::layoutClass( [] ) )->toBe( 'is-layout-flow' );
	} );

	it( 'falls back to the caller default when no layout is stored', function (): void {
		expect( LayoutSupport::layoutClass( [], 'constrained' ) )->toBe( 'is-layout-constrained' );
	} );

	it( 'refuses to mint a class from an unknown stored type', function ( mixed $type ): void {
		expect( LayoutSupport::layoutClass( [ 'layout' => [ 'type' => $type ] ] ) )
			->toBe( 'is-layout-flow' );
	} )->with( [
		'unknown string'  => 'sidebar',
		'injected token'  => 'flow" onload="alert(1)',
		'non-string type' => 12,
		'empty string'    => '',
	] );

	it( 'ignores an unsupported caller default rather than emitting it', function (): void {
		expect( LayoutSupport::layoutClass( [], 'sidebar' ) )->toBe( 'is-layout-flow' );
	} );
} );

describe( 'LayoutSupport::pair', function (): void {
	it( 'emits the shared modifier followed by the per-block compound', function (): void {
		expect( LayoutSupport::pair( 'group', 'is-layout-constrained' ) )
			->toBe( [ 'is-layout-constrained', 'wp-block-group-is-layout-constrained' ] );
	} );

	it( 'pairs every class it is handed', function (): void {
		expect( LayoutSupport::pair( 'post-template', 'is-layout-grid', 'is-layout-flow' ) )
			->toBe( [
				'is-layout-grid',
				'wp-block-post-template-is-layout-grid',
				'is-layout-flow',
				'wp-block-post-template-is-layout-flow',
			] );
	} );

	it( 'skips empty class tokens', function (): void {
		expect( LayoutSupport::pair( 'group', '' ) )->toBe( [] );
	} );

	it( 'returns an empty list when handed no classes', function (): void {
		expect( LayoutSupport::pair( 'group' ) )->toBe( [] );
	} );
} );

describe( 'LayoutSupport::wrapperForBlock', function (): void {
	it( 'resolves the type from attributes and returns the pair', function (): void {
		expect( LayoutSupport::wrapperForBlock( [ 'layout' => [ 'type' => 'constrained' ] ], 'post-content' ) )
			->toBe( [ 'is-layout-constrained', 'wp-block-post-content-is-layout-constrained' ] );
	} );

	it( 'honours the caller default when the block stores no layout', function (): void {
		expect( LayoutSupport::wrapperForBlock( [], 'group', 'flex' ) )
			->toBe( [ 'is-layout-flex', 'wp-block-group-is-layout-flex' ] );
	} );
} );

describe( 'LayoutSupport::container (#871)', function (): void {
	$css = static fn ( array $layout, string $layoutClass, string $slug = 'group' ): ?string => LayoutSupport::container( [ 'layout' => $layout ], $slug, $layoutClass )['css'] ?? null;

	it( 'caps constrained children at the block content and wide size', function () use ( $css ): void {
		expect( $css( [ 'type' => 'constrained', 'contentSize' => '640px', 'wideSize' => '1200px' ], 'is-layout-constrained' ) )
			->toContain( ' > :where(:not(.alignleft):not(.alignright):not(.alignfull)){max-width:640px;margin-left:auto !important;margin-right:auto !important;}' )
			->toContain( ' > .alignwide{max-width:1200px;}' );
	} );

	it( 'falls back to whichever constrained size is set', function () use ( $css ): void {
		expect( $css( [ 'contentSize' => '640px' ], 'is-layout-constrained' ) )
			->toContain( '{max-width:640px;margin-left' )
			->toContain( ' > .alignwide{max-width:640px;}' );

		expect( $css( [ 'wideSize' => '1200px' ], 'is-layout-constrained' ) )
			->toContain( '{max-width:1200px;margin-left' );
	} );

	it( 'honors constrained justification with or without a size', function () use ( $css ): void {
		expect( $css( [ 'contentSize' => '640px', 'justifyContent' => 'right' ], 'is-layout-constrained' ) )
			->toContain( 'margin-left:auto !important;margin-right:0 !important;' );

		expect( $css( [ 'justifyContent' => 'left' ], 'is-layout-constrained' ) )
			->toEndWith( ' > :where(:not(.alignleft):not(.alignright):not(.alignfull)){margin-left:0 !important;}' );

		expect( $css( [ 'justifyContent' => 'center' ], 'is-layout-constrained' ) )->toBeNull();
	} );

	it( 'drops unsafe or unbalanced lengths', function ( string $value ) use ( $css ): void {
		expect( $css( [ 'contentSize' => $value ], 'is-layout-constrained' ) )->toBeNull();
	} )->with( [
		'declaration break' => [ '640px;}body{color:red' ],
		'unbalanced paren'  => [ 'calc(100% - 2rem' ],
		'stray close paren' => [ '640px)' ],
		'comment'           => [ '640px/*' ],
		'blank'             => [ '   ' ],
	] );

	it( 'maps horizontal flex wrap, justification and vertical alignment', function () use ( $css ): void {
		expect( $css( [ 'type' => 'flex', 'flexWrap' => 'nowrap', 'justifyContent' => 'space-between', 'verticalAlignment' => 'bottom' ], 'is-layout-flex' ) )
			->toEndWith( '{flex-wrap:nowrap;justify-content:space-between;align-items:flex-end;}' );
	} );

	it( 'maps vertical flex onto a column with a flex-start default', function () use ( $css ): void {
		expect( $css( [ 'type' => 'flex', 'orientation' => 'vertical' ], 'is-layout-flex' ) )
			->toEndWith( '{flex-direction:column;align-items:flex-start;}' );

		expect( $css( [ 'type' => 'flex', 'orientation' => 'vertical', 'justifyContent' => 'stretch', 'verticalAlignment' => 'space-between' ], 'is-layout-flex' ) )
			->toEndWith( '{flex-direction:column;align-items:stretch;justify-content:space-between;}' );
	} );

	it( 'ignores flex options outside the orientation allowlist', function () use ( $css ): void {
		expect( $css( [ 'type' => 'flex', 'justifyContent' => 'stretch', 'verticalAlignment' => 'space-between' ], 'is-layout-flex' ) )->toBeNull();
		expect( $css( [ 'type' => 'flex', 'justifyContent' => 'red;}' ], 'is-layout-flex' ) )->toBeNull();
	} );

	it( 'builds the grid track list from the column count and minimum width', function () use ( $css ): void {
		expect( $css( [ 'type' => 'grid', 'columnCount' => 3 ], 'is-layout-grid' ) )
			->toEndWith( '{grid-template-columns:repeat(3, minmax(0, 1fr));}' );

		expect( $css( [ 'type' => 'grid', 'minimumColumnWidth' => '20rem' ], 'is-layout-grid' ) )
			->toEndWith( '{grid-template-columns:repeat(auto-fill, minmax(min(20rem, 100%), 1fr));container-type:inline-size;}' );

		expect( $css( [ 'type' => 'grid' ], 'is-layout-grid' ) )
			->toContain( 'minmax(min(12rem, 100%), 1fr)' );

		expect( $css( [ 'type' => 'grid', 'columnCount' => 4, 'minimumColumnWidth' => '14rem' ], 'is-layout-grid' ) )
			->toContain( 'minmax(max(min(14rem, 100%), (100% - (var(--wp--style--block-gap, 24px) * 3)) / 4), 1fr)' );
	} );

	it( 'accepts whole column counts and rejects the rest', function ( mixed $count, ?string $expected ) use ( $css ): void {
		$rule = $css( [ 'type' => 'grid', 'columnCount' => $count ], 'is-layout-grid' );

		expect( $rule )->toContain( $expected ?? 'auto-fill' );
	} )->with( [
		'int'          => [ 2, 'repeat(2, minmax(0, 1fr))' ],
		'digit string' => [ ' 5 ', 'repeat(5, minmax(0, 1fr))' ],
		'whole float'  => [ 3.0, 'repeat(3, minmax(0, 1fr))' ],
		'fraction'     => [ 2.5, null ],
		'zero'         => [ 0, null ],
		'negative'     => [ -2, null ],
		'over the cap' => [ 65, null ],
		'bool'         => [ true, null ],
	] );

	it( 'emits nothing for flow layouts', function () use ( $css ): void {
		expect( $css( [ 'type' => 'flow', 'contentSize' => '640px' ], 'is-layout-flow' ) )->toBeNull();
	} );

	it( 'scopes the selector past the shared containment rule', function (): void {
		$container = LayoutSupport::container( [ 'layout' => [ 'contentSize' => '640px' ] ], 'post-content', 'is-layout-constrained' );

		expect( $container['class'] )->toMatch( '/^wp-container-[0-9a-f]{12}$/' )
			->and( $container['css'] )->toStartWith( '.wp-block-post-content.wp-block-post-content-is-layout-constrained.' . $container['class'] . ' > ' );
	} );

	it( 'shares a scope between identical layouts and splits it across wrappers', function (): void {
		$attributes = [ 'layout' => [ 'type' => 'grid', 'columnCount' => 2 ] ];

		$first  = LayoutSupport::container( $attributes, 'group', 'is-layout-grid' );
		$second = LayoutSupport::container( $attributes, 'group', 'is-layout-grid' );
		$other  = LayoutSupport::container( $attributes, 'post-content', 'is-layout-grid' );

		expect( $first )->toBe( $second )
			->and( $other['class'] )->not->toBe( $first['class'] );
	} );

	it( 'pushes the rule into the responsive accumulator once per scope', function (): void {
		$accumulator = app( ResponsiveCssAccumulator::class );
		$accumulator->reset();

		$attributes = [ 'layout' => [ 'type' => 'grid', 'columnCount' => 2 ] ];
		$classes    = LayoutSupport::containerClasses( $attributes, 'group', 'is-layout-grid' );
		LayoutSupport::containerClasses( $attributes, 'group', 'is-layout-grid' );

		expect( $classes )->toHaveCount( 1 )
			->and( $accumulator->all() )->toHaveCount( 1 )
			->and( LayoutSupport::containerClasses( [], 'group', 'is-layout-flow' ) )->toBe( [] );
	} );
} );
