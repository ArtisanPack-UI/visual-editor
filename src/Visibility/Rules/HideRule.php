<?php

/**
 * Master "Hide Block" toggle.
 *
 * When `artisanpackVisibility.hide.hidden === true`, the block is
 * dropped from rendered output. This is intentionally the coarsest
 * rule — no context is consulted; the block simply doesn't render.
 * The editor canvas draws its own hatched overlay so authors can
 * still see and select the block while it's toggled off (#491).
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

class HideRule implements VisibilityRule
{
	public function key(): string
	{
		return 'hide';
	}

	public function evaluate( array $ruleAttributes, VisibilityContext $context ): VisibilityDecision
	{
		if ( true === ( $ruleAttributes['hidden'] ?? false ) ) {
			return VisibilityDecision::hidden( [ $this->key() ] );
		}

		return VisibilityDecision::visible();
	}
}
