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
	'semicolon' => 'red; display: none',
	'brace'     => 'red } body { display: none',
	'tag'       => '</style><script>alert(1)</script>',
	'comment'   => 'red /* x',
	'paren'     => 'rgb(1,2,3',
] );

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
