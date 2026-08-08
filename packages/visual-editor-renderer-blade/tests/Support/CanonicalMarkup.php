<?php

/**
 * Canonical markup serializer for the Blade-vs-JS parity check (#704).
 *
 * Parses rendered HTML and re-emits it as an indented, deterministic
 * tree so the Blade renderer's output can be compared against the
 * React / Vue renderers' output without tripping over incidental
 * formatting differences.
 *
 * Normalization is deliberately narrow — see README.md in
 * packages/renderer-markup-parity for the exact contract. In short:
 * whitespace and attribute order are normalized; element names, class
 * tokens and attribute values are not.
 *
 * The TypeScript twin of this class lives at
 * packages/renderer-markup-parity/canonicalize.ts. Any change here must
 * be mirrored there or the two suites stop agreeing.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Tests\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

final class CanonicalMarkup
{
	/**
	 * HTML elements that never have children and are emitted self-closed.
	 *
	 * @var array<int, string>
	 */
	private const VOID_ELEMENTS = [
		'area',
		'base',
		'br',
		'col',
		'embed',
		'hr',
		'img',
		'input',
		'link',
		'meta',
		'param',
		'source',
		'track',
		'wbr',
	];

	/**
	 * Class-token regexes declared as known renderer divergences.
	 *
	 * @var array<int, string>
	 */
	private static array $dropClassPatterns = [];

	/**
	 * Serializes rendered HTML into the canonical comparison form.
	 *
	 * @since 1.6.0
	 *
	 * @param  string             $html                Rendered HTML fragment.
	 * @param  array<int, string> $dropClassPatterns   Unanchored-delimiter regex
	 *                                                 sources for class tokens that
	 *                                                 are declared, documented
	 *                                                 divergences (see the
	 *                                                 `knownDivergences` block in
	 *                                                 fixtures.json). Matching tokens
	 *                                                 are dropped from every class
	 *                                                 attribute before comparison.
	 *
	 * @return string Canonical, newline-delimited markup tree.
	 */
	public static function fromHtml( string $html, array $dropClassPatterns = [] ): string
	{
		self::$dropClassPatterns = $dropClassPatterns;

		$document = new DOMDocument();

		$previous = libxml_use_internal_errors( true );

		$document->loadHTML(
			'<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div id="ve-parity-root">'
			. $html
			. '</div></body></html>'
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$root = $document->getElementById( 've-parity-root' );

		if ( ! $root instanceof DOMElement ) {
			return '';
		}

		$lines = [];

		self::serializeChildren( $root, 0, $lines );

		return implode( "\n", $lines );
	}

	/**
	 * Serializes an element's children, dropping insignificant whitespace.
	 *
	 * @since 1.6.0
	 *
	 * @param  DOMNode              $parent  Node whose children are serialized.
	 * @param  int                  $depth   Current indentation depth.
	 * @param  array<int, string>   $lines   Accumulated output lines.
	 */
	private static function serializeChildren( DOMNode $parent, int $depth, array &$lines ): void
	{
		/** @var array<int, array{node: DOMNode, text: string|null}> $kept */
		$kept = [];

		foreach ( $parent->childNodes as $child ) {
			if ( $child instanceof DOMText ) {
				$text = self::collapseWhitespace( $child->textContent );

				if ( '' === trim( $text ) ) {
					continue;
				}

				$kept[] = [ 'node' => $child, 'text' => $text ];

				continue;
			}

			if ( ! $child instanceof DOMElement ) {
				// Comments and processing instructions carry no rendered
				// markup; Vue's SSR fragment markers land here too.
				continue;
			}

			$kept[] = [ 'node' => $child, 'text' => null ];
		}

		$lastIndex = count( $kept ) - 1;

		foreach ( $kept as $index => $entry ) {
			if ( null !== $entry['text'] ) {
				$text = $entry['text'];

				if ( 0 === $index ) {
					$text = ltrim( $text );
				}

				if ( $index === $lastIndex ) {
					$text = rtrim( $text );
				}

				if ( '' === $text ) {
					continue;
				}

				$lines[] = str_repeat( '  ', $depth ) . '"' . self::escape( $text ) . '"';

				continue;
			}

			/** @var DOMElement $element */
			$element = $entry['node'];

			self::serializeElement( $element, $depth, $lines );
		}
	}

	/**
	 * Serializes a single element and, recursively, its children.
	 *
	 * @since 1.6.0
	 *
	 * @param  DOMElement          $element  Element to serialize.
	 * @param  int                 $depth    Current indentation depth.
	 * @param  array<int, string>  $lines    Accumulated output lines.
	 */
	private static function serializeElement( DOMElement $element, int $depth, array &$lines ): void
	{
		$indent = str_repeat( '  ', $depth );
		$tag    = strtolower( $element->tagName );

		$attributes = [];

		foreach ( $element->attributes as $attribute ) {
			$name = strtolower( $attribute->nodeName );

			$attributes[ $name ] = self::canonicalAttributeValue( $name, (string) $attribute->nodeValue );
		}

		ksort( $attributes );

		$serialized = '';

		foreach ( $attributes as $name => $value ) {
			$serialized .= ' ' . $name . '="' . self::escape( $value ) . '"';
		}

		if ( in_array( $tag, self::VOID_ELEMENTS, true ) ) {
			$lines[] = $indent . '<' . $tag . $serialized . ' />';

			return;
		}

		$lines[] = $indent . '<' . $tag . $serialized . '>';

		self::serializeChildren( $element, $depth + 1, $lines );

		$lines[] = $indent . '</' . $tag . '>';
	}

	/**
	 * Applies the narrow, per-attribute whitespace normalization.
	 *
	 * `class` gets its whitespace collapsed (token order and spelling are
	 * preserved). `style` gets each declaration trimmed and re-joined with
	 * `; ` so Vue's trailing semicolon and Blade's indentation don't count
	 * as divergence. Every other attribute value is passed through as-is.
	 *
	 * @since 1.6.0
	 *
	 * @param  string  $name   Lowercased attribute name.
	 * @param  string  $value  Raw attribute value.
	 *
	 * @return string Canonical attribute value.
	 */
	private static function canonicalAttributeValue( string $name, string $value ): string
	{
		if ( 'class' === $name ) {
			$tokens = [];

			foreach ( preg_split( '/\s+/', trim( $value ) ) ?: [] as $token ) {
				if ( '' === $token || self::isDeclaredDivergentClass( $token ) ) {
					continue;
				}

				$tokens[] = $token;
			}

			return implode( ' ', $tokens );
		}

		if ( 'style' === $name ) {
			$declarations = [];

			foreach ( explode( ';', $value ) as $declaration ) {
				$declaration = trim( self::collapseWhitespace( $declaration ) );

				if ( '' === $declaration ) {
					continue;
				}

				// Whitespace around the property/value colon is
				// insignificant CSS; React serializes `flex-basis:60%`
				// where Blade writes `flex-basis: 60%`.
				$parts = explode( ':', $declaration, 2 );

				$declarations[] = 2 === count( $parts )
					? trim( $parts[0] ) . ': ' . trim( $parts[1] )
					: $declaration;
			}

			return implode( '; ', $declarations );
		}

		return $value;
	}

	/**
	 * Whether a class token is a declared, documented divergence.
	 *
	 * @since 1.6.0
	 *
	 * @param  string  $token  Single class token.
	 *
	 * @return bool True when the token should be dropped before comparison.
	 */
	private static function isDeclaredDivergentClass( string $token ): bool
	{
		foreach ( self::$dropClassPatterns as $pattern ) {
			if ( 1 === preg_match( self::compileDropClassPattern( $pattern ), $token ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Wraps a `dropClassTokensMatching` source in its PCRE delimiter and
	 * asserts that the result compiles.
	 *
	 * Both steps matter. A pattern containing `#` would otherwise end the
	 * delimiter early and fail to compile, and `preg_match()` signals that
	 * with a warning plus a `false` return that reads exactly like "no
	 * match" — the golden would then be written *with* the token while the
	 * JS side (where `new RegExp(source)` compiles it fine) drops it, a
	 * permanent and baffling parity failure. Throwing here turns that into
	 * an immediate, legible error instead.
	 *
	 * @since 1.6.0
	 *
	 * @param  string  $pattern  Delimiter-less regex source from fixtures.json.
	 *
	 * @return string Delimited pattern safe to hand to `preg_match()`.
	 */
	public static function compileDropClassPattern( string $pattern ): string
	{
		$delimited = '#' . str_replace( '#', '\\#', $pattern ) . '#';

		if ( false === @preg_match( $delimited, '' ) ) {
			throw new \InvalidArgumentException(
				'Invalid dropClassTokensMatching pattern in fixtures.json: ' . $pattern
			);
		}

		return $delimited;
	}

	/**
	 * Collapses every run of whitespace down to a single space.
	 *
	 * @since 1.6.0
	 *
	 * @param  string  $value  Value to collapse.
	 *
	 * @return string Collapsed value.
	 */
	private static function collapseWhitespace( string $value ): string
	{
		// The character class is spelled out rather than using `\s` because
		// the two canonicalizers must agree on what counts as whitespace.
		// JavaScript's `\s` matches Unicode spaces (U+00A0, U+2028, U+FEFF,
		// …); PCRE's `\s` under `/u` without `(*UCP)` matches ASCII only.
		// Both sides now name the ASCII set explicitly so a fixture carrying
		// `&nbsp;` canonicalizes identically instead of producing a golden
		// the JS side can never match. Mirrors `collapseWhitespace()` in
		// packages/renderer-markup-parity/canonicalize.ts.
		//
		// `\x0B` is spelled numerically on purpose: PCRE's `\v` is the
		// *vertical whitespace* class (which under `/u` also matches NEL,
		// U+2028 and U+2029), whereas JavaScript's `\v` is the single
		// vertical-tab character the JS twin means.
		return (string) preg_replace( '/[ \t\r\n\f\x0B]+/u', ' ', $value );
	}

	/**
	 * Escapes backslashes and double quotes for the quoted output form.
	 *
	 * @since 1.6.0
	 *
	 * @param  string  $value  Value to escape.
	 *
	 * @return string Escaped value.
	 */
	private static function escape( string $value ): string
	{
		return str_replace( [ '\\', '"' ], [ '\\\\', '\"' ], $value );
	}
}
