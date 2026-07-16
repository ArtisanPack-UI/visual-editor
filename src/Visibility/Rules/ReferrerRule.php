<?php

/**
 * Show / hide a block based on the request's `Referer` header.
 *
 * Attribute shape:
 *
 *     artisanpackVisibility.referrer = {
 *         "direction": "show",                   // "show" | "hide"
 *         "combinator": "any",                   // "any" | "all"
 *         "patterns": [
 *             "twitter.com",                     // literal hostname
 *             "*.example.com",                   // subdomain wildcard
 *             "(direct)"                         // no / empty referer
 *         ]
 *     }
 *
 * Referer parsing strips protocol + port + path so the patterns are
 * always hostname-only. Editors that need path-level matching should
 * reach for the query-string rule instead.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Visibility\Rules;

use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityDecision;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityRule;

class ReferrerRule implements VisibilityRule
{
	public const DIRECT = '(direct)';

	public function key(): string
	{
		return 'referrer';
	}

	public function evaluate( array $ruleAttributes, VisibilityContext $context ): VisibilityDecision
	{
		$patterns = isset( $ruleAttributes['patterns'] ) && is_array( $ruleAttributes['patterns'] )
			? array_values( array_filter( $ruleAttributes['patterns'], 'is_string' ) )
			: [];

		if ( [] === $patterns ) {
			return VisibilityDecision::visible();
		}

		$direction  = ( 'hide' === ( $ruleAttributes['direction'] ?? 'show' ) ) ? 'hide' : 'show';
		$combinator = ( 'all'  === ( $ruleAttributes['combinator'] ?? 'any' ) ) ? 'all'  : 'any';

		$host = $this->hostFromReferer( $context->referrer );

		$matches = 'all' === $combinator
			? $this->matchesAll( $patterns, $host )
			: $this->matchesAny( $patterns, $host );

		$visible = 'show' === $direction ? $matches : ! $matches;

		return $visible
			? VisibilityDecision::visible()
			: VisibilityDecision::hidden( [ $this->key() ] );
	}

	/**
	 * @param  array<int, string>  $patterns
	 */
	protected function matchesAny( array $patterns, string $host ): bool
	{
		foreach ( $patterns as $pattern ) {
			if ( $this->matchPattern( $pattern, $host ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param  array<int, string>  $patterns
	 */
	protected function matchesAll( array $patterns, string $host ): bool
	{
		foreach ( $patterns as $pattern ) {
			if ( ! $this->matchPattern( $pattern, $host ) ) {
				return false;
			}
		}
		return true;
	}

	protected function matchPattern( string $pattern, string $host ): bool
	{
		if ( self::DIRECT === $pattern ) {
			return '' === $host;
		}

		if ( '' === $host ) {
			return false;
		}

		if ( str_starts_with( $pattern, '*.' ) ) {
			// Lowercase the suffix so brand-cased patterns like
			// `*.Example.com` match `foo.example.com` — the literal
			// branch already treats hostnames case-insensitively via
			// `strcasecmp`, and hostnames are canonical lowercase per
			// RFC 5891.
			$suffix = strtolower( substr( $pattern, 2 ) );
			if ( '' === $suffix ) {
				return false;
			}
			return $host === $suffix || str_ends_with( $host, '.' . $suffix );
		}

		return strcasecmp( $host, $pattern ) === 0;
	}

	protected function hostFromReferer( string $referer ): string
	{
		if ( '' === $referer ) {
			return '';
		}

		$parts = parse_url( $referer );

		if ( ! is_array( $parts ) || ! isset( $parts['host'] ) || ! is_string( $parts['host'] ) ) {
			return '';
		}

		return strtolower( $parts['host'] );
	}
}
