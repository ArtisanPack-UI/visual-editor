<?php

/**
 * The trust boundary for `_resolved*` block attributes.
 *
 * Render-time resolvers stamp `_resolved*` attributes (a post's
 * permalink, the login URL, the breadcrumb trail, the business
 * address, the next-page URL, …) and the block views print them.
 * Most resolvers keep a value a block already carries, so a host can
 * hand in values it resolved itself. A saved block tree must not get
 * that precedence, or anyone who can edit block markup can save their
 * own `_resolvedLoginoutUrl` and point a link anywhere (#1096).
 *
 * {@see strip()} removes every `_resolved*` key from a saved tree. The
 * render entry points run it once, after template parts and patterns
 * are inlined and before any resolver stamps, so only values stamped
 * during the render reach the views. A host that resolved a tree itself
 * passes `trust-resolved` to `<x-ve-blocks>` (or `false` for
 * {@see \ArtisanPackUI\VisualEditor\Resources\QueryInliner::inline()}'s
 * `$stripSavedResolved`). A host that mixes saved content with its own
 * values strips the saved tree with {@see strip()}, adds its values,
 * and then passes the result as trusted.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

/**
 * Strips saved `_resolved*` attributes from block trees.
 *
 * @since 1.20.0
 */
class ResolvedAttributes
{
	/**
	 * Prefix every render-time attribute shares.
	 *
	 * @since 1.20.0
	 */
	public const PREFIX = '_resolved';

	/**
	 * Remove every `_resolved*` attribute from every block in a tree.
	 *
	 * Reads both the `attributes` and the legacy `attrs` shape, and walks
	 * `innerBlocks` at any depth. Entries that aren't block arrays are
	 * dropped, as every other tree walker does.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<int|string, mixed>  $tree  A saved block tree.
	 *
	 * @return array<int, array<string, mixed>> The tree without `_resolved*` attributes.
	 */
	public static function strip( array $tree ): array
	{
		$out = [];

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			foreach ( [ 'attributes', 'attrs' ] as $key ) {
				if ( isset( $block[ $key ] ) && is_array( $block[ $key ] ) ) {
					$block[ $key ] = self::stripAttributes( $block[ $key ] );
				}
			}

			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::strip( $block['innerBlocks'] );
			}

			$out[] = $block;
		}

		return $out;
	}

	/**
	 * Remove the `_resolved*` keys from one block's attributes.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<string, mixed>  $attributes  The block's attributes.
	 *
	 * @return array<string, mixed>
	 */
	public static function stripAttributes( array $attributes ): array
	{
		return array_filter(
			$attributes,
			static fn ( mixed $key ): bool => ! is_string( $key ) || ! str_starts_with( $key, self::PREFIX ),
			ARRAY_FILTER_USE_KEY,
		);
	}
}
