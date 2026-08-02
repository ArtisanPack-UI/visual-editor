<?php

/**
 * Minimal CSS-selector → XPath translator.
 *
 * Covers exactly the selector grammar that Gutenberg `block.json`
 * attribute definitions use in their `selector` field — nothing more.
 * Written by hand rather than pulled from `symfony/css-selector` so the
 * package gains no new runtime dependency for a feature this narrow
 * (the selectors shipped across all 105 bundled manifests are tag
 * names, class names, attribute presence/equality, `:not()` over a
 * single simple predicate, descendant and child combinators, and comma
 * groups).
 *
 * Supported grammar, per comma-separated group:
 *
 *     compound ( ( ' ' | ' > ' ) compound )*
 *     compound := tag? ( '.' class | '#' id | '[' attr ( '=' value )? ']' | ':not(' simple ')' )*
 *
 * Anything outside that grammar throws {@see UnsupportedSelectorException}
 * so callers can degrade to "attribute not recovered" rather than
 * silently matching the wrong node.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.5.5
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

class CssSelectorToXPath
{
	/**
	 * Memoizes translations for the process lifetime. Selectors come
	 * from a fixed set of registered `block.json` manifests, so the
	 * cache is bounded by the number of registered sourced attributes.
	 *
	 * @var array<string, string>
	 *
	 * @since 1.5.5
	 */
	protected static array $cache = [];

	/**
	 * Translate a CSS selector into an XPath expression evaluated
	 * relative to a context node.
	 *
	 * @since 1.5.5
	 *
	 * @param  string  $selector  A CSS selector within the supported subset.
	 *
	 * @return string XPath expression, relative to the context node.
	 *
	 * @throws UnsupportedSelectorException When the selector falls outside the supported subset.
	 */
	public static function translate( string $selector ): string
	{
		$selector = trim( $selector );

		if ( isset( self::$cache[ $selector ] ) ) {
			return self::$cache[ $selector ];
		}

		if ( '' === $selector ) {
			throw new UnsupportedSelectorException( 'Empty CSS selector.' );
		}

		$paths = [];

		foreach ( explode( ',', $selector ) as $group ) {
			$paths[] = self::translateGroup( trim( $group ) );
		}

		return self::$cache[ $selector ] = implode( ' | ', $paths );
	}

	/**
	 * Translate a single (comma-free) selector group.
	 *
	 * @since 1.5.5
	 *
	 * @throws UnsupportedSelectorException
	 */
	protected static function translateGroup( string $group ): string
	{
		if ( '' === $group ) {
			throw new UnsupportedSelectorException( 'Empty CSS selector group.' );
		}

		// Normalize combinators so a simple whitespace split yields an
		// alternating compound/combinator token stream.
		$normalized = (string) preg_replace( '/\s*>\s*/', ' > ', $group );
		$normalized = (string) preg_replace( '/\s+/', ' ', trim( $normalized ) );

		$tokens = explode( ' ', $normalized );
		$xpath  = '';
		$axis   = 'descendant-or-self::';

		foreach ( $tokens as $token ) {
			if ( '>' === $token ) {
				$axis = 'child::';

				continue;
			}

			$xpath .= ( '' === $xpath ? '' : '/' ) . $axis . self::translateCompound( $token );
			$axis   = 'descendant::';
		}

		if ( '' === $xpath ) {
			throw new UnsupportedSelectorException( sprintf( 'Unsupported CSS selector group: "%s".', $group ) );
		}

		return $xpath;
	}

	/**
	 * Translate a compound selector (`tag.class[attr]:not(...)`) into an
	 * XPath node test plus predicates.
	 *
	 * @since 1.5.5
	 *
	 * @throws UnsupportedSelectorException
	 */
	protected static function translateCompound( string $compound ): string
	{
		$tag        = '*';
		$predicates = [];
		$cursor     = 0;
		$length     = strlen( $compound );

		// Leading type selector, if any.
		if ( preg_match( '/^([a-zA-Z][a-zA-Z0-9_-]*|\*)/', $compound, $match ) ) {
			$tag    = '*' === $match[1] ? '*' : strtolower( $match[1] );
			$cursor = strlen( $match[1] );
		}

		while ( $cursor < $length ) {
			$rest = substr( $compound, $cursor );

			if ( preg_match( '/^:not\(([^()]+)\)/', $rest, $match ) ) {
				$predicates[] = sprintf( 'not(%s)', self::translateSimple( $match[1] ) );
				$cursor      += strlen( $match[0] );

				continue;
			}

			$simple = self::matchSimple( $rest );

			if ( null === $simple ) {
				throw new UnsupportedSelectorException( sprintf(
					'Unsupported CSS selector fragment: "%s".',
					$rest,
				) );
			}

			[ $predicate, $consumed ] = $simple;

			$predicates[] = $predicate;
			$cursor      += $consumed;
		}

		return [] === $predicates
			? $tag
			: $tag . '[' . implode( ' and ', $predicates ) . ']';
	}

	/**
	 * Translate a single simple selector (`.class`, `#id`, `[attr]`,
	 * `[attr=value]`) into an XPath predicate.
	 *
	 * @since 1.5.5
	 *
	 * @throws UnsupportedSelectorException
	 */
	protected static function translateSimple( string $simple ): string
	{
		$simple = trim( $simple );
		$match  = self::matchSimple( $simple );

		if ( null === $match || $match[1] !== strlen( $simple ) ) {
			throw new UnsupportedSelectorException( sprintf(
				'Unsupported simple selector: "%s".',
				$simple,
			) );
		}

		return $match[0];
	}

	/**
	 * Match one simple selector at the head of `$input`.
	 *
	 * @since 1.5.5
	 *
	 * @return array{0: string, 1: int}|null Tuple of `[predicate, bytesConsumed]`, or null when nothing matched.
	 */
	protected static function matchSimple( string $input ): ?array
	{
		if ( preg_match( '/^\.([a-zA-Z0-9_-]+)/', $input, $match ) ) {
			return [
				sprintf(
					"contains(concat(' ', normalize-space(@class), ' '), %s)",
					self::quote( ' ' . $match[1] . ' ' ),
				),
				strlen( $match[0] ),
			];
		}

		if ( preg_match( '/^#([a-zA-Z0-9_-]+)/', $input, $match ) ) {
			return [ sprintf( '@id = %s', self::quote( $match[1] ) ), strlen( $match[0] ) ];
		}

		if ( preg_match( '/^\[([a-zA-Z_:][a-zA-Z0-9_:.-]*)\]/', $input, $match ) ) {
			return [ sprintf( '@%s', $match[1] ), strlen( $match[0] ) ];
		}

		// One pattern per quoting style rather than a single alternation:
		// PCRE reports non-participating groups as empty strings when a
		// LATER group matched, which would make an empty double-quoted
		// value indistinguishable from an unquoted one.
		$valuePatterns = [
			'/^\[([a-zA-Z_:][a-zA-Z0-9_:.-]*)="([^"]*)"\]/',
			"/^\[([a-zA-Z_:][a-zA-Z0-9_:.-]*)='([^']*)'\]/",
			'/^\[([a-zA-Z_:][a-zA-Z0-9_:.-]*)=([^\]"\']*)\]/',
		];

		foreach ( $valuePatterns as $pattern ) {
			if ( preg_match( $pattern, $input, $match ) ) {
				return [ sprintf( '@%s = %s', $match[1], self::quote( $match[2] ) ), strlen( $match[0] ) ];
			}
		}

		return null;
	}

	/**
	 * XPath 1.0 has no escape syntax for quotes inside string literals,
	 * so a value containing both quote characters has to be assembled
	 * with `concat()`. Class and attribute values coming out of
	 * `block.json` never need it, but the helper keeps a hostile
	 * selector from producing a malformed expression.
	 *
	 * @since 1.5.5
	 */
	protected static function quote( string $value ): string
	{
		if ( ! str_contains( $value, "'" ) ) {
			return "'" . $value . "'";
		}

		if ( ! str_contains( $value, '"' ) ) {
			return '"' . $value . '"';
		}

		$parts = [];

		foreach ( explode( "'", $value ) as $index => $chunk ) {
			if ( $index > 0 ) {
				$parts[] = '"\'"';
			}

			if ( '' !== $chunk ) {
				$parts[] = "'" . $chunk . "'";
			}
		}

		return 'concat(' . implode( ', ', $parts ) . ')';
	}
}
