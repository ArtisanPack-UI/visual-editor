<?php

/**
 * Gives every tab section on a page a unique id (#1057).
 *
 * Tabs print each section's id into `id`, `href`, `aria-controls` and
 * `aria-labelledby`, so two Tabs blocks with the same `tabId` values, or
 * with empty ones that fall back to `tab-N`, repeat element ids. This pass
 * walks a block tree in document order and stamps each section with an id
 * nothing else in the request has used; a repeat takes a `-2`, `-3`, …
 * suffix.
 *
 * Ids are stamped on the tree rather than claimed while rendering because
 * Tabs inside a Tabs block or an Accordion render twice: once as inner
 * blocks and again from the parent's partial. Claiming during the first
 * render would leave the printed copy with a suffix it doesn't need.
 *
 * Scoped, so ids stay unique across every tree rendered in a request and a
 * long-lived worker (Octane / queue) doesn't carry them to the next one.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

use ArtisanPackUI\VisualEditor\Blocks\DynamicContent\DynamicLoopBlock;
use ArtisanPackUI\VisualEditor\Support\BlockShape;

class TabIdStamper
{
	/**
	 * Attribute holding a tab section's stamped id.
	 *
	 * @since 1.20.0
	 */
	public const ATTRIBUTE = '_resolvedTabId';

	/**
	 * Ids already claimed, keyed by id.
	 *
	 * @var array<string, true>
	 */
	protected array $used = [];

	/**
	 * Stamp every tab section in a normalized tree.
	 *
	 * The walk stops at dynamic loops: a loop repeats its template, so its
	 * sections are stamped when {@see DynamicLoopBlock}
	 * renders each row.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<int|string, mixed>  $tree       Normalized block tree.
	 * @param  bool                      $overwrite  Replace ids already on the tree. True for a
	 *                                               new document, so stored content can't set one.
	 *
	 * @return array<int|string, mixed>
	 */
	public function stamp( array $tree, bool $overwrite = false ): array
	{
		foreach ( $tree as $key => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( DynamicLoopBlock::BLOCK_NAME === ( $block['name'] ?? null ) ) {
				if ( $overwrite ) {
					$tree[ $key ] = $this->clear( [ $block ] )[0];
				}

				continue;
			}

			$innerBlocks = $block['innerBlocks'] ?? null;

			if ( ! is_array( $innerBlocks ) || [] === $innerBlocks ) {
				continue;
			}

			$tree[ $key ]['innerBlocks'] = 'artisanpack/tabs' === ( $block['name'] ?? null )
				? $this->stampSections( $innerBlocks, $overwrite )
				: $this->stamp( $innerBlocks, $overwrite );
		}

		return $tree;
	}

	/**
	 * Stamp the tab sections among a Tabs block's inner blocks, then
	 * anything nested inside them.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<int|string, mixed>  $innerBlocks  The Tabs block's inner blocks.
	 * @param  bool                      $overwrite    Replace ids already on the sections.
	 *
	 * @return array<int|string, mixed>
	 */
	public function stampSections( array $innerBlocks, bool $overwrite = false ): array
	{
		$sectionIndex = 0;
		$inBlock      = [];

		foreach ( $innerBlocks as $key => $block ) {
			if ( ! is_array( $block ) || 'artisanpack/tab-section' !== ( $block['name'] ?? null ) ) {
				continue;
			}

			$sectionIndex++;

			$attributes = is_array( $block['attributes'] ?? null ) ? $block['attributes'] : [];
			$stored     = $attributes[ self::ATTRIBUTE ] ?? null;
			$keep       = ! $overwrite && is_string( $stored ) && '' !== $stored;

			// An id this block hasn't printed yet stays as it is, even if
			// it's already claimed: a nested render re-prints the ids the
			// outer pass stamped.
			if ( $keep && ! isset( $inBlock[ $stored ] ) ) {
				$stamped                 = $stored;
				$this->used[ $stamped ]  = true;
			} else {
				$stamped = $this->claim(
					$keep ? $stored : $this->sanitize( (string) ( $attributes['tabId'] ?? '' ), $sectionIndex ),
				);
			}

			$inBlock[ $stamped ] = true;

			$attributes[ self::ATTRIBUTE ]     = $stamped;
			$innerBlocks[ $key ]['attributes'] = $attributes;

			if ( is_array( $block['innerBlocks'] ?? null ) ) {
				$innerBlocks[ $key ]['innerBlocks'] = $this->stamp( $block['innerBlocks'], $overwrite );
			}
		}

		return $innerBlocks;
	}

	/**
	 * Remove stored ids from a dynamic loop and its template, which
	 * {@see self::stamp()} doesn't walk. A row keeps any id it finds, so a
	 * stored one would repeat in every row.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<int|string, mixed>  $blocks  Blocks to clear.
	 *
	 * @return array<int|string, mixed>
	 */
	protected function clear( array $blocks ): array
	{
		foreach ( $blocks as $key => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			[ $attrKey, $attributes ] = BlockShape::readAttrs( $block );

			unset( $attributes[ self::ATTRIBUTE ] );

			if ( is_array( $attributes['_template'] ?? null ) ) {
				$attributes['_template'] = $this->clear( $attributes['_template'] );
			}

			$blocks[ $key ][ $attrKey ] = $attributes;

			if ( is_array( $block['innerBlocks'] ?? null ) ) {
				$blocks[ $key ]['innerBlocks'] = $this->clear( $block['innerBlocks'] );
			}
		}

		return $blocks;
	}

	/**
	 * Normalize a tab id so it's safe in an HTML id or fragment, falling
	 * back to the section's position when nothing survives. Mirrors the
	 * React and Vue `sanitizeTabId` helpers.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $raw           The stored `tabId`.
	 * @param  int     $sectionIndex  The section's 1-based position in its block.
	 */
	protected function sanitize( string $raw, int $sectionIndex ): string
	{
		$slug = preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $raw ) ) ?? '';
		$slug = trim( $slug, '-' );

		return '' === $slug ? 'tab-' . $sectionIndex : $slug;
	}

	/**
	 * Claim an id, adding a numeric suffix when it's taken.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $base  The id to claim.
	 */
	protected function claim( string $base ): string
	{
		$candidate = $base;
		$suffix    = 2;

		while ( isset( $this->used[ $candidate ] ) ) {
			$candidate = $base . '-' . $suffix;
			$suffix++;
		}

		$this->used[ $candidate ] = true;

		return $candidate;
	}
}
