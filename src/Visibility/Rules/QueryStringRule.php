<?php

/**
 * Show / hide a block based on the request's query string.
 *
 * Attribute shape:
 *
 *     artisanpackVisibility.queryString = {
 *         "direction": "show",              // "show" | "hide"
 *         "combinator": "any",              // "any" | "all"
 *         "clauses": [
 *             { "key": "utm_source", "value": "newsletter" },
 *             { "key": "debug",      "value": "*" }         // "*" = key present, any value
 *         ]
 *     }
 *
 * `direction: show` means the block is visible when the clauses match.
 * `direction: hide` means it's hidden when they match.
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

class QueryStringRule implements VisibilityRule
{
	public function key(): string
	{
		return 'queryString';
	}

	public function evaluate( array $ruleAttributes, VisibilityContext $context ): VisibilityDecision
	{
		$clauses = isset( $ruleAttributes['clauses'] ) && is_array( $ruleAttributes['clauses'] )
			? array_values( array_filter( $ruleAttributes['clauses'], 'is_array' ) )
			: [];

		if ( [] === $clauses ) {
			return VisibilityDecision::visible();
		}

		$direction  = ( 'hide' === ( $ruleAttributes['direction'] ?? 'show' ) ) ? 'hide' : 'show';
		$combinator = ( 'all'  === ( $ruleAttributes['combinator'] ?? 'any' ) ) ? 'all'  : 'any';

		$matches = 'all' === $combinator ? $this->matchesAll( $clauses, $context ) : $this->matchesAny( $clauses, $context );

		$visible = 'show' === $direction ? $matches : ! $matches;

		return $visible
			? VisibilityDecision::visible()
			: VisibilityDecision::hidden( [ $this->key() ] );
	}

	/**
	 * @param  array<int, array<string, mixed>>  $clauses
	 */
	protected function matchesAny( array $clauses, VisibilityContext $context ): bool
	{
		foreach ( $clauses as $clause ) {
			if ( $this->matchClause( $clause, $context ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param  array<int, array<string, mixed>>  $clauses
	 */
	protected function matchesAll( array $clauses, VisibilityContext $context ): bool
	{
		foreach ( $clauses as $clause ) {
			if ( ! $this->matchClause( $clause, $context ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param  array<string, mixed>  $clause
	 */
	protected function matchClause( array $clause, VisibilityContext $context ): bool
	{
		$key   = isset( $clause['key'] ) && is_string( $clause['key'] ) ? trim( $clause['key'] ) : '';
		$value = isset( $clause['value'] ) && ( is_string( $clause['value'] ) || is_numeric( $clause['value'] ) )
			? (string) $clause['value']
			: '';

		if ( '' === $key ) {
			return false;
		}

		if ( ! array_key_exists( $key, $context->queryString ) ) {
			return false;
		}

		if ( '*' === $value ) {
			return true;
		}

		return $value === $context->queryString[ $key ];
	}
}
