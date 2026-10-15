<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\BlockStyles\BlockStyleCssCompiler;

function compileVariation( string $block, string $slug, array $style ): string
{
	return ( new BlockStyleCssCompiler() )->compile( [
		[ 'block' => $block, 'slug' => $slug, 'label' => $slug, 'style' => $style, 'isDefault' => false ],
	] );
}

it( 'emits a zero-specificity rule on the block and variation classes', function (): void {
	$css = compileVariation( 'core/group', 'card', [
		'color'   => [ 'background' => 'var:preset|color|base', 'text' => '#0f172a' ],
		'spacing' => [ 'padding' => [ 'top' => '2rem', 'left' => 'var:preset|spacing|40' ] ],
		'border'  => [ 'radius' => '12px', 'width' => '1px', 'style' => 'solid' ],
		'shadow'  => 'var:preset|shadow|natural',
	] );

	expect( $css )->toContain( ':root :where(.wp-block-group.is-style-card) {' )
		->toContain( 'background-color: var(--wp--preset--color--base);' )
		->toContain( 'color: #0f172a;' )
		->toContain( 'padding-top: 2rem;' )
		->toContain( 'padding-left: var(--wp--preset--spacing--40);' )
		->toContain( 'border-radius: 12px;' )
		->toContain( 'box-shadow: var(--wp--preset--shadow--natural);' );
} );

it( 'targets both the fork and the core class for artisanpack blocks', function (): void {
	expect( compileVariation( 'artisanpack/group', 'card', [ 'border' => [ 'radius' => '12px' ] ] ) )
		->toContain( ':root :where(.wp-block-group.is-style-card, .wp-block-artisanpack-group.is-style-card)' );
} );

it( 'namespaces third-party block selectors', function (): void {
	expect( compileVariation( 'acme/hero', 'dark', [ 'color' => [ 'text' => '#fff' ] ] ) )
		->toContain( '.wp-block-acme-hero.is-style-dark' );
} );

it( 'compiles nested elements and their pseudo states', function (): void {
	$css = compileVariation( 'core/group', 'card', [
		'elements' => [
			'link'    => [ 'color' => [ 'text' => '#22d3ee' ], ':hover' => [ 'color' => [ 'text' => '#e879f9' ] ] ],
			'heading' => [ 'typography' => [ 'fontWeight' => '700' ] ],
			'unknown' => [ 'color' => [ 'text' => 'red' ] ],
		],
	] );

	expect( $css )->toContain( ':root :where(.wp-block-group.is-style-card a:where(:not(.wp-element-button))) {' )
		->toContain( ':root :where(.wp-block-group.is-style-card a:where(:not(.wp-element-button)):hover) {' )
		->toContain( 'color: #e879f9;' )
		->toContain( '.wp-block-group.is-style-card h1, .wp-block-group.is-style-card h2' )
		->not->toContain( 'red' );
} );

it( 'compiles nested block styles as descendants', function (): void {
	expect( compileVariation( 'core/group', 'card', [
		'blocks' => [ 'core/heading' => [ 'typography' => [ 'fontSize' => '1.5rem' ] ] ],
	] ) )->toContain( ':root :where(.wp-block-group.is-style-card .wp-block-heading) {' );
} );

it( 'skips variations without styles', function (): void {
	expect( compileVariation( 'core/group', 'plain', [] ) )->toBe( '' );
} );

it( 'drops values that could break out of the style block', function ( string $value ): void {
	$css = compileVariation( 'core/group', 'bad', [ 'color' => [ 'text' => $value, 'background' => '#fff' ] ] );

	expect( $css )->toContain( 'background-color: #fff;' )
		->not->toContain( 'color: ' . $value );
} )->with( [
	'semicolon'                 => 'red; display: none',
	'brace'                     => 'red } body { display: none',
	'tag'                       => '</style><script>alert(1)</script>',
	'comment'                   => 'red /* x',
	'paren'                     => 'rgb(1,2,3',
	'interleaved double quotes' => '"a\' "b\'',
	'interleaved single quotes' => '\'a" \'b"',
	'open bracket'              => 'red [ display:none',
	'stray bracket'             => 'red ] display:none',
	'crossed brackets'          => '([)] display:none',
	'bracket in a string'       => '"[" ]',
] );

it( 'keeps values with a parenthesis inside a string (#885)', function (): void {
	expect( compileVariation( 'core/group', 'quoted', [ 'typography' => [ 'fontFamily' => '"Smile :)", sans-serif' ] ] ) )
		->toContain( 'font-family: "Smile :)", sans-serif;' );
} );

it( 'paints button variations on the inner link, as block.json selectors do', function (): void {
	$css = compileVariation( 'core/button', 'pill', [
		'border'   => [ 'radius' => '999px' ],
		'elements' => [ 'link' => [ 'color' => [ 'text' => '#fff' ] ] ],
	] );

	expect( $css )->toContain( ':root :where(.wp-block-button.is-style-pill .wp-block-button__link) {' )
		->toContain( 'border-radius: 999px;' )
		->toContain( ':root :where(.wp-block-button.is-style-pill a:where(:not(.wp-element-button)))' )
		->not->toContain( ':root :where(.wp-block-button.is-style-pill) {' );
} );

it( 'sends image border and shadow to the img and the rest to the wrapper', function (): void {
	$css = compileVariation( 'artisanpack/image', 'framed', [
		'border'  => [ 'radius' => '16px' ],
		'shadow'  => '0 8px 24px #0003',
		'spacing' => [ 'margin' => '2rem' ],
	] );

	expect( $css )->toContain( ':root :where(.wp-block-image.is-style-framed img, .wp-block-artisanpack-image.is-style-framed img) {' )
		->toContain( "border-radius: 16px;\n\tbox-shadow: 0 8px 24px #0003;" )
		->toContain( ':root :where(.wp-block-image.is-style-framed, .wp-block-artisanpack-image.is-style-framed) {' )
		->toContain( 'margin: 2rem;' );
} );

it( 'compiles outline on the variation, its elements and their focus states', function (): void {
	$css = compileVariation( 'core/group', 'card', [
		'outline'  => [ 'style' => 'solid', 'width' => '1px' ],
		'elements' => [
			'button' => [ ':focus' => [ 'outline' => [ 'color' => 'var:preset|color|contrast', 'offset' => '2px', 'style' => 'dashed', 'width' => '2px' ] ] ],
		],
	] );

	expect( $css )->toContain( ":root :where(.wp-block-group.is-style-card) {\n\toutline-style: solid;\n\toutline-width: 1px;\n}" )
		->toContain(
			":root :where(.wp-block-group.is-style-card .wp-element-button:focus, .wp-block-group.is-style-card .wp-block-button__link:focus) {\n"
			. "\toutline-color: var(--wp--preset--color--contrast);\n\toutline-offset: 2px;\n\toutline-style: dashed;\n\toutline-width: 2px;\n}",
		);
} );

it( 'paints a button variation outline on the inner link', function (): void {
	expect( compileVariation( 'core/button', 'ring', [ 'outline' => [ 'width' => '2px' ] ] ) )
		->toContain( ":root :where(.wp-block-button.is-style-ring .wp-block-button__link) {\n\toutline-width: 2px;\n}" );
} );

it( 'drops outline values that could break out of the style block', function (): void {
	$css = compileVariation( 'core/group', 'bad', [ 'outline' => [ 'color' => 'red [ display:none', 'width' => '1px' ] ] );

	expect( $css )->toContain( 'outline-width: 1px;' )
		->not->toContain( 'display:none' );
} );

it( 'compiles WordPress\'s link and button states in its order', function (): void {
	$node = [];

	foreach ( [ ':active', ':focus-visible', ':focus', ':hover', ':visited', ':any-link', ':link' ] as $index => $state ) {
		$node[ $state ] = [ 'color' => [ 'text' => '#00000' . $index ] ];
	}

	$css       = compileVariation( 'core/group', 'card', [ 'elements' => [ 'link' => $node ] ] );
	$positions = array_map(
		static fn ( string $state ): int|false => strpos( $css, '.is-style-card a:where(:not(.wp-element-button))' . $state . ') {' ),
		BlockStyleCssCompiler::PSEUDO_STATES,
	);
	$sorted = $positions;
	sort( $sorted );

	expect( BlockStyleCssCompiler::PSEUDO_STATES )->toBe( [ ':link', ':any-link', ':visited', ':hover', ':focus', ':focus-visible', ':active' ] )
		->and( $positions )->not->toContain( false )
		->and( $positions )->toBe( $sorted );
} );

it( 'ignores pseudo states on elements other than link and button', function (): void {
	$css = compileVariation( 'core/group', 'card', [
		'elements' => [
			'heading' => [ 'color' => [ 'text' => '#000000' ], ':hover' => [ 'color' => [ 'text' => '#123456' ] ] ],
			'h3'      => [ ':focus' => [ 'color' => [ 'text' => '#123456' ] ] ],
			'caption' => [ ':hover' => [ 'color' => [ 'text' => '#123456' ] ] ],
		],
	] );

	expect( $css )->toContain( 'color: #000000;' )
		->not->toContain( '#123456' )
		->not->toContain( ':hover' )
		->not->toContain( ':focus' );
} );

it( 'paints inner blocks of a variation on their block.json selectors (M-6)', function (): void {
	$css = compileVariation( 'core/group', 'card', [
		'blocks' => [
			'core/button' => [ 'color' => [ 'background' => 'blue' ] ],
			'core/image'  => [ 'border' => [ 'radius' => '8px' ], 'color' => [ 'text' => 'red' ] ],
		],
	] );

	expect( $css )->toContain( ":root :where(.wp-block-group.is-style-card .wp-block-button .wp-block-button__link) {\n\tbackground-color: blue;" )
		->toContain( ":root :where(.wp-block-group.is-style-card .wp-block-image img) {\n\tborder-radius: 8px;" )
		->toContain( ":root :where(.wp-block-group.is-style-card .wp-block-image) {\n\tcolor: red;" )
		->not->toContain( ':root :where(.wp-block-group.is-style-card .wp-block-button) {' );
} );

it( 'emits the gradient shorthand before background-color so the color survives (R-1)', function (): void {
	$css = compileVariation( 'core/group', 'card', [
		'color' => [ 'background' => '#fff', 'gradient' => 'linear-gradient(red, blue)' ],
	] );

	expect( $css )->toContain( "background: linear-gradient(red, blue);\n\tbackground-color: #fff;" );
} );
