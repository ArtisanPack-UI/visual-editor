<?php

/**
 * Show / hide a block based on the visitor's role list.
 *
 * Attribute shape:
 *
 *     artisanpackVisibility.userRole = {
 *         "direction": "show",         // "show" | "hide"
 *         "combinator": "any",         // "any" | "all"
 *         "roles": ["admin", "editor"]
 *     }
 *
 * Anonymous evaluation short-circuits: an anonymous visitor has an
 * empty role list, so a `direction: show + any` rule with any roles
 * configured is automatically hidden. A `direction: hide + any` rule
 * with any roles is automatically visible. No DB query is issued when
 * `context.isAuthenticated === false`.
 *
 * Role resolution itself lives in {@see \ArtisanPackUI\VisualEditor\Visibility\VisibilityEvaluator::resolveRoles()}
 * — it prefers cms-framework's `RoleManager` and falls back to Spatie /
 * Laravel-standard patterns.
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

class UserRoleRule implements VisibilityRule
{
	public function key(): string
	{
		return 'userRole';
	}

	public function evaluate( array $ruleAttributes, VisibilityContext $context ): VisibilityDecision
	{
		$roles = isset( $ruleAttributes['roles'] ) && is_array( $ruleAttributes['roles'] )
			? array_values( array_filter( $ruleAttributes['roles'], 'is_string' ) )
			: [];

		if ( [] === $roles ) {
			return VisibilityDecision::visible();
		}

		$direction  = ( 'hide' === ( $ruleAttributes['direction']  ?? 'show' ) ) ? 'hide' : 'show';
		$combinator = ( 'all'  === ( $ruleAttributes['combinator'] ?? 'any'  ) ) ? 'all'  : 'any';

		// Anonymous short-circuit: no user, no roles to match.
		if ( ! $context->isAuthenticated ) {
			$matches = false;
		} else {
			$matches = 'all' === $combinator
				? $this->matchesAll( $roles, $context->roles )
				: $this->matchesAny( $roles, $context->roles );
		}

		$visible = 'show' === $direction ? $matches : ! $matches;

		return $visible
			? VisibilityDecision::visible()
			: VisibilityDecision::hidden( [ $this->key() ] );
	}

	/**
	 * @param  array<int, string>  $required
	 * @param  array<int, string>  $userRoles
	 */
	protected function matchesAny( array $required, array $userRoles ): bool
	{
		foreach ( $required as $role ) {
			if ( in_array( $role, $userRoles, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param  array<int, string>  $required
	 * @param  array<int, string>  $userRoles
	 */
	protected function matchesAll( array $required, array $userRoles ): bool
	{
		foreach ( $required as $role ) {
			if ( ! in_array( $role, $userRoles, true ) ) {
				return false;
			}
		}
		return true;
	}
}
