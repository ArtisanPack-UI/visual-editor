<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Effects\EffectsCompiler;

function compileEffects( array $effects, array $attributes = [] ): ?array
{
	return EffectsCompiler::compile( [ 'artisanpackEffects' => $effects ] + $attributes );
}

it( 'returns null without effects', function (): void {
	expect( EffectsCompiler::compile( [] ) )->toBeNull()
		->and( compileEffects( [] ) )->toBeNull()
		->and( compileEffects( [ 'opacity' => 'half', 'overflowX' => 'nope' ] ) )->toBeNull();
} );

it( 'compiles every effect in a fixed order under a hashed scope', function (): void {
	$result = compileEffects( [
		'textClip'         => true,
		'clipShape'        => 'circle',
		'zIndex'           => 5,
		'isolation'        => true,
		'mixBlendMode'     => 'multiply',
		'backdropSaturate' => 180,
		'backdropBlur'     => 12,
		'overflowY'        => 'auto',
		'overflowX'        => 'hidden',
		'opacity'          => 80,
	], [ 'gradient' => 'vivid-cyan-blue-to-vivid-purple' ] );

	expect( $result['class'] )->toMatch( '/^ve-fx-[0-9a-f]{12}$/' )
		->and( $result['css'] )->toBe(
			'.' . $result['class'] . '{opacity:0.8;overflow-x:hidden;overflow-y:auto;'
			. '-webkit-backdrop-filter:blur(12px) saturate(180%);backdrop-filter:blur(12px) saturate(180%);'
			. 'mix-blend-mode:multiply;isolation:isolate;z-index:5;clip-path:circle(50% at 50% 50%);'
			. '-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}'
			. ':where(.' . $result['class'] . '){position:relative}'
			. '@media (forced-colors:active){.' . $result['class'] . '{-webkit-text-fill-color:currentColor;'
			. '-webkit-background-clip:border-box;background-clip:border-box}}',
		);
} );

it( 'drops text clip when the block paints no gradient or background image', function (): void {
	expect( compileEffects( [ 'textClip' => true ] ) )->toBeNull()
		->and( compileEffects( [ 'textClip' => true, 'opacity' => 50 ] )['css'] )->not->toContain( 'transparent' )
		->and( compileEffects( [ 'textClip' => true ], [ 'gradient' => '' ] ) )->toBeNull()
		->and( compileEffects( [ 'textClip' => true ], [ 'style' => [ 'color' => [ 'background' => '#000' ] ] ] ) )->toBeNull();
} );

it( 'keeps text clip, with a forced-colors fallback, over a gradient or background image', function ( array $attributes ): void {
	$result = compileEffects( [ 'textClip' => true ], $attributes );

	expect( $result['css'] )->toBe(
		'.' . $result['class'] . '{-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}'
		. '@media (forced-colors:active){.' . $result['class'] . '{-webkit-text-fill-color:currentColor;'
		. '-webkit-background-clip:border-box;background-clip:border-box}}',
	);
} )->with( [
	'preset gradient'  => [ [ 'gradient' => 'cool-to-warm' ] ],
	'custom gradient'  => [ [ 'style' => [ 'color' => [ 'gradient' => 'linear-gradient(red, blue)' ] ] ] ],
	'image object'     => [ [ 'style' => [ 'background' => [ 'backgroundImage' => [ 'url' => 'https://example.com/a.png' ] ] ] ] ],
	'image string'     => [ [ 'style' => [ 'background' => [ 'backgroundImage' => 'https://example.com/a.png' ] ] ] ],
] );

it( 'lets the caller say whether the block paints a background', function (): void {
	$effects = [ 'artisanpackEffects' => [ 'textClip' => true ] ];

	expect( EffectsCompiler::compile( $effects, true )['css'] )->toContain( 'background-clip:text' )
		->and( EffectsCompiler::compile( $effects + [ 'gradient' => 'cool-to-warm' ], false ) )->toBeNull();
} );

it( 'detects a background fill from the attributes', function (): void {
	expect( EffectsCompiler::hasBackgroundFill( [] ) )->toBeFalse()
		->and( EffectsCompiler::hasBackgroundFill( [ 'style' => 'nope', 'gradient' => 5 ] ) )->toBeFalse()
		->and( EffectsCompiler::hasBackgroundFill( [ 'style' => [ 'background' => [ 'backgroundImage' => [ 'url' => '' ] ] ] ] ) )->toBeFalse()
		->and( EffectsCompiler::hasBackgroundFill( [ 'gradient' => 'cool-to-warm' ] ) )->toBeTrue();
} );

it( 'drops Custom CSS that loads a URL when config disallows URLs', function (): void {
	$effects = [ 'opacity' => 50, 'customCss' => 'background: url("https://example.com/a.png");' ];

	expect( compileEffects( $effects )['css'] )->toContain( 'url("https://example.com/a.png")' );

	config()->set( 'artisanpack.visual-editor.effects.custom_css_allow_urls', false );

	$result = compileEffects( $effects );

	expect( $result['css'] )->toBe( '.' . $result['class'] . '{opacity:0.5}' )
		->and( EffectsCompiler::compile( [ 'artisanpackEffects' => $effects ], null, true )['css'] )->toContain( 'url(' );
} );

it( 'allows URLs by default', function (): void {
	expect( config( 'artisanpack.visual-editor.effects.custom_css_allow_urls' ) )->toBeTrue();
} );

it( 'clamps and rounds numbers', function (): void {
	expect( compileEffects( [ 'opacity' => 150 ] )['css'] )->toContain( 'opacity:1}' )
		->and( compileEffects( [ 'opacity' => '7' ] )['css'] )->toContain( 'opacity:0.07}' )
		->and( compileEffects( [ 'opacity' => 0 ] )['css'] )->toContain( 'opacity:0}' )
		->and( compileEffects( [ 'zIndex' => -2.5 ] )['css'] )->toContain( 'z-index:-3}' )
		->and( compileEffects( [ 'backdropBlur' => 999 ] )['css'] )->toContain( 'blur(200px)' );
} );

it( 'only adds the zero-specificity position with a z-index', function (): void {
	expect( compileEffects( [ 'opacity' => 50 ] )['css'] )->not->toContain( 'position' );
} );

it( 'nests Custom CSS inside the block rule', function (): void {
	$result = compileEffects( [ 'opacity' => 50, 'customCss' => "border: 1px solid red;\n& img { border-radius: 8px; }" ] );

	expect( $result['css'] )->toBe( '.' . $result['class'] . '{opacity:0.5;border: 1px solid red; & img { border-radius: 8px; }}' );
} );

it( 'drops rejected Custom CSS but keeps the effects', function (): void {
	$result = compileEffects( [ 'opacity' => 50, 'customCss' => 'color: red; } body { display: none' ] );

	expect( $result['css'] )->toBe( '.' . $result['class'] . '{opacity:0.5}' );
} );

it( 'shares one class between blocks with the same effects', function (): void {
	expect( compileEffects( [ 'opacity' => 50 ] )['class'] )->toBe( compileEffects( [ 'opacity' => 50.0 ] )['class'] )
		->and( compileEffects( [ 'opacity' => 50 ] )['class'] )->not->toBe( compileEffects( [ 'opacity' => 60 ] )['class'] );
} );

it( 'treats only ASCII whitespace as blank or padding (the JS twins match this)', function (): void {
	$nbsp = compileEffects( [ 'customCss' => "\u{00A0}" ] );

	expect( $nbsp['css'] )->toBe( '.' . $nbsp['class'] . "{\u{00A0}}" )
		->and( compileEffects( [ 'zIndex' => "\u{00A0}5" ] ) )->toBeNull()
		->and( compileEffects( [ 'opacity' => "\u{00A0}50" ] ) )->toBeNull()
		->and( compileEffects( [ 'zIndex' => " 5\t" ] )['css'] )->toContain( '{z-index:5}' )
		->and( compileEffects( [ 'opacity' => "\x0B50\x0C" ] )['css'] )->toContain( 'opacity:0.5}' );
} );
