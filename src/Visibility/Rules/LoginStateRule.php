<?php

/**
 * Show / hide a block based on the visitor's authentication state.
 *
 * Attribute shape:
 *
 *     artisanpackVisibility.loginState = {
 *         "state": "loggedIn"       // "loggedIn" | "loggedOut" | "either"
 *     }
 *
 * "either" is functionally a no-op — the panel still writes it so
 * legacy content that persisted the tri-state doesn't fall out of
 * schema, but the rule short-circuits to visible.
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

class LoginStateRule implements VisibilityRule
{
	public const STATE_LOGGED_IN  = 'loggedIn';
	public const STATE_LOGGED_OUT = 'loggedOut';
	public const STATE_EITHER     = 'either';

	public function key(): string
	{
		return 'loginState';
	}

	public function evaluate( array $ruleAttributes, VisibilityContext $context ): VisibilityDecision
	{
		$state = isset( $ruleAttributes['state'] ) && is_string( $ruleAttributes['state'] )
			? $ruleAttributes['state']
			: self::STATE_EITHER;

		if ( self::STATE_EITHER === $state ) {
			return VisibilityDecision::visible();
		}

		$visible = self::STATE_LOGGED_IN === $state
			? $context->isAuthenticated
			: ! $context->isAuthenticated;

		return $visible
			? VisibilityDecision::visible()
			: VisibilityDecision::hidden( [ $this->key() ] );
	}
}
