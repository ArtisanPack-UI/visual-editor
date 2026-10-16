<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditorRendererBlade\Support\FlexLayoutStyles;

/**
 * Comments and whitespace stripped, so the generated rules can be
 * compared with the static stylesheet's.
 */
function flexLayoutRules( string $css ): string
{
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );

	return trim( (string) preg_replace( '/\s+/', ' ', $css ) );
}

describe( 'FlexLayoutStyles (#895)', function (): void {
	it( 'builds exactly the static stylesheet\'s rules from the default breakpoints', function (): void {
		$static = (string) file_get_contents( dirname( __DIR__, 2 ) . '/resources/assets/frontend/flex-layout.css' );

		expect( flexLayoutRules( FlexLayoutStyles::build( new BreakpointRegistry( BreakpointRegistry::DEFAULTS ) ) ) )
			->toBe( flexLayoutRules( $static ) );
	} );

	it( 'keeps the editor canvas copy of the stylesheet in step with the front end', function (): void {
		$root = dirname( __DIR__, 4 );

		expect( file_get_contents( $root . '/resources/css/flex-layout.css' ) )
			->toBe( file_get_contents( $root . '/packages/visual-editor-renderer-blade/resources/assets/frontend/flex-layout.css' ) );
	} );

	it( 'returns nothing for the default breakpoints', function (): void {
		expect( FlexLayoutStyles::css( BreakpointRegistry::fromLayers( [] ) ) )->toBe( '' );
	} );

	it( 'moves each breakpoint block to the host width', function (): void {
		$css = FlexLayoutStyles::css( BreakpointRegistry::fromLayers( [
			'md'     => 700,
			'mobile' => [ 'maxWidthPx' => 599, 'previewWidthPx' => 375 ],
		] ) );

		expect( $css )
			->toContain( "@media (min-width: 700px) {\n\t:where(.md\\:ap-flex) > * + * { margin-block-start: 0; }" )
			->toContain( "@media (max-width: 599px) {\n\t:where(.mobile\\:ap-flex) > * + * { margin-block-start: 0; }" )
			->not->toContain( '(min-width: 768px)' )
			->not->toContain( '(max-width: 767px)' );
	} );

	it( 'emits blocks in the registry order, so narrower device overrides come last', function (): void {
		$css = FlexLayoutStyles::css( BreakpointRegistry::fromLayers( [ 'tablet' => [ 'maxWidthPx' => 900, 'previewWidthPx' => 768 ] ] ) );

		expect( strpos( $css, '.\\32xl\\:ap-flex {' ) )->toBeLessThan( strpos( $css, '.tablet\\:ap-flex {' ) );
		expect( strpos( $css, '.tablet\\:ap-flex {' ) )->toBeLessThan( strpos( $css, '.mobile\\:ap-flex {' ) );
	} );

	it( 'adds a block for a host breakpoint and drops a removed one', function (): void {
		$css = FlexLayoutStyles::css( BreakpointRegistry::fromLayers( [ '3xl' => 1920, 'sm' => null ] ) );

		expect( $css )
			->toContain( "@media (min-width: 1920px) {\n\t:where(.\\33xl\\:ap-flex)" )
			->not->toContain( 'sm\\:ap-flex' );
	} );

	it( 'escapes a leading digit in a breakpoint prefix', function ( string $key, string $expected ): void {
		expect( FlexLayoutStyles::escapePrefix( $key ) )->toBe( $expected );
	} )->with( [
		'letter'               => [ 'tablet', 'tablet\\:' ],
		'digit then non-hex'   => [ '2xl', '\\32xl\\:' ],
		'digit then hex digit' => [ '2a', '\\32 a\\:' ],
	] );
} );
