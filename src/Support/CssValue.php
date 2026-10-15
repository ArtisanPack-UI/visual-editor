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
	 * and parentheses must balance outside strings: an unterminated
	 * string or function swallows every rule after it in the same block.
	 * The scan is quote-aware, so `rgb(")"` (an open function hiding its
	 * `)` inside a string) is rejected.
	 *
	 * @since 1.15.0
	 */
	public static function isPrintable( string $value ): bool
	{
		if ( 1 === preg_match( '#[;{}<>\\\\\x00-\x1F\x7F]|/\*|\*/#', $value ) ) {
			return false;
		}

		$depth = 0;
		$quote = null;

		foreach ( str_split( $value ) as $char ) {
			if ( null !== $quote ) {
				if ( $char === $quote ) {
					$quote = null;
				}

				continue;
			}

			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
			} elseif ( '(' === $char ) {
				++$depth;
			} elseif ( ')' === $char && --$depth < 0 ) {
				return false;
			}
		}

		return null === $quote && 0 === $depth;
	}

	/**
	 * Whether parentheses balance and no comment delimiter appears — the
	 * extra check for values that already passed a character whitelist
	 * which allows `/`, `*`, `(` and `)` (lengths, `calc()`, `var()`).
	 *
	 * @since 1.15.0
	 */
	public static function isBalanced( string $value ): bool
	{
		if ( str_contains( $value, '/*' ) || str_contains( $value, '*/' ) ) {
			return false;
		}

		$depth = 0;

		foreach ( str_split( $value ) as $char ) {
			if ( '(' === $char ) {
				++$depth;
			} elseif ( ')' === $char && --$depth < 0 ) {
				return false;
			}
		}

		return 0 === $depth;
	}
}
