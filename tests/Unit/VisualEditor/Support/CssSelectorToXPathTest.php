<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Support\CssSelectorToXPath;
use ArtisanPackUI\VisualEditor\Support\UnsupportedSelectorException;

/**
 * Run a selector against a fragment and return the matched tag names,
 * so the assertions describe behavior rather than XPath syntax.
 *
 * @return array<int, string>
 */
function veMatchTags( string $html, string $selector ): array
{
	$document = new DOMDocument();

	libxml_use_internal_errors( true );
	$document->loadHTML(
		'<?xml encoding="UTF-8"?><div id="root">' . $html . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
	);
	libxml_clear_errors();

	$xpath   = new DOMXPath( $document );
	$root    = $xpath->query( "//*[@id='root']" )->item( 0 );
	$matches = $xpath->query( CssSelectorToXPath::translate( $selector ), $root );

	$out = [];

	foreach ( $matches as $node ) {
		$out[] = strtolower( $node->tagName ) . ( '' !== $node->textContent ? ':' . $node->textContent : '' );
	}

	return $out;
}

it( 'matches a fragment root by tag name', function () {
	expect( veMatchTags( '<p>Hello</p>', 'p' ) )->toBe( [ 'p:Hello' ] );
} );

it( 'matches any of a comma-separated group', function () {
	expect( veMatchTags( '<h3>Title</h3>', 'h1,h2,h3,h4,h5,h6' ) )->toBe( [ 'h3:Title' ] );
} );

it( 'matches a class selector', function () {
	expect( veMatchTags( '<div class="ap-callout__body other">Body</div>', 'div.ap-callout__body' ) )
		->toBe( [ 'div:Body' ] );
} );

it( 'does not match a class that is only a substring of another class', function () {
	expect( veMatchTags( '<div class="ap-callout__bodyguard">x</div>', '.ap-callout__body' ) )->toBe( [] );
} );

it( 'honours the child combinator', function () {
	expect( veMatchTags( '<figure><a href="#">link</a></figure>', 'figure > a' ) )->toBe( [ 'a:link' ] );
	expect( veMatchTags( '<figure><span><a href="#">link</a></span></figure>', 'figure > a' ) )->toBe( [] );
} );

it( 'honours the descendant combinator', function () {
	expect( veMatchTags( '<figure><span><img/></span></figure>', 'figure img' ) )->toBe( [ 'img' ] );
} );

it( 'matches attribute presence and negation', function () {
	$html = '<div><a href="#a">plain</a><a download href="#b">dl</a></div>';

	expect( veMatchTags( $html, 'a[download]' ) )->toBe( [ 'a:dl' ] );
	expect( veMatchTags( $html, 'a:not([download])' ) )->toBe( [ 'a:plain' ] );
} );

it( 'matches attribute equality across quoting styles', function () {
	$html = '<div><input type="text"/><input type="hidden"/></div>';

	expect( CssSelectorToXPath::translate( '[type=text]' ) )
		->toBe( CssSelectorToXPath::translate( '[type="text"]' ) );

	expect( veMatchTags( $html, 'input[type="hidden"]' ) )->toBe( [ 'input' ] );
} );

it( 'returns matches in document order for a comma group', function () {
	expect( veMatchTags( '<tr><th>H</th><td>D</td></tr>', 'td,th' ) )->toBe( [ 'th:H', 'td:D' ] );
} );

it( 'translates every selector used by every bundled block manifest', function () {
	// The translator supports a deliberate SUBSET of CSS. This guard
	// makes a manifest that introduces a selector outside that subset
	// (`~`, `+`, a pseudo-class, a comma inside an attribute value) fail
	// here rather than silently dropping the attribute at render time.
	$selectors = [];

	/**
	 * @param  array<string, mixed>  $definitions
	 */
	$collect = function ( array $definitions, string $block ) use ( &$collect, &$selectors ): void {
		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			if ( isset( $definition['selector'] ) && is_string( $definition['selector'] ) ) {
				$selectors[ $definition['selector'] ] = $block;
			}

			if ( isset( $definition['query'] ) && is_array( $definition['query'] ) ) {
				$collect( $definition['query'], $block );
			}
		}
	};

	foreach ( glob( __DIR__ . '/../../../../resources/js/visual-editor/blocks/*/block.json' ) as $path ) {
		$manifest = json_decode( (string) file_get_contents( $path ), true );

		if ( is_array( $manifest['attributes'] ?? null ) ) {
			$collect( $manifest['attributes'], basename( dirname( $path ) ) );
		}
	}

	expect( $selectors )->not->toBeEmpty();

	foreach ( $selectors as $selector => $block ) {
		expect( fn () => CssSelectorToXPath::translate( (string) $selector ) )
			->not->toThrow( UnsupportedSelectorException::class, "block: {$block}, selector: {$selector}" );
	}
} );

it( 'rejects selectors outside the supported subset', function () {
	expect( fn () => CssSelectorToXPath::translate( 'p:nth-child(2)' ) )
		->toThrow( UnsupportedSelectorException::class );

	expect( fn () => CssSelectorToXPath::translate( '' ) )
		->toThrow( UnsupportedSelectorException::class );
} );
