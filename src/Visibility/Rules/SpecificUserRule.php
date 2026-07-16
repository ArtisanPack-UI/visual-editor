<?php

/**
 * Show / hide a block for one or more specific users.
 *
 * Users are identified by email in the persisted attribute so the
 * value survives an id rotation and is human-readable when reviewing
 * saved content. The editor picker autocompletes against
 * `GET /visual-editor/api/users/search` and writes the selected
 * `{ id, email, name }` triples back into the block.
 *
 * Attribute shape:
 *
 *     artisanpackVisibility.specificUser = {
 *         "direction": "show",
 *         "users": [
 *             { "id": 42, "email": "me@example.com", "name": "Ada" }
 *         ]
 *     }
 *
 * Anonymous evaluation always fails to match (there is no visitor to
 * compare against). See {@see UserRoleRule} for the same short-circuit
 * pattern.
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

class SpecificUserRule implements VisibilityRule
{
	public function key(): string
	{
		return 'specificUser';
	}

	public function evaluate( array $ruleAttributes, VisibilityContext $context ): VisibilityDecision
	{
		$users = isset( $ruleAttributes['users'] ) && is_array( $ruleAttributes['users'] )
			? $ruleAttributes['users']
			: [];

		if ( [] === $users ) {
			return VisibilityDecision::visible();
		}

		$direction = ( 'hide' === ( $ruleAttributes['direction'] ?? 'show' ) ) ? 'hide' : 'show';

		$matches = $this->currentUserMatches( $users, $context );

		$visible = 'show' === $direction ? $matches : ! $matches;

		return $visible
			? VisibilityDecision::visible()
			: VisibilityDecision::hidden( [ $this->key() ] );
	}

	/**
	 * @param  array<int, mixed>  $users
	 */
	protected function currentUserMatches( array $users, VisibilityContext $context ): bool
	{
		if ( ! $context->isAuthenticated ) {
			return false;
		}

		$viewerEmail = null !== $context->userEmail ? strtolower( $context->userEmail ) : null;
		$viewerId    = $context->userId;

		foreach ( $users as $user ) {
			if ( ! is_array( $user ) ) {
				continue;
			}

			$email = isset( $user['email'] ) && is_string( $user['email'] ) ? strtolower( $user['email'] ) : null;
			// Accept both numeric IDs (integer keys) and UUID / other
			// string keys (`HasUuids`) so persisted picks survive
			// hosts on either model keying scheme.
			$id = isset( $user['id'] ) && is_scalar( $user['id'] ) && '' !== (string) $user['id']
				? (string) $user['id']
				: null;

			if ( null !== $viewerEmail && null !== $email && $viewerEmail === $email ) {
				return true;
			}

			if ( null !== $viewerId && null !== $id && (string) $viewerId === $id ) {
				return true;
			}
		}

		return false;
	}
}
