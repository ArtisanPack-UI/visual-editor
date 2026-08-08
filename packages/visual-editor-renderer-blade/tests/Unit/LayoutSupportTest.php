<?php

declare( strict_types=1 );

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
