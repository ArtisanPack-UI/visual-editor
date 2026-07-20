<?php

/**
 * Per-block visibility decision returned by
 * {@see \ArtisanPackUI\VisualEditor\Visibility\VisibilityEvaluator::evaluate()}.
 *
 * Three outcomes:
 *
 *   - `visible()`      — the block renders as normal.
 *   - `hidden()`       — the block is omitted from rendered output; the
 *                        renderers never emit markup for it. Server-side
 *                        drop so no flash of hidden content occurs.
 *   - `cssHidden()`    — the block renders but with breakpoint-specific
 *                        `display:none` CSS attached; the screen-size rule
 *                        is the only user of this outcome today because
 *                        viewport size is a client-only signal.
 *
 * The `reasons` array is opaque and only used by the debug hook
 * (`ap.visualEditor.visibility.evaluated`) so the "why is this hidden?"
 * developer tooling can surface the failing rule name(s) without
 * reserving specific fields on the DTO.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Visibility;

class VisibilityDecision
{
	public const OUTCOME_VISIBLE     = 'visible';
	public const OUTCOME_HIDDEN      = 'hidden';
	public const OUTCOME_CSS_HIDDEN  = 'css-hidden';

	/**
	 * @param  array<int, string>  $reasons          Rule keys that contributed to the decision.
	 * @param  array<int, string>  $hiddenBreakpoints Breakpoint keys the block should be `display:none` at.
	 */
	public function __construct(
		public readonly string $outcome,
		public readonly array $reasons = [],
		public readonly array $hiddenBreakpoints = [],
	) {
	}

	public static function visible(): self
	{
		return new self( self::OUTCOME_VISIBLE );
	}

	/**
	 * @param  array<int, string>  $reasons
	 */
	public static function hidden( array $reasons = [] ): self
	{
		return new self( self::OUTCOME_HIDDEN, $reasons );
	}

	/**
	 * @param  array<int, string>  $hiddenBreakpoints
	 * @param  array<int, string>  $reasons
	 */
	public static function cssHidden( array $hiddenBreakpoints, array $reasons = [] ): self
	{
		return new self( self::OUTCOME_CSS_HIDDEN, $reasons, $hiddenBreakpoints );
	}

	public function isVisible(): bool
	{
		return self::OUTCOME_VISIBLE === $this->outcome;
	}

	public function isHidden(): bool
	{
		return self::OUTCOME_HIDDEN === $this->outcome;
	}

	public function isCssHidden(): bool
	{
		return self::OUTCOME_CSS_HIDDEN === $this->outcome;
	}

	/**
	 * Merge two decisions. Order of precedence:
	 *   1. Any `hidden()` wins outright (the block is dropped from output).
	 *   2. CSS-hidden breakpoints from both sides combine.
	 *   3. Otherwise `visible()`.
	 *
	 * @since 1.4.0
	 */
	public function combine( self $other ): self
	{
		if ( $this->isHidden() || $other->isHidden() ) {
			return self::hidden( array_values( array_unique( array_merge( $this->reasons, $other->reasons ) ) ) );
		}

		if ( $this->isCssHidden() || $other->isCssHidden() ) {
			return self::cssHidden(
				array_values( array_unique( array_merge( $this->hiddenBreakpoints, $other->hiddenBreakpoints ) ) ),
				array_values( array_unique( array_merge( $this->reasons, $other->reasons ) ) ),
			);
		}

		return self::visible();
	}
}
