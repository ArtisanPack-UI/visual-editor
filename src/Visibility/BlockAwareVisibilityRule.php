<?php

/**
 * Contract for a visibility rule that reads the block tree.
 *
 * {@see VisibilityRule} only sees request, user and time data, so it
 * can't react to what a block resolved to. A rule implementing this
 * interface also receives the block, `innerBlocks` included, after the
 * tree's `_resolved*` keys have been stamped. {@see VisibilityEvaluator}
 * calls {@see evaluateBlock()} in place of `evaluate()` for these rules.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Visibility;

interface BlockAwareVisibilityRule extends VisibilityRule
{
	/**
	 * Evaluate the rule against the block that carries it.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<string, mixed>  $ruleAttributes  The `artisanpackVisibility.{key()}` slice.
	 * @param  array<string, mixed>  $block           The full block node, including `innerBlocks`.
	 * @param  VisibilityContext     $context         The request-scoped context.
	 *
	 * @return VisibilityDecision The rule's decision.
	 */
	public function evaluateBlock( array $ruleAttributes, array $block, VisibilityContext $context ): VisibilityDecision;
}
