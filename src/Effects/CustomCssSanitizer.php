<?php

/**
 * Sanitizer for a block's Custom CSS (#881).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Effects;

/**
 * Validates and normalises the Custom CSS an author attaches to one block.
 *
 * The CSS is printed inside the block's own rule, `.ve-fx-<hash>{…}`, and
 * relies on native CSS nesting: bare declarations style the block, and
 * nested rules (`& img { … }`, `&:hover { … }`, `@media (…) { … }`) style
 * it or its descendants. Scoping holds only while the text can't close
 * that rule early or leave the `<style>` element, so a value is rejected
 * outright — never partially kept — when it:
 *
 *  - contains `<`, a backslash or a control character other than tab,
 *    newline or carriage return;
 *  - closes more braces than it opens, or leaves a brace, string or
 *    comment open;
 *  - uses an at-rule other than `@media`, `@supports` or `@container`;
 *  - uses the legacy script vectors `expression(`, `javascript:`,
 *    `-moz-binding` or `behavior:`;
 *  - is longer than {@see self::MAX_LENGTH} characters.
 *
 * Nesting keeps every rule anchored to the block, but a selector can
 * still reach outside it through the block (`:root:has(&) body`), so this
 * is a guard against accidents and broken markup, not a sandbox — which is
 * why writing Custom CSS needs {@see BlockCssAccess}.
 *
 * Comments are removed and whitespace outside strings collapses to single
 * spaces. The JS twin (`resources/js/visual-editor/effects/compiler.ts`
 * and the React/Vue renderers' `support/effects.ts`) implements the same
 * scan, so every surface prints byte-identical CSS.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.15.0
 */
class CustomCssSanitizer
{
	/**
	 * Longest Custom CSS value accepted, in characters.
	 *
	 * @since 1.15.0
	 */
	public const MAX_LENGTH = 10000;

	/**
	 * At-rules allowed inside a block's Custom CSS.
	 *
	 * @since 1.15.0
	 *
	 * @var array<int, string>
	 */
	public const ALLOWED_AT_RULES = [ 'media', 'supports', 'container' ];

	/**
	 * Sanitize a Custom CSS value. Returns the normalised CSS, an empty
	 * string for an empty value, or null when the value is rejected.
	 *
	 * @since 1.15.0
	 */
	public static function sanitize( mixed $css ): ?string
	{
		if ( ! is_string( $css ) ) {
			return '';
		}

		if ( '' === trim( $css ) ) {
			return '';
		}

		if ( mb_strlen( $css ) > self::MAX_LENGTH ) {
			return null;
		}

		if ( 1 === preg_match( '/[<\\\\\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $css ) ) {
			return null;
		}

		$lower = strtolower( $css );

		foreach ( [ 'expression(', 'javascript:', '-moz-binding', 'behavior:' ] as $vector ) {
			if ( str_contains( $lower, $vector ) ) {
				return null;
			}
		}

		$out     = '';
		$quote   = null;
		$depth   = 0;
		$pending = false;
		$length  = strlen( $css );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $css[ $i ];

			if ( null !== $quote ) {
				if ( "\n" === $char || "\r" === $char ) {
					return null;
				}

				$out .= $char;

				if ( $char === $quote ) {
					$quote = null;
				}

				continue;
			}

			if ( '/' === $char && '*' === ( $css[ $i + 1 ] ?? '' ) ) {
				$end = strpos( $css, '*/', $i + 2 );

				if ( false === $end ) {
					return null;
				}

				$i       = $end + 1;
				$pending = true;

				continue;
			}

			if ( ' ' === $char || "\t" === $char || "\n" === $char || "\r" === $char ) {
				$pending = true;

				continue;
			}

			if ( $pending && '' !== $out ) {
				$out .= ' ';
			}

			$pending = false;

			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
			} elseif ( '{' === $char ) {
				++$depth;
			} elseif ( '}' === $char ) {
				if ( --$depth < 0 ) {
					return null;
				}
			} elseif ( '@' === $char ) {
				preg_match( '/[a-zA-Z-]*/A', $css, $match, 0, $i + 1 );

				if ( ! in_array( strtolower( $match[0] ?? '' ), self::ALLOWED_AT_RULES, true ) ) {
					return null;
				}
			}

			$out .= $char;
		}

		if ( null !== $quote || 0 !== $depth ) {
			return null;
		}

		return $out;
	}
}
