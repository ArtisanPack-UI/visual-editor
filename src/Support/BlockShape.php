<?php

/**
 * Shared helper for reading and normalizing the block tree shape.
 *
 * Two shapes coexist:
 *  - Gutenberg's editor serializes to `{name, attributes, innerBlocks}`
 *    with `bindings` nested inside `attributes`.
 *  - WordPress's `parse_blocks()` produces `{name, attrs, innerBlocks}`
 *    with `bindings` as a top-level sidecar.
 *
 * Pipeline stages that walk the tree (renderer, binding resolver,
 * cycle guard, cache-key hasher) all need to read attributes and
 * bindings in a shape-agnostic way. Duplicating the sniff per
 * consumer led to real bugs (#650 review finding #4 — cycle guard
 * silently ignored the editor shape). This helper centralizes the
 * detection and, via {@see normalizeTree()}, offers a canonical form
 * that lets downstream code read only one key.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

class BlockShape
{
	/**
	 * Return which key the block uses for its attributes bag, and the
	 * bag itself, in a tuple.
	 *
	 * @param  array<string, mixed>  $block
	 *
	 * @return array{0: 'attributes'|'attrs', 1: array<string, mixed>}
	 *
	 * @since 1.4.0
	 */
	public static function readAttrs( array $block ): array
	{
		if ( is_array( $block['attributes'] ?? null ) ) {
			return [ 'attributes', $block['attributes'] ];
		}

		if ( is_array( $block['attrs'] ?? null ) ) {
			return [ 'attrs', $block['attrs'] ];
		}

		return [ 'attributes', [] ];
	}

	/**
	 * Read the block's bindings sidecar, handling both top-level
	 * (parse_blocks) and nested-in-attributes (editor) placements.
	 *
	 * Only treats `attrs.bindings` as a sidecar when at least one entry
	 * carries a `source` key — otherwise a block that happens to have
	 * an unrelated attribute literally named `bindings` would be
	 * misinterpreted. Real bindings are `{ attr: { source, args, … } }`.
	 *
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>|null
	 *
	 * @since 1.4.0
	 */
	public static function readBindings( array $block ): ?array
	{
		if ( is_array( $block['bindings'] ?? null ) ) {
			return $block['bindings'];
		}

		[ , $attrs ] = self::readAttrs( $block );
		$nested      = $attrs['bindings'] ?? null;

		if ( ! is_array( $nested ) || [] === $nested ) {
			return null;
		}

		// Ensure at least one entry looks like a binding
		// (`{ source: string, ... }`). Otherwise the block just has an
		// unrelated attribute named `bindings`.
		foreach ( $nested as $entry ) {
			if ( is_array( $entry ) && is_string( $entry['source'] ?? null ) && '' !== $entry['source'] ) {
				return $nested;
			}
		}

		return null;
	}

	/**
	 * Canonicalize a whole block tree so every block uses
	 * `attributes` as the attribute-bag key. Recursively normalizes
	 * `innerBlocks`. Trees already in canonical form round-trip
	 * unchanged.
	 *
	 * Downstream pipeline stages should read `attributes` after this
	 * pass and stop probing for `attrs`.
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @since 1.4.0
	 */
	public static function normalizeTree( array $tree ): array
	{
		return array_values( array_map(
			static fn ( $block ) => is_array( $block ) ? self::normalizeBlock( $block ) : $block,
			$tree
		) );
	}

	/**
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.4.0
	 */
	protected static function normalizeBlock( array $block ): array
	{
		if ( isset( $block['attrs'] ) && ! isset( $block['attributes'] ) ) {
			$block['attributes'] = is_array( $block['attrs'] ) ? $block['attrs'] : [];
			unset( $block['attrs'] );
		}

		if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) && [] !== $block['innerBlocks'] ) {
			$block['innerBlocks'] = self::normalizeTree( $block['innerBlocks'] );
		}

		return $block;
	}
}
