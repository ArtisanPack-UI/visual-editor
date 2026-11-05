<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Support\CssValue;

it( 'accepts printable values', function ( string $value ): void {
	expect( CssValue::isPrintable( $value ) )->toBeTrue();
} )->with( [
	'hex'               => '#22d3ee',
	'function'          => 'rgba(34, 211, 238, 0.6)',
	'font stack'        => '"Inter Tight", system-ui, sans-serif',
	'quoted paren'      => '"a (b)"',
	'gradient'          => 'linear-gradient(45deg, var(--wp--preset--color--primary), #fff)',
	'smiley'            => '"Smile :)"',
	'open paren'        => "'('",
	'calc'              => 'calc(100% - var(--gap, 1rem))',
	'grid lines'        => '[full-start] minmax(0, 1fr) [full-end]',
	'bracket in string' => '"a[b"',
	'nested var'        => 'var(--x, rgb(0 0 0))',
	'paren in brackets' => '[a(b)]',
	'unquoted url'      => 'url(https://example.com/a.png)',
	'quoted url'        => 'url("a(b.png")',
] );

it( 'rejects values that break the style block', function ( string $value ): void {
	expect( CssValue::isPrintable( $value ) )->toBeFalse();
} )->with( [
	'semicolon'                => 'red; display: none',
	'brace'                    => 'red }',
	'tag'                      => '</style>',
	'comment'                  => 'red /* x',
	'escape'                   => 'rgb(\\)',
	'unclosed function'        => 'rgb(1,2,3',
	'stray close'              => 'red)',
	'paren hidden in string'   => 'rgb(")"',
	'unterminated string'      => '\'"\' "',
	'interleaved double'       => '"a\' "b\'',
	'interleaved single'       => '\'a" \'b"',
	'open bracket'             => 'red [ display:none',
	'stray bracket'            => 'red ] display:none',
	'crossed'                  => '([)] display:none',
	'paren closed by bracket'  => 'var(--x]',
	'unclosed grid line'       => '[full-start 1fr',
	'bracket hidden in string' => '"[" ]',
	'quote in unquoted url'    => 'url(a\'b) ( \'x)',
	'paren in unquoted url'    => 'url(a(b))',
	'unclosed unquoted url'    => 'url(a',
	'bad url opens a string'   => 'url(a")"x)',
] );

it( 'checks balance and comments for whitelisted values', function (): void {
	expect( CssValue::isBalanced( 'calc(100% - var(--gap))' ) )->toBeTrue()
		->and( CssValue::isBalanced( 'calc(' ) )->toBeFalse()
		->and( CssValue::isBalanced( '1px /* x' ) )->toBeFalse()
		->and( CssValue::isBalanced( '1px */' ) )->toBeFalse()
		->and( CssValue::isBalanced( '[full-start] minmax(0, 1fr) [full-end]' ) )->toBeTrue()
		->and( CssValue::isBalanced( 'ease [' ) )->toBeFalse()
		->and( CssValue::isBalanced( 'ease ]' ) )->toBeFalse()
		->and( CssValue::isBalanced( '([)]' ) )->toBeFalse();
} );

it( 'accepts a single gradient or var() reference', function ( string $value ): void {
	expect( CssValue::isGradient( $value ) )->toBeTrue();
} )->with( [
	'linear'           => 'linear-gradient(135deg, rgba(0, 0, 0, 0.5), var(--x))',
	'repeating radial' => 'repeating-radial-gradient(circle, red, color-mix(in srgb, red, blue))',
	'var'              => 'var(--wp--preset--gradient--sunset)',
] );

it( 'rejects a gradient that could fetch a resource or add a layer', function ( string $value ): void {
	expect( CssValue::isGradient( $value ) )->toBeFalse();
} )->with( [
	'url'                 => 'url(t.png)',
	'image-set'           => 'image-set(t.png 1x)',
	'url inside gradient' => 'linear-gradient(url(t.png), blue)',
	'second layer'        => 'linear-gradient(red, blue),url(t.png)',
	'trailing keyword'    => 'linear-gradient(red, blue) padding-box',
	'unclosed'            => 'linear-gradient(red, blue',
	'plain color'         => 'red',
	'var with fallback'   => 'var(--x, url(t.png))',
] );
