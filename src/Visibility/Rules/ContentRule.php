<?php

/**
 * Show / hide a block based on the dynamic content inside it.
 *
 * Attribute shape:
 *
 *     artisanpackVisibility.content = {
 *         "source": "relatedPosts",   // a registered ContentSource key
 *         "condition": "hasResults",  // one of the source's conditions
 *         "match": "any",             // "any" | "all"
 *         "direction": "show"         // "show" | "hide"
 *     }
 *
 * The rule searches the block and its descendants for blocks matching
 * the source and combines their results with `match`. `direction: show`
 * renders the block when that passes; `direction: hide` renders it when
 * it fails, which is how an empty-state fallback is built.
 *
 * A rule that can't find anything to check is inert: an unknown source
 * or condition, or no matching block, leaves the block visible and
 * records a reason for the `ap.visualEditor.visibility.evaluated` hook.
 * A matching block that never resolved counts as having no content.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Visibility\Rules;

use ArtisanPackUI\VisualEditor\Visibility\BlockAwareVisibilityRule;
use ArtisanPackUI\VisualEditor\Visibility\ContentSource;
use ArtisanPackUI\VisualEditor\Visibility\ContentSourceRegistry;
use ArtisanPackUI\VisualEditor\Visibility\TreePruner;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityDecision;

class ContentRule implements BlockAwareVisibilityRule
{
	public const REASON_NO_SOURCE         = 'content:no-source';
	public const REASON_UNKNOWN_SOURCE    = 'content:unknown-source';
	public const REASON_UNKNOWN_CONDITION = 'content:unknown-condition';
	public const REASON_UNRESOLVED        = 'content:unresolved';

	/**
	 * @since 1.20.0
	 *
	 * @param  ContentSourceRegistry  $sources  The registered content sources.
	 */
	public function __construct( protected ContentSourceRegistry $sources )
	{
	}

	/**
	 * @since 1.20.0
	 *
	 * @return string The rule key.
	 */
	public function key(): string
	{
		return 'content';
	}

	/**
	 * This rule needs the block, so without one it has nothing to check.
	 * {@see \ArtisanPackUI\VisualEditor\Visibility\VisibilityEvaluator}
	 * calls {@see evaluateBlock()} instead.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<string, mixed>  $ruleAttributes  The `artisanpackVisibility.content` slice.
	 * @param  VisibilityContext     $context         The request-scoped context.
	 *
	 * @return VisibilityDecision Always visible.
	 */
	public function evaluate( array $ruleAttributes, VisibilityContext $context ): VisibilityDecision
	{
		return VisibilityDecision::visible();
	}

	/**
	 * @since 1.20.0
	 *
	 * @param  array<string, mixed>  $ruleAttributes  The `artisanpackVisibility.content` slice.
	 * @param  array<string, mixed>  $block           The block carrying the rule.
	 * @param  VisibilityContext     $context         The request-scoped context.
	 *
	 * @return VisibilityDecision The rule's decision.
	 */
	public function evaluateBlock( array $ruleAttributes, array $block, VisibilityContext $context ): VisibilityDecision
	{
		$source = is_string( $ruleAttributes['source'] ?? null )
			? $this->sources->get( $ruleAttributes['source'] )
			: null;

		if ( null === $source ) {
			return VisibilityDecision::visible( [ self::REASON_UNKNOWN_SOURCE ] );
		}

		$condition = $ruleAttributes['condition'] ?? null;

		if ( ! is_string( $condition ) || ! array_key_exists( $condition, $source->conditions() ) ) {
			return VisibilityDecision::visible( [ self::REASON_UNKNOWN_CONDITION ] );
		}

		$matched = $this->collect( $block, $source, 0 );

		if ( [] === $matched ) {
			return VisibilityDecision::visible( [ self::REASON_NO_SOURCE ] );
		}

		$direction = 'hide' === ( $ruleAttributes['direction'] ?? 'show' ) ? 'hide' : 'show';
		$match     = 'all' === ( $ruleAttributes['match'] ?? 'any' ) ? 'all' : 'any';

		$results       = [];
		$hasUnresolved = false;

		foreach ( $matched as $candidate ) {
			$isResolved    = $source->isResolved( $candidate );
			$hasUnresolved = $hasUnresolved || ! $isResolved;
			$results[]     = $isResolved && $source->passes( $condition, $candidate );
		}

		$passes  = 'all' === $match ? ! in_array( false, $results, true ) : in_array( true, $results, true );
		$visible = 'show' === $direction ? $passes : ! $passes;
		$reasons = $hasUnresolved ? [ self::REASON_UNRESOLVED ] : [];

		return $visible
			? VisibilityDecision::visible( $reasons )
			: VisibilityDecision::hidden( [ $this->key(), ...$reasons ] );
	}

	/**
	 * Collect the block and its descendants that match the source, in
	 * document order.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<string, mixed>  $block   The block to search from.
	 * @param  ContentSource         $source  The source to match.
	 * @param  int                   $depth   Current nesting depth.
	 *
	 * @return array<int, array<string, mixed>> The matching blocks.
	 */
	protected function collect( array $block, ContentSource $source, int $depth ): array
	{
		$found = $source->matches( $block ) ? [ $block ] : [];

		if ( $depth >= TreePruner::MAX_DEPTH || ! isset( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
			return $found;
		}

		foreach ( $block['innerBlocks'] as $child ) {
			if ( is_array( $child ) ) {
				array_push( $found, ...$this->collect( $child, $source, $depth + 1 ) );
			}
		}

		return $found;
	}
}
