<?php

/**
 * Contract for every visibility rule.
 *
 * A rule inspects its slice of the block's `artisanpackVisibility`
 * attribute bag against the request-scoped {@see VisibilityContext} and
 * returns a {@see VisibilityDecision}. Rules that don't apply to the
 * current block (nothing configured on their `key()` slice) must return
 * `VisibilityDecision::visible()` — an unconfigured rule can never hide
 * a block.
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

interface VisibilityRule
{
	/**
	 * Unique kebab-case identifier used both as the attribute slice key
	 * inside `artisanpackVisibility` and as the debug reason emitted when
	 * the rule hides a block. Must match the corresponding editor
	 * subsection component's key so the panel's "only render active
	 * subsections" logic stays in sync.
	 *
	 * @since 1.4.0
	 */
	public function key(): string;

	/**
	 * Evaluate the rule against the given block attributes + request context.
	 *
	 * @param  array<string, mixed>  $ruleAttributes  The `artisanpackVisibility.{key()}` slice.
	 *
	 * @since 1.4.0
	 */
	public function evaluate( array $ruleAttributes, VisibilityContext $context ): VisibilityDecision;
}
