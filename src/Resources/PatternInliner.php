<?php

/**
 * PatternInliner service.
 *
 * Walks a saved Gutenberg block tree and replaces every `core/block`
 * (synced-pattern reference) with the same block carrying its resolved
 * pattern's blocks as `innerBlocks`. Front-end renderers (Blade, React,
 * Vue) consume the post-inlining tree so a single recursive renderer
 * pass produces the final HTML — there is no per-renderer pattern
 * lookup.
 *
 * Only synced patterns reach this resolver. Unsynced patterns
 * (`synced: false`) are inlined into the target block tree at insert
 * time by the editor (see `inserter-patterns-panel.tsx::patternBlocks`
 * and the `parse()` call there) and travel as plain block trees from
 * that point on — they never appear in saved content as `core/block`
 * references. See `docs/plans/11-v1-expansion.md` §2.2 and §8.
 *
 * Recursion guard: an in-flight stack of pattern-id keys catches cycles
 * (pattern A → pattern B → pattern A) and a depth counter caps how deep
 * the resolution chain can go (default 10). Either guard converts the
 * offending block into a marker whose `attributes._resolutionError`
 * records the reason — the renderers translate that into a graceful
 * empty render in production and a visible warning in dev.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Resources;

class PatternInliner
{
	/**
	 * Default cap on how many levels deep the synced-pattern chain can
	 * resolve before the renderer renders the fallback marker. Real
	 * sites rarely nest synced patterns more than two or three deep;
	 * ten leaves generous headroom while still terminating before PHP
	 * exhausts its stack.
	 */
	public const DEFAULT_MAX_DEPTH = 10;

	public const ERROR_MISSING_REF = 'missing-ref';
	public const ERROR_NOT_FOUND   = 'not-found';
	public const ERROR_CYCLE       = 'cycle';
	public const ERROR_DEPTH_LIMIT = 'depth-limit';

	public function __construct(
		protected int $maxDepth = self::DEFAULT_MAX_DEPTH
	) {
	}

	/**
	 * Walks `$tree` and returns a copy with every `core/block` reference
	 * carrying its resolved pattern's blocks under `innerBlocks`.
	 *
	 * The returned shape matches the input — the same `clientId`, `name`,
	 * and `attributes` keys are preserved so existing renderers can render
	 * the inlined tree without special-casing patterns. Blocks the
	 * inliner cannot resolve (missing ref, missing pattern, cycle, depth
	 * overflow) keep the `core/block` name and gain a synthetic
	 * `_resolutionError` attribute the renderers detect.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree  Parsed block tree.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function inline( array $tree ): array
	{
		return $this->walk( $tree, [], 0 );
	}

	/**
	 * Recursive walker shared by the public entry point and inner-block
	 * descent. Keeps the cycle stack and depth counter on the stack frame
	 * (rather than instance state) so concurrent calls into the same
	 * inliner instance don't leak resolution context across trees.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 * @param  array<int, string>                $stack
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function walk( array $tree, array $stack, int $depth ): array
	{
		$out = [];

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';

			if ( 'core/block' === $name ) {
				$out[] = $this->resolvePattern( $block, $stack, $depth );

				continue;
			}

			$innerBlocks          = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : [];
			$block['innerBlocks'] = $this->walk( $innerBlocks, $stack, $depth );
			$out[]                = $block;
		}

		return $out;
	}

	/**
	 * Looks the pattern up by ref and returns the block with the resolved
	 * pattern's blocks attached as `innerBlocks`.
	 *
	 * Resolution failures (missing ref, missing record, cycle, depth
	 * overflow) return the original block stamped with a
	 * `_resolutionError` attribute so the renderer can react without
	 * hard-failing the whole template.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $block
	 * @param  array<int, string>    $stack
	 *
	 * @return array<string, mixed>
	 */
	protected function resolvePattern( array $block, array $stack, int $depth ): array
	{
		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];
		$ref        = $this->normalizeRef( $attributes['ref'] ?? null );

		if ( null === $ref ) {
			return $this->markUnresolved( $block, self::ERROR_MISSING_REF );
		}

		if ( $depth >= $this->maxDepth ) {
			return $this->markUnresolved( $block, self::ERROR_DEPTH_LIMIT );
		}

		$key = (string) $ref;

		if ( in_array( $key, $stack, true ) ) {
			return $this->markUnresolved( $block, self::ERROR_CYCLE );
		}

		$blocks = $this->findPatternBlocks( $ref );

		if ( null === $blocks ) {
			return $this->markUnresolved( $block, self::ERROR_NOT_FOUND );
		}

		$childStack       = $stack;
		$childStack[]     = $key;
		$resolvedChildren = $this->walk( $blocks, $childStack, $depth + 1 );

		return [
			'clientId'    => $block['clientId'] ?? null,
			'name'        => 'core/block',
			'attributes'  => array_merge( $attributes, [
				'ref' => $ref,
			] ),
			'innerBlocks' => $resolvedChildren,
		];
	}

	/**
	 * Coerces the `ref` attribute to a positive integer pattern id.
	 *
	 * The editor writes refs as integers (see `inserter-patterns-panel.tsx`),
	 * but JSON round-trips and host-app authoring may surface them as
	 * numeric strings — normalise both shapes and reject anything else.
	 *
	 * @since 1.0.0
	 */
	protected function normalizeRef( mixed $value ): ?int
	{
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : null;
		}

		if ( is_string( $value ) && '' !== trim( $value ) && ctype_digit( trim( $value ) ) ) {
			$int = (int) trim( $value );

			return $int > 0 ? $int : null;
		}

		return null;
	}

	/**
	 * cms-framework's BlockPattern model. Direct `find()` lookup by id
	 * matches the visual-editor's `core/block` ref-as-id contract.
	 */
	protected const PATTERN_MODEL = '\\ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Models\\BlockPattern';

	/**
	 * Looks the pattern up via cms-framework's BlockPattern model and
	 * returns its block tree. Returns null when cms-framework isn't
	 * installed, no row matches the ref, or the content envelope is
	 * malformed — the inliner's `_resolutionError: 'not-found'` marker
	 * is the renderer's signal in any of those cases.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>|null
	 */
	protected function findPatternBlocks( int $ref ): ?array
	{
		if ( ! class_exists( self::PATTERN_MODEL ) ) {
			return null;
		}

		$model   = self::PATTERN_MODEL;
		$pattern = $model::query()->find( $ref );

		if ( null === $pattern ) {
			return null;
		}

		// cms-framework's `BlockPattern` stores `{ raw, blocks }` per the
		// plan-14 envelope. Read `blocks` defensively — a malformed row
		// surfaces as a resolution failure rather than a 500.
		$content = $pattern->content ?? null;

		if ( ! is_array( $content ) ) {
			return null;
		}

		$blocks = $content['blocks'] ?? null;

		return is_array( $blocks ) ? $blocks : null;
	}

	/**
	 * Returns the block stamped with the resolution-failure reason so
	 * downstream renderers can short-circuit to their fallback markup.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	protected function markUnresolved( array $block, string $reason ): array
	{
		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];

		return [
			'clientId'    => $block['clientId'] ?? null,
			'name'        => 'core/block',
			'attributes'  => array_merge( $attributes, [
				'_resolutionError' => $reason,
			] ),
			'innerBlocks' => [],
		];
	}
}
