<?php

/**
 * Block tree searchable-text extractor.
 *
 * Walks a saved block tree and returns a single plain-text string suitable
 * for indexing in Laravel Scout (or any other search engine). Static blocks
 * contribute text from a fixed set of known attribute keys so URLs, IDs,
 * and layout primitives never leak into the index. Dynamic blocks delegate
 * to their own {@see DynamicBlock::searchableText()} implementation, which
 * defaults to an empty string and must be overridden per block to opt in.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Search;

use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;

class BlockTreeSearchExtractor
{
	/**
	 * Attribute keys that hold human-readable text on static blocks.
	 *
	 * Deliberately narrow: restricting extraction to these keys keeps
	 * class names, image URLs, element IDs, and other non-display strings
	 * out of the search index without requiring each block to opt in.
	 *
	 * @var array<int, string>
	 */
	public const STATIC_TEXT_ATTRIBUTES = [ 'content', 'caption', 'alt', 'title' ];

	public function __construct( protected DynamicBlockRegistry $registry ) {}

	/**
	 * Walk the given block tree and return a single space-separated string
	 * of its extracted searchable text.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<string, mixed>>  $blocks  The saved block tree.
	 */
	public function extract( array $blocks ): string
	{
		$parts = [];

		$this->walk( $blocks, $parts );

		return trim( implode( ' ', $parts ) );
	}

	/**
	 * Recursively walk a block tree, appending each block's contribution to
	 * the shared parts list.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, mixed>  $blocks
	 * @param  array<int, string>  $parts
	 */
	protected function walk( array $blocks, array &$parts ): void
	{
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name  = is_string( $block['name'] ?? null ) ? $block['name'] : '';
			$attrs = is_array( $block['attributes'] ?? null ) ? $block['attributes'] : [];

			if ( '' !== $name && $this->registry->has( $name ) ) {
				$this->appendDynamic( $this->registry->get( $name ), $attrs, $parts );
			} else {
				$this->appendStatic( $attrs, $parts );
			}

			$inner = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : [];

			if ( [] !== $inner ) {
				$this->walk( $inner, $parts );
			}
		}
	}

	/**
	 * Append a dynamic block's contribution, catching exceptions so one
	 * misbehaving block can't stop the entire model from being indexed.
	 *
	 * @since 1.0.0
	 *
	 * @param  \ArtisanPackUI\VisualEditor\Blocks\DynamicBlock|null  $block
	 * @param  array<string, mixed>  $attrs
	 * @param  array<int, string>  $parts
	 */
	protected function appendDynamic( $block, array $attrs, array &$parts ): void
	{
		if ( null === $block ) {
			return;
		}

		try {
			$text = $block->searchableText( $attrs );
		} catch ( \Throwable $e ) {
			return;
		}

		$trimmed = trim( $text );

		if ( '' !== $trimmed ) {
			$parts[] = $trimmed;
		}
	}

	/**
	 * Append a static block's contribution by pulling known text attributes.
	 *
	 * Strips HTML tags so RichText-stored markup (e.g. a paragraph's
	 * `<strong>` emphasis) contributes its text without dumping raw tags
	 * into the search index.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attrs
	 * @param  array<int, string>  $parts
	 */
	protected function appendStatic( array $attrs, array &$parts ): void
	{
		foreach ( self::STATIC_TEXT_ATTRIBUTES as $key ) {
			$value = $attrs[ $key ] ?? null;

			if ( ! is_string( $value ) ) {
				continue;
			}

			$trimmed = trim( strip_tags( $value ) );

			if ( '' !== $trimmed ) {
				$parts[] = $trimmed;
			}
		}
	}
}
