<?php

/**
 * Walks a serialized block tree and yields every block that carries a
 * `dateTimeWindow` or `recurring` visibility rule.
 *
 * Used by:
 *   - {@see \ArtisanPackUI\VisualEditor\Console\AuditScheduledBlocksCommand}
 *     to enumerate every scheduled block in the host's registered
 *     `HasBlockContent` resources.
 *   - The cache-tightening layer that shortens the TTL of any template
 *     part containing a scheduled block, and surfaces the editor
 *     warning when a scheduled block sits inside a heavily cached
 *     surface (#493).
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

class ScheduledBlockCollector
{
	/**
	 * Collect every scheduled block in a tree.
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 *
	 * @return array<int, array{name: string, rule: string, attributes: array<string, mixed>}>
	 *
	 * @since 1.4.0
	 */
	public function collect( array $tree ): array
	{
		$out = [];

		$this->walk( $tree, $out );

		return $out;
	}

	/**
	 * @param  array<int, array<string, mixed>>  $tree
	 * @param  array<int, array{name: string, rule: string, attributes: array<string, mixed>}>  $out
	 */
	protected function walk( array $tree, array &$out ): void
	{
		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name  = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';
			$attrs = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];
			$slice = $attrs['artisanpackVisibility'] ?? null;

			if ( is_array( $slice ) ) {
				if ( ! empty( $slice['dateTimeWindow'] ) && is_array( $slice['dateTimeWindow'] ) ) {
					$out[] = [ 'name' => $name, 'rule' => 'dateTimeWindow', 'attributes' => $slice['dateTimeWindow'] ];
				}
				if ( ! empty( $slice['recurring'] ) && is_array( $slice['recurring'] ) ) {
					$out[] = [ 'name' => $name, 'rule' => 'recurring', 'attributes' => $slice['recurring'] ];
				}
			}

			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$this->walk( $block['innerBlocks'], $out );
			}
		}
	}
}
