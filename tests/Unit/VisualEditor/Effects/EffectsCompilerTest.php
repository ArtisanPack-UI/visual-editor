<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Effects\EffectsCompiler;

function compileEffects( array $effects ): ?array
{
	return EffectsCompiler::compile( [ 'artisanpackEffects' => $effects ] );
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
	] );

	expect( $result['class'] )->toMatch( '/^ve-fx-[0-9a-f]{12}$/' )
		->and( $result['css'] )->toBe(
			'.' . $result['class'] . '{opacity:0.8;overflow-x:hidden;overflow-y:auto;'
			. '-webkit-backdrop-filter:blur(12px) saturate(180%);backdrop-filter:blur(12px) saturate(180%);'
			. 'mix-blend-mode:multiply;isolation:isolate;z-index:5;clip-path:circle(50% at 50% 50%);'
			. '-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}'
			. ':where(.' . $result['class'] . '){position:relative}',
		);
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
