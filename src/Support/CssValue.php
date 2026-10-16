<?php

/**
 * Guards for theme- and author-supplied CSS values printed unescaped into
 * a `<style>` block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

class CssValue
{
	/**
	 * Whether a value can be written into a `<style>` block as-is.
	 *
	 * Rejects anything that closes the declaration or rule (`; { }`),
	 * the element (`< >`), opens or closes a comment, smuggles a CSS
	 * escape (`\`), or breaks the line. Quotes stay allowed — font-family
	 * stacks and some gradients need them — but every string must close,
	 * and parentheses and brackets must balance, without crossing,
	 * outside strings: an unterminated string, function or `[` block
	 * swallows every rule after it in the same block. The scan is
	 * quote-aware, so `rgb(")"` (an open function hiding its `)` inside a
	 * string) is rejected, and tracks the closer each open `(` / `[`
	 * expects, so `([)]` is rejected as crossed. An unquoted `url(` is
	 * read as the single url token a CSS tokenizer makes of it, ending at
	 * the first `)`. Mirrors cms-framework's
	 * `GlobalStylesEmitter::isSafeCssValue()`.
	 *
	 * @since 1.15.0
	 */
	public static function isPrintable( string $value ): bool
	{
		if ( 1 === preg_match( '#[;{}<>\\\\\x00-\x1F\x7F]|/\*|\*/#', $value ) ) {
			return false;
		}

		$quote   = null;
		$closers = [];
		$length  = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $value[ $i ];

			if ( null !== $quote ) {
				if ( $char === $quote ) {
					$quote = null;
				}

				continue;
			}

			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
			} elseif ( '(' === $char && self::opensUnquotedUrl( $value, $i ) ) {
				// An unquoted `url(` is one url token that ends at the
				// first `)`. A quote or `(` inside it makes a bad-url
				// token, which a tokenizer reads differently from this scan.
				$end = strpos( $value, ')', $i );

				if ( false === $end || false !== strpbrk( substr( $value, $i + 1, $end - $i - 1 ), "\"'(" ) ) {
					return false;
				}

				$i = $end;
			} elseif ( ! self::trackBlock( $char, $closers ) ) {
				return false;
			}
		}

		return null === $quote && [] === $closers;
	}

	/**
	 * Whether parentheses and brackets balance without crossing and no
	 * comment delimiter appears — the extra check for values that
	 * already passed a character filter which allows `/`, `*`, `(` and
	 * `)` (lengths, `calc()`, `var()`) but rejects quotes.
	 *
	 * @since 1.15.0
	 */
	public static function isBalanced( string $value ): bool
	{
		if ( str_contains( $value, '/*' ) || str_contains( $value, '*/' ) ) {
			return false;
		}

		$closers = [];

		foreach ( str_split( $value ) as $char ) {
			if ( ! self::trackBlock( $char, $closers ) ) {
				return false;
			}
		}

		return [] === $closers;
	}

	/**
	 * Track one character against the stack of closers the open `(` and
	 * `[` blocks expect. Returns false for a `)` or `]` that doesn't close
	 * the innermost open block (a stray or crossed closer).
	 *
	 * @since 1.15.0
	 *
	 * @param  list<string>  $closers
	 */
	protected static function trackBlock( string $char, array &$closers ): bool
	{
		if ( '(' === $char ) {
			$closers[] = ')';
		} elseif ( '[' === $char ) {
			$closers[] = ']';
		} elseif ( ')' === $char || ']' === $char ) {
			return array_pop( $closers ) === $char;
		}

		return true;
	}

	/**
	 * Whether the `(` at `$offset` opens an unquoted url token: it follows
	 * the whole identifier `url` (any case), and its first non-whitespace
	 * character isn't a quote.
	 *
	 * @since 1.15.0
	 */
	protected static function opensUnquotedUrl( string $value, int $offset ): bool
	{
		if ( $offset < 3 ) {
			return false;
		}

		return 1 === preg_match( '/(?<![\w\x80-\xFF-])url\(\s*+(?![\'"])/Ai', $value, $match, 0, $offset - 3 );
	}
}
