<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Support\CssValue;

it( 'accepts printable values', function ( string $value ): void {
	expect( CssValue::isPrintable( $value ) )->toBeTrue();
} )->with( [
	'hex'          => '#22d3ee',
	'function'     => 'rgba(34, 211, 238, 0.6)',
	'font stack'   => '"Inter Tight", system-ui, sans-serif',
	'quoted paren' => '"a (b)"',
	'gradient'     => 'linear-gradient(45deg, var(--wp--preset--color--primary), #fff)',
] );

it( 'rejects values that break the style block', function ( string $value ): void {
	expect( CssValue::isPrintable( $value ) )->toBeFalse();
} )->with( [
	'semicolon'               => 'red; display: none',
	'brace'                   => 'red }',
	'tag'                     => '</style>',
	'comment'                 => 'red /* x',
	'escape'                  => 'rgb(\\)',
	'unclosed function'       => 'rgb(1,2,3',
	'stray close'             => 'red)',
	'paren hidden in string'  => 'rgb(")"',
	'unterminated string'     => '\'"\' "',
] );

it( 'checks balance and comments for whitelisted values', function (): void {
	expect( CssValue::isBalanced( 'calc(100% - var(--gap))' ) )->toBeTrue()
		->and( CssValue::isBalanced( 'calc(' ) )->toBeFalse()
		->and( CssValue::isBalanced( '1px /* x' ) )->toBeFalse()
		->and( CssValue::isBalanced( '1px */' ) )->toBeFalse();
} );
