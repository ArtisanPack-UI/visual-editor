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
