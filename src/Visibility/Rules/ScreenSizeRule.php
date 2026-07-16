<?php

/**
 * Show / hide a block by named breakpoint.
 *
 * Viewport size is a client-only signal — the server can't know how
 * wide the visitor's window is at render time. So this rule returns
 * `cssHidden([...breakpoints])` and lets each renderer's CSS emitter
 * translate that into `@media` `display:none` rules. Zero runtime JS.
 *
 * Attribute shape:
 *
 *     artisanpackVisibility.screenSize = {
 *         "direction": "show",           // "show" | "hide"
 *         "breakpoints": ["sm", "md"]    // keys from the BreakpointRegistry
 *     }
 *
 * `direction: show` means the block is visible AT those breakpoints
 * and hidden elsewhere. `direction: hide` is the inverse.
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

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityDecision;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityRule;

class ScreenSizeRule implements VisibilityRule
{
	public function __construct( protected BreakpointRegistry $breakpoints )
	{
	}

	public function key(): string
	{
		return 'screenSize';
	}

	public function evaluate( array $ruleAttributes, VisibilityContext $context ): VisibilityDecision
	{
		$direction = ( 'hide' === ( $ruleAttributes['direction'] ?? 'hide' ) ) ? 'hide' : 'show';
		$configured = isset( $ruleAttributes['breakpoints'] ) && is_array( $ruleAttributes['breakpoints'] )
			? array_values( array_unique( array_filter( $ruleAttributes['breakpoints'], 'is_string' ) ) )
			: [];

		if ( [] === $configured ) {
			return VisibilityDecision::visible();
		}

		$known = array_keys( $this->allBreakpoints() );

		if ( 'hide' === $direction ) {
			$hidden = array_values( array_intersect( $known, $configured ) );
		} else {
			$hidden = array_values( array_diff( $known, $configured ) );
		}

		if ( [] === $hidden ) {
			return VisibilityDecision::visible();
		}

		return VisibilityDecision::cssHidden( $hidden, [ $this->key() ] );
	}

	/**
	 * @return array<string, int>
	 */
	protected function allBreakpoints(): array
	{
		if ( method_exists( $this->breakpoints, 'all' ) ) {
			$all = $this->breakpoints->all();
			if ( is_array( $all ) ) {
				return $all;
			}
		}

		return [];
	}
}
