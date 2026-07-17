<?php

/**
 * Server-side block-tree pruner used by the React and Vue renderers.
 *
 * Both client-side renderers hydrate from a server-produced JSON tree
 * embedded in the page (Inertia payload, `<script type="application/
 * json">` bootstrap, etc.). Because visibility must not leak markup to
 * unauthorised visitors, the correct place to gate the tree is on the
 * server *before* it is serialised — not in the client renderer. This
 * pruner walks the tree the same way the Blade `BlockRenderer` does,
 * dropping blocks whose {@see VisibilityEvaluator::evaluate()} returns
 * `hidden()`, and stamping a `_veHiddenBreakpoints` side-channel key on
 * blocks whose decision was `cssHidden()` so the client renderer can
 * emit matching `@media` rules.
 *
 * Preserves the tree's inner-block nesting. When a container block is
 * dropped, its inner blocks are dropped with it — the whole subtree
 * disappears, matching the Blade renderer's behavior.
 *
 * Hosts serialising trees to Inertia / a JSON payload pipe the tree
 * through {@see prune()} in whatever controller assembles the payload.
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

class TreePruner
{
	/**
	 * Hard depth cap on inner-block recursion. A persisted tree
	 * deeper than this is treated as malformed and passed through
	 * without pruning (fail-open on visibility) so a compromised
	 * import or a pathological block payload cannot stack-overflow
	 * the PHP process on every subsequent visit.
	 *
	 * @since 1.4.0
	 */
	public const MAX_DEPTH = 128;

	public function __construct( protected VisibilityEvaluator $evaluator )
	{
	}

	/**
	 * Prune every hidden block from the given tree.
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 * @param  VisibilityContext|null            $context  Optional pre-built context; otherwise built from the request.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @since 1.4.0
	 */
	public function prune( array $tree, ?VisibilityContext $context = null ): array
	{
		if ( ! $this->evaluator->enabled() ) {
			return $tree;
		}

		$ctx = $context ?? $this->evaluator->contextFromRequest();

		return $this->walk( $tree, $ctx, 0 );
	}

	/**
	 * @param  array<int, array<string, mixed>>  $tree
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function walk( array $tree, VisibilityContext $context, int $depth ): array
	{
		if ( $depth > self::MAX_DEPTH ) {
			report( new \RuntimeException( sprintf(
				'TreePruner recursion cap (%d) exceeded — passing subtree through without pruning. Likely a malformed or attacker-crafted block payload.',
				self::MAX_DEPTH,
			) ) );

			return $tree;
		}

		$out = [];

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$decision = $this->evaluator->evaluate( $block, $context );

			if ( $decision->isHidden() ) {
				continue;
			}

			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->walk( $block['innerBlocks'], $context, $depth + 1 );
			}

			if ( $decision->isCssHidden() && [] !== $decision->hiddenBreakpoints ) {
				$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] )
					? $block['attributes']
					: [];

				$attributes['_veHiddenBreakpoints'] = $decision->hiddenBreakpoints;
				$block['attributes']                = $attributes;
			}

			$out[] = $block;
		}

		return $out;
	}
}
