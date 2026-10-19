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
 * Two families of keys are accepted (#974):
 *
 *   - Desktop-first device keys (`mobile`, `tablet`) cover everything
 *     at or below their max-width: `mobile` hides at
 *     `@media (max-width:767px)`, `tablet` at `(max-width:1023px)`.
 *     These are the only keys that reach phone widths below 640px.
 *   - Legacy mobile-first keys (`sm`, `md`, `lg`, `xl`, `2xl`) are
 *     each a width RANGE from their min-width to one pixel below the
 *     next one's (`md` = 768–1023px; `2xl` is open-ended). Content
 *     saved with them renders exactly as before.
 *
 * Keys of both families may be mixed. `direction: hide` hides the
 * block in the union of the listed keys' ranges.
 *
 * `direction: show` means the block is visible in the union of the
 * listed ranges and hidden elsewhere. The hidden set is expressed as
 * legacy keys: every unlisted legacy range that starts above the
 * widest listed device key's max-width. "Show only on mobile"
 * (`["mobile"]`) therefore hides `md`, `lg`, `xl`, `2xl` — i.e. from
 * 768px up — and `["tablet"]` hides from 1024px up. With only legacy
 * keys listed, widths below the first legacy min-width (0–639px) stay
 * visible, as they always have. When a host narrows a device key so
 * it no longer ends one pixel below a legacy min-width, `show` rounds
 * to the legacy boundaries: a legacy range that starts inside the
 * device's width stays visible in full.
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
		$direction  = ( 'hide' === ( $ruleAttributes['direction'] ?? 'hide' ) ) ? 'hide' : 'show';
		$configured = isset( $ruleAttributes['breakpoints'] ) && is_array( $ruleAttributes['breakpoints'] )
			? array_values( array_unique( array_filter( $ruleAttributes['breakpoints'], 'is_string' ) ) )
			: [];

		if ( [] === $configured ) {
			return VisibilityDecision::visible();
		}

		$legacy  = $this->allBreakpoints();
		$devices = $this->deviceBreakpoints();

		if ( 'hide' === $direction ) {
			$known  = array_merge( array_keys( $legacy ), array_keys( $devices ) );
			$hidden = array_values( array_intersect( $known, $configured ) );
		} else {
			$deviceCeiling = 0;

			foreach ( $devices as $key => $maxWidth ) {
				if ( in_array( $key, $configured, true ) ) {
					$deviceCeiling = max( $deviceCeiling, $maxWidth );
				}
			}

			$hidden = [];

			foreach ( $legacy as $key => $minWidth ) {
				if ( ! in_array( $key, $configured, true ) && $minWidth > $deviceCeiling ) {
					$hidden[] = $key;
				}
			}
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

	/**
	 * The desktop-first device breakpoints as `[key => max-width-px]`.
	 *
	 * @since 1.15.2
	 *
	 * @return array<string, int>
	 */
	protected function deviceBreakpoints(): array
	{
		if ( ! method_exists( $this->breakpoints, 'entries' ) ) {
			return [];
		}

		$out = [];

		foreach ( $this->breakpoints->entries() as $key => $spec ) {
			$maxWidth = $spec['maxWidthPx'] ?? null;

			if ( is_int( $maxWidth ) && $maxWidth > 0 && null === ( $spec['minWidthPx'] ?? null ) ) {
				$out[ (string) $key ] = $maxWidth;
			}
		}

		return $out;
	}
}
