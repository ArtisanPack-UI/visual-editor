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
			->toContain( 'minmax(max(min(14rem, 100%), (100% - (var(--wp--style--block-gap, 0.5em) * 3)) / 4), 1fr)' );
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

describe( 'LayoutSupport::layoutClass legacy inherit (F-3)', function (): void {
	it( 'treats a legacy inherit layout as constrained', function (): void {
		expect( LayoutSupport::layoutClass( [ 'layout' => [ 'inherit' => true ] ] ) )->toBe( 'is-layout-constrained' );
	} );

	it( 'lets a stored type win over inherit', function (): void {
		expect( LayoutSupport::layoutClass( [ 'layout' => [ 'type' => 'flex', 'inherit' => true ] ] ) )->toBe( 'is-layout-flex' );
		expect( LayoutSupport::layoutClass( [ 'layout' => [ 'inherit' => 'yes' ] ] ) )->toBe( 'is-layout-flow' );
	} );
} );

describe( 'LayoutSupport::container block gap (M-15)', function (): void {
	$css = static fn ( array $attributes, string $layoutClass, string $slug = 'group' ): ?string => LayoutSupport::container( $attributes, $slug, $layoutClass )['css'] ?? null;

	it( 'scopes a flow group gap to its children, matching the shared golden', function (): void {
		$container = LayoutSupport::container( [ 'style' => [ 'spacing' => [ 'blockGap' => '5rem' ] ] ], 'group', 'is-layout-flow' );

		expect( $container )->toBe( [
			'class' => 'wp-container-f4f1e681cf25',
			'css'   => '.wp-block-group.wp-block-group-is-layout-flow.wp-container-f4f1e681cf25 > * + *{margin-block-start:5rem;margin-block-end:0;}',
		] );
	} );

	it( 'adds the gap to the flex and grid container rule', function () use ( $css ): void {
		expect( $css( [ 'layout' => [ 'flexWrap' => 'nowrap' ], 'style' => [ 'spacing' => [ 'blockGap' => 'var:preset|spacing|40' ] ] ], 'is-layout-flex' ) )
			->toEndWith( '{flex-wrap:nowrap;gap:var(--wp--preset--spacing--40);}' );

		expect( $css( [ 'style' => [ 'spacing' => [ 'blockGap' => '1rem' ] ] ], 'is-layout-flex' ) )
			->toEndWith( '{gap:1rem;}' );

		expect( $css( [ 'layout' => [ 'columnCount' => 2 ], 'style' => [ 'spacing' => [ 'blockGap' => '2rem' ] ] ], 'is-layout-grid' ) )
			->toEndWith( '{grid-template-columns:repeat(2, minmax(0, 1fr));gap:2rem;}' );
	} );

	it( 'combines a per-axis gap with the fallback for the unset side', function () use ( $css ): void {
		expect( $css( [ 'style' => [ 'spacing' => [ 'blockGap' => [ 'top' => '1rem' ] ] ] ], 'is-layout-flex' ) )
			->toEndWith( '{gap:1rem 0.5em;}' );

		expect( $css( [ 'style' => [ 'spacing' => [ 'blockGap' => [ 'top' => '2rem', 'left' => '2rem' ] ] ] ], 'is-layout-flex' ) )
			->toEndWith( '{gap:2rem;}' );

		expect( LayoutSupport::container( [ 'style' => [ 'spacing' => [ 'blockGap' => [ 'left' => '3rem' ] ] ] ], 'columns', 'is-layout-flex', null, '2em' )['css'] )
			->toEndWith( '{gap:2em 3rem;}' );

		expect( $css( [ 'style' => [ 'spacing' => [ 'blockGap' => [] ] ] ], 'is-layout-flex' ) )->toBeNull();
	} );

	it( 'spaces flow and constrained children with the top side only', function () use ( $css ): void {
		expect( $css( [ 'style' => [ 'spacing' => [ 'blockGap' => [ 'top' => '2rem', 'left' => '1rem' ] ] ] ], 'is-layout-constrained' ) )
			->toBe( $css( [ 'style' => [ 'spacing' => [ 'blockGap' => '2rem' ] ] ], 'is-layout-constrained' ) );

		expect( $css( [ 'style' => [ 'spacing' => [ 'blockGap' => [ 'left' => '1rem' ] ] ] ], 'is-layout-flow' ) )->toBeNull();
	} );

	it( 'drops a gap that could add declarations (F-10)', function ( mixed $blockGap ) use ( $css ): void {
		expect( $css( [ 'style' => [ 'spacing' => [ 'blockGap' => $blockGap ] ] ], 'is-layout-flex' ) )->toBeNull()
			->and( $css( [ 'style' => [ 'spacing' => [ 'blockGap' => $blockGap ] ] ], 'is-layout-flow' ) )->toBeNull();
	} )->with( [
		'declaration break' => [ '1rem;position:fixed;inset:0' ],
		'rule break'        => [ '2rem}body{color:red' ],
		'unbalanced paren'  => [ 'calc(1rem' ],
		'stray close paren' => [ '1rem)' ],
		'comment'           => [ '1rem/*' ],
		'blank'             => [ '   ' ],
		'boolean'           => [ true ],
	] );

	it( 'keys a custom wrapper class into the selector and the hash', function (): void {
		$attributes = [ 'style' => [ 'spacing' => [ 'blockGap' => '3rem' ] ] ];
		$inner      = LayoutSupport::container( $attributes, 'cover', 'is-layout-flow', 'wp-block-cover__inner-container' );
		$outer      = LayoutSupport::container( $attributes, 'cover', 'is-layout-flow' );

		expect( $inner['css'] )->toStartWith( '.wp-block-cover__inner-container.wp-block-cover-is-layout-flow.' . $inner['class'] . ' > * + *' )
			->and( $inner['class'] )->not->toBe( $outer['class'] );
	} );
} );

describe( 'LayoutSupport::blockGapValue (F-10)', function (): void {
	it( 'expands preset references and keeps safe values', function (): void {
		expect( LayoutSupport::blockGapValue( 'var:preset|spacing|40' ) )->toBe( 'var(--wp--preset--spacing--40)' )
			->and( LayoutSupport::blockGapValue( ' 1.5rem ' ) )->toBe( '1.5rem' )
			->and( LayoutSupport::blockGapValue( 'calc(1rem + 2px)' ) )->toBe( 'calc(1rem + 2px)' )
			->and( LayoutSupport::blockGapValue( 12 ) )->toBe( '12' );
	} );

	it( 'rejects values that could break out of the declaration', function ( mixed $value ): void {
		expect( LayoutSupport::blockGapValue( $value ) )->toBeNull();
	} )->with( [
		'semicolon'      => [ '1rem;position:fixed' ],
		'brace'          => [ '1rem}' ],
		'protocol slash' => [ 'url(//x)' ],
		'unbalanced'     => [ 'calc(1rem' ],
		'empty'          => [ '' ],
		'null'           => [ null ],
		'array'          => [ [] ],
	] );
} );

describe( 'LayoutSupport::columnsGapDeclaration (#914)', function (): void {
	it( 'hands a per-axis left gap to the Column width calc', function ( mixed $left, string $expected ): void {
		$attributes = [ 'style' => [ 'spacing' => [ 'blockGap' => [ 'top' => '0', 'left' => $left ] ] ] ];

		expect( LayoutSupport::columnsGapDeclaration( $attributes ) )->toBe( '--ap-columns-gap: ' . $expected );
	} )->with( [
		'unitless zero' => [ '0', '0px' ],
		'integer zero'  => [ 0, '0px' ],
		'length'        => [ '4rem', '4rem' ],
		'preset'        => [ 'var:preset|spacing|40', 'var(--wp--preset--spacing--40)' ],
	] );

	it( 'returns null without a usable per-axis left gap', function ( mixed $blockGap ): void {
		expect( LayoutSupport::columnsGapDeclaration( [ 'style' => [ 'spacing' => [ 'blockGap' => $blockGap ] ] ] ) )->toBeNull();
	} )->with( [
		'top only'       => [ [ 'top' => '1rem' ] ],
		'unsafe left'    => [ [ 'top' => '1rem', 'left' => '1rem;position:fixed' ] ],
		'bare number'    => [ [ 'left' => '12' ] ],
		'string gap'     => [ '2rem' ],
		'unset'          => [ null ],
		'keyword'        => [ [ 'left' => 'inherit' ] ],
		'auto'           => [ [ 'left' => 'auto' ] ],
		'string keyword' => [ 'unset' ],
	] );

	it( 'pins a normal gap to 0px, since the width calc needs a length', function ( mixed $blockGap ): void {
		expect( LayoutSupport::columnsGapDeclaration( [ 'style' => [ 'spacing' => [ 'blockGap' => $blockGap ] ] ] ) )
			->toBe( '--ap-columns-gap: 0px' );
	} )->with( [
		'per-axis'      => [ [ 'top' => '1rem', 'left' => 'normal' ] ],
		'upper case'    => [ [ 'left' => 'NORMAL' ] ],
		'string gap'    => [ 'Normal' ],
	] );
} );

describe( 'LayoutSupport::modifierClasses (F-3)', function (): void {
	it( 'adds the orientation, justification and wrap classes WordPress adds', function (): void {
		expect( LayoutSupport::modifierClasses( [ 'layout' => [ 'orientation' => 'vertical', 'justifyContent' => 'space-between', 'flexWrap' => 'nowrap' ] ] ) )
			->toBe( [ 'is-vertical', 'is-content-justification-space-between', 'is-nowrap' ] )
			->and( LayoutSupport::modifierClasses( [ 'layout' => [ 'orientation' => 'vertical', 'justifyContent' => 'left' ] ], false, false ) )
			->toBe( [] );
	} );

	it( 'mints classes only from allowlisted values', function (): void {
		expect( LayoutSupport::modifierClasses( [ 'layout' => [ 'orientation' => 'diagonal" x', 'justifyContent' => 'center x', 'flexWrap' => 'wrap' ] ] ) )
			->toBe( [] )
			->and( LayoutSupport::modifierClasses( [ 'layout' => 'grid' ] ) )->toBe( [] );
	} );
} );
