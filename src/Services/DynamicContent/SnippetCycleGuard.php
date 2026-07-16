<?php

/**
 * Snippet cycle guard.
 *
 * Prevents `artisanpack/snippet` blocks from referencing themselves —
 * directly or transitively — before the reference reaches the renderer.
 * Used on save (throws {@see SnippetCycleException} for validation
 * error reporting) and on render (returns bool so the renderer can
 * bail cleanly with a warning HTML placeholder).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\DynamicContent;

use ArtisanPackUI\VisualEditor\Models\Snippet;

class SnippetCycleGuard
{
	/**
	 * Maximum snippet nesting depth. A tree deeper than this triggers a
	 * cycle diagnosis even if the tree is technically acyclic — it is
	 * either a mistake or a pathological payload, and the renderer
	 * treats it the same as a cycle.
	 *
	 * @since 1.4.0
	 */
	public const MAX_DEPTH = 32;

	/**
	 * Assert that the given block tree, when placed under `$ownerSlug`,
	 * does not cycle. Throws when it does. Used from the CRUD controller
	 * so a save with a cycle fails with a 422 before hitting the DB.
	 *
	 * @param  array<int, array<string, mixed>>  $blocks
	 *
	 * @throws SnippetCycleException
	 *
	 * @since 1.4.0
	 */
	public function assertNoCycle( string $ownerSlug, array $blocks ): void
	{
		$visited = [];

		if ( '' !== $ownerSlug ) {
			$visited[ $ownerSlug ] = true;
		}

		$this->walk( $blocks, $visited, 0 );
	}

	/**
	 * Non-throwing form used at render time. Returns the resolved snippet
	 * or null when the reference would introduce a cycle. Each call
	 * receives the enclosing visited-set so nested placements share one
	 * detection scope.
	 *
	 * @param  array<string, bool>  $visited  Slug set of enclosing snippets.
	 *
	 * @since 1.4.0
	 */
	public function checkPlacement( string $targetSlug, array $visited, int $depth ): ?Snippet
	{
		if ( '' === $targetSlug ) {
			return null;
		}

		if ( isset( $visited[ $targetSlug ] ) ) {
			return null;
		}

		if ( $depth >= self::MAX_DEPTH ) {
			return null;
		}

		return Snippet::query()->where( 'slug', $targetSlug )->first();
	}

	/**
	 * Recursively walk the block tree looking for snippet references and
	 * asserting cycle-freeness.
	 *
	 * @param  array<int, array<string, mixed>>  $blocks
	 * @param  array<string, bool>               $visited
	 *
	 * @throws SnippetCycleException
	 *
	 * @since 1.4.0
	 */
	protected function walk( array $blocks, array $visited, int $depth ): void
	{
		if ( $depth >= self::MAX_DEPTH ) {
			throw new SnippetCycleException( sprintf(
				'Snippet nesting exceeds the maximum depth of %d.',
				self::MAX_DEPTH
			) );
		}

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name = is_string( $block['name'] ?? null ) ? $block['name'] : '';

			if ( 'artisanpack/snippet' === $name ) {
				$attrs      = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [];
				$targetSlug = is_string( $attrs['slug'] ?? null ) ? $attrs['slug'] : '';

				if ( '' !== $targetSlug && isset( $visited[ $targetSlug ] ) ) {
					throw new SnippetCycleException( sprintf(
						'Snippet "%s" cycles back on itself.',
						$targetSlug
					) );
				}

				if ( '' !== $targetSlug ) {
					$referenced = Snippet::query()->where( 'slug', $targetSlug )->first();

					if ( null !== $referenced && is_array( $referenced->blocks ) ) {
						$this->walk(
							$referenced->blocks,
							$visited + [ $targetSlug => true ],
							$depth + 1
						);
					}
				}
			}

			$inner = $block['innerBlocks'] ?? null;

			if ( is_array( $inner ) && [] !== $inner ) {
				$this->walk( $inner, $visited, $depth + 1 );
			}
		}
	}
}
