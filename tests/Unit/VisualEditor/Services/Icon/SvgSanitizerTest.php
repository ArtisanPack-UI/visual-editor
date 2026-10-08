<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Icon\SvgSanitizer;

beforeEach( function (): void {
	test()->sanitizer = new SvgSanitizer();
} );

it( 'returns empty result for empty input', function () {
	$result = test()->sanitizer->sanitize( '' );

	expect( $result->isEmpty() )->toBeTrue()
		->and( $result->hasWarnings() )->toBeFalse();
} );

it( 'passes a clean svg through', function () {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>';

	$result = test()->sanitizer->sanitize( $svg );

	expect( $result->isEmpty() )->toBeFalse()
		->and( $result->sanitized )->toContain( '<path' )
		->and( $result->sanitized )->toContain( 'viewBox="0 0 24 24"' )
		->and( $result->hasWarnings() )->toBeFalse();
} );

it( 'strips <script> elements', function () {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0"/></svg>';

	$result = test()->sanitizer->sanitize( $svg );

	expect( $result->sanitized )->not->toContain( '<script' )
		->and( $result->sanitized )->not->toContain( 'alert(1)' )
		->and( $result->sanitized )->toContain( '<path' )
		->and( $result->warnings )->toContain( 'removed <script> element' );
} );

it( 'strips on* event handler attributes', function () {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><path onclick="alert(2)" d="M0 0"/></svg>';

	$result = test()->sanitizer->sanitize( $svg );

	expect( $result->sanitized )->not->toContain( 'onload' )
		->and( $result->sanitized )->not->toContain( 'onclick' )
		->and( $result->warnings )->not->toBeEmpty();
} );

it( 'strips javascript: URIs from xlink:href', function () {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
		. '<use xlink:href="javascript:alert(1)"/></svg>';

	$result = test()->sanitizer->sanitize( $svg );

	expect( $result->sanitized )->not->toContain( 'javascript:' )
		->and( $result->warnings )->not->toBeEmpty();
} );

it( 'preserves internal anchor references', function () {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
		. '<defs><linearGradient id="g1"><stop offset="0" stop-color="red"/></linearGradient></defs>'
		. '<rect fill="url(#g1)" width="10" height="10"/></svg>';

	$result = test()->sanitizer->sanitize( $svg );

	expect( $result->sanitized )->toContain( 'id="g1"' )
		->and( $result->sanitized )->toContain( 'url(#g1)' )
		->and( $result->sanitized )->toContain( '<rect' );
} );

it( 'rejects markup whose root is not <svg>', function () {
	$result = test()->sanitizer->sanitize( '<div><script>x</script></div>' );

	expect( $result->isEmpty() )->toBeTrue()
		->and( $result->warnings )->not->toBeEmpty();
} );

it( 'rejects unparseable input', function () {
	$result = test()->sanitizer->sanitize( '<svg><not closed' );

	expect( $result->isEmpty() )->toBeTrue();
} );

it( 'preserves inline style declarations carrying inert presentation rules', function () {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" style="fill:#abc">'
		. '<path d="M0 0" style="fill:#123;opacity:0.5"/>'
		. '</svg>';

	$result = test()->sanitizer->sanitize( $svg );

	expect( $result->sanitized )->toContain( 'fill:#abc' )
		->and( $result->sanitized )->toContain( 'fill:#123' )
		->and( $result->sanitized )->toContain( 'opacity:0.5' )
		->and( $result->hasWarnings() )->toBeFalse();
} );

it( 'strips javascript:, expression(), and external url() from style', function () {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg">'
		. '<path d="M0 0" style="fill:#abc;background:expression(alert(1))"/>'
		. '<path d="M0 0" style="fill:url(http://evil.example/x.png)"/>'
		. '<path d="M0 0" style="fill:javascript:alert(1)"/>'
		. '</svg>';

	$result = test()->sanitizer->sanitize( $svg );

	expect( $result->sanitized )->not->toContain( 'expression' )
		->and( $result->sanitized )->not->toContain( 'javascript:' )
		->and( $result->sanitized )->not->toContain( 'evil.example' )
		->and( $result->sanitized )->toContain( 'fill:#abc' )
		->and( $result->warnings )->not->toBeEmpty();
} );

it( 'strips relative-path url() references from style (any non-fragment target)', function () {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg">'
		. '<path d="M0 0" style="fill:url(/asset.svg)"/>'
		. '<path d="M0 0" style="fill:url(icon.svg)"/>'
		. '</svg>';

	$result = test()->sanitizer->sanitize( $svg );

	expect( $result->sanitized )->not->toContain( '/asset.svg' )
		->and( $result->sanitized )->not->toContain( 'icon.svg' )
		->and( $result->warnings )->not->toBeEmpty();
} );

it( 'keeps internal url(#…) references inside style', function () {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg">'
		. '<defs><linearGradient id="g1"/></defs>'
		. '<rect style="fill:url(#g1)" width="10" height="10"/>'
		. '</svg>';

	$result = test()->sanitizer->sanitize( $svg );

	expect( $result->sanitized )->toContain( 'url(#g1)' )
		->and( $result->hasWarnings() )->toBeFalse();
} );

it( 'allows harmless version and xml:space root attributes', function () {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" xml:space="preserve">'
		. '<path d="M0 0"/></svg>';

	$result = test()->sanitizer->sanitize( $svg );

	expect( $result->sanitized )->toContain( 'version="1.1"' )
		->and( $result->sanitized )->toContain( 'xml:space="preserve"' )
		->and( $result->hasWarnings() )->toBeFalse();
} );

it( 'refuses DOCTYPEs carrying entity declarations instead of stripping them', function () {
	$svg = '<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
		. '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>';

	$result = test()->sanitizer->sanitize( $svg );

	expect( $result->isEmpty() )->toBeTrue()
		->and( $result->warnings )->toContain( 'svg contains a DOCTYPE or ENTITY declaration' );
} );

describe( 'XML external entity hardening (GHSA-r4qj-7p7h-gjxr)', function (): void {
	beforeEach( function (): void {
		test()->secretFile = tempnam( sys_get_temp_dir(), 've-xxe-' );
		file_put_contents( test()->secretFile, 'VE_XXE_CANARY_SECRET' );
		test()->secretUri = 'file://' . test()->secretFile;
	} );

	afterEach( function (): void {
		@unlink( test()->secretFile );
	} );

	it( 'refuses a DOCTYPE nested inside another DOCTYPE', function (): void {
		$svg = '<!DOC<!DOCTYPE x>TYPE svg [<!ENTITY xxe SYSTEM "' . test()->secretUri . '">]>'
			. '<svg xmlns="http://www.w3.org/2000/svg"><title>&xxe;</title><path d="M0 0"/></svg>';

		$result = test()->sanitizer->sanitize( $svg );

		expect( $result->sanitized )->not->toContain( 'VE_XXE_CANARY_SECRET' )
			->and( $result->isEmpty() )->toBeTrue()
			->and( $result->warnings )->toContain( 'svg contains a DOCTYPE or ENTITY declaration' );
	} );

	it( 'refuses an external SYSTEM entity expanded into an allow-listed element', function (): void {
		$svg = '<!DOCTYPE svg [<!ENTITY xxe SYSTEM "' . test()->secretUri . '">]>'
			. '<svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>';

		$result = test()->sanitizer->sanitize( $svg );

		expect( $result->sanitized )->not->toContain( 'VE_XXE_CANARY_SECRET' )
			->and( $result->isEmpty() )->toBeTrue()
			->and( $result->hasWarnings() )->toBeTrue();
	} );

	it( 'refuses parameter entities', function (): void {
		$svg = '<!DOCTYPE svg [<!ENTITY % ext SYSTEM "' . test()->secretUri . '"> %ext;]>'
			. '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>';

		$result = test()->sanitizer->sanitize( $svg );

		expect( $result->sanitized )->not->toContain( 'VE_XXE_CANARY_SECRET' )
			->and( $result->isEmpty() )->toBeTrue()
			->and( $result->hasWarnings() )->toBeTrue();
	} );

	it( 'refuses a bare ENTITY declaration outside a DOCTYPE', function (): void {
		$svg = '<!ENTITY xxe SYSTEM "' . test()->secretUri . '">'
			. '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>';

		$result = test()->sanitizer->sanitize( $svg );

		expect( $result->isEmpty() )->toBeTrue()
			->and( $result->hasWarnings() )->toBeTrue();
	} );

	it( 'refuses DOCTYPE declarations regardless of case or whitespace', function ( string $doctype ): void {
		$svg = $doctype . '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>';

		$result = test()->sanitizer->sanitize( $svg );

		expect( $result->isEmpty() )->toBeTrue()
			->and( $result->hasWarnings() )->toBeTrue();
	} )->with( [
		'lowercase'           => '<!doctype svg [<!entity a "b">]>',
		'mixed case'          => '<!DocType svg [<!Entity a "b">]>',
		'whitespace after <!' => '<! DOCTYPE svg>',
	] );

	it( 'does not expand undeclared entity references', function (): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><title>&xxe;</title></svg>';

		$result = test()->sanitizer->sanitize( $svg );

		expect( $result->sanitized )->not->toContain( 'VE_XXE_CANARY_SECRET' )
			->and( $result->isEmpty() )->toBeTrue();
	} );

	it( 'still decodes the predefined XML entities', function (): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><title>Fish &amp; Chips</title></svg>';

		$result = test()->sanitizer->sanitize( $svg );

		expect( $result->sanitized )->toContain( 'Fish &amp; Chips' )
			->and( $result->hasWarnings() )->toBeFalse();
	} );

	it( 'accepts the standard SVG 1.1 public DOCTYPE emitted by authoring tools', function (): void {
		$svg = '<?xml version="1.0" encoding="utf-8"?>'
			. '<!-- Generator: Adobe Illustrator 24.0.0, SVG Export Plug-In -->'
			. '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'
			. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0"/></svg>';

		$result = test()->sanitizer->sanitize( $svg );

		expect( $result->isEmpty() )->toBeFalse()
			->and( $result->sanitized )->not->toContain( 'DOCTYPE' )
			->and( $result->sanitized )->toContain( '<path' );
	} );

	it( 'refuses a malicious DOCTYPE reassembled around the standard public DOCTYPE', function (): void {
		$svg = '<!DOC<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'
			. 'TYPE svg [<!ENTITY xxe SYSTEM "' . test()->secretUri . '">]>'
			. '<svg xmlns="http://www.w3.org/2000/svg"><title>&xxe;</title></svg>';

		$result = test()->sanitizer->sanitize( $svg );

		expect( $result->sanitized )->not->toContain( 'VE_XXE_CANARY_SECRET' )
			->and( $result->isEmpty() )->toBeTrue()
			->and( $result->hasWarnings() )->toBeTrue();
	} );

	it( 'refuses a standard public DOCTYPE that carries an internal subset', function (): void {
		$svg = '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd" '
			. '[<!ENTITY xxe SYSTEM "' . test()->secretUri . '">]>'
			. '<svg xmlns="http://www.w3.org/2000/svg"><title>&xxe;</title></svg>';

		$result = test()->sanitizer->sanitize( $svg );

		expect( $result->sanitized )->not->toContain( 'VE_XXE_CANARY_SECRET' )
			->and( $result->isEmpty() )->toBeTrue();
	} );
} );
