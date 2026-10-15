<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Effects\CustomCssSanitizer;

it( 'normalises accepted Custom CSS', function ( string $css, string $expected ): void {
	expect( CustomCssSanitizer::sanitize( $css ) )->toBe( $expected );
} )->with( [
	'declarations'     => [ "  color: red;\n\tpadding: 1rem;  ", 'color: red; padding: 1rem;' ],
	'nested rule'      => [ "& > img {\n  border-radius: 50%;\n}", '& > img { border-radius: 50%; }' ],
	'pseudo class'     => [ '&:hover { opacity: 1; }', '&:hover { opacity: 1; }' ],
	'media query'      => [ '@media (max-width: 600px) { & { display: none; } }', '@media (max-width: 600px) { & { display: none; } }' ],
	'supports'         => [ '@supports (display: grid) { display: grid; }', '@supports (display: grid) { display: grid; }' ],
	'comment removed'  => [ 'color: red; /* note */ margin: 0;', 'color: red; margin: 0;' ],
	'string kept'      => [ 'content: "a  {  b";', 'content: "a  {  b";' ],
	'url'              => [ 'background: url(https://example.com/a.png);', 'background: url(https://example.com/a.png);' ],
] );

it( 'returns an empty string for empty input', function ( mixed $css ): void {
	expect( CustomCssSanitizer::sanitize( $css ) )->toBe( '' );
} )->with( [ 'null' => [ null ], 'blank' => [ "  \n " ], 'array' => [ [] ] ] );

it( 'rejects CSS that could escape the block or the style element', function ( string $css ): void {
	expect( CustomCssSanitizer::sanitize( $css ) )->toBeNull();
} )->with( [
	'closes the rule'       => [ 'color: red; } body { display: none' ],
	'leaves a brace open'   => [ '& img { color: red;' ],
	'style breakout'        => [ 'color: red; </style><script>alert(1)</script>' ],
	'backslash escape'      => [ 'content: "\\7d";' ],
	'unterminated string'   => [ 'content: "abc;' ],
	'newline in string'     => [ "content: \"a\nb\";" ],
	'unterminated comment'  => [ 'color: red; /* open' ],
	'import'                => [ '@import url(evil.css);' ],
	'font face'             => [ '@font-face { font-family: x; }' ],
	'keyframes'             => [ '@keyframes spin { to { rotate: 1turn; } }' ],
	'expression'            => [ 'width: expression(alert(1));' ],
	'javascript url'        => [ 'background: url(javascript:alert(1));' ],
	'binding'               => [ '-moz-binding: url(x.xml#y);' ],
	'control character'     => [ "color: red;\x01" ],
] );

it( 'rejects CSS over the length limit', function (): void {
	expect( CustomCssSanitizer::sanitize( str_repeat( 'a', CustomCssSanitizer::MAX_LENGTH + 1 ) ) )->toBeNull()
		->and( CustomCssSanitizer::sanitize( str_repeat( 'a', CustomCssSanitizer::MAX_LENGTH ) ) )->not->toBeNull();
} );
