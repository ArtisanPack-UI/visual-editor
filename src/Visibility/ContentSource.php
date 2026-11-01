<?php

/**
 * Contract for a kind of dynamic content the Content visibility rule
 * can inspect.
 *
 * Built-in sources live in `Visibility/ContentSources`. Hosts add their
 * own through the `ap.visualEditor.visibility.registerContentSources`
 * filter, usually by extending
 * {@see ContentSources\AbstractContentSource}.
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

interface ContentSource
{
	/**
	 * Unique identifier stored in the rule's `source` field.
	 *
	 * @since 1.20.0
	 *
	 * @return string The source key.
	 */
	public function key(): string;

	/**
	 * Translated name shown in the Inspector's Source select.
	 *
	 * @since 1.20.0
	 *
	 * @return string The source label.
	 */
	public function label(): string;

	/**
	 * Block names this source inspects. The Inspector uses them to tell
	 * the author what the rule found. A source that matches on anything
	 * other than the name returns `[]`, and the Inspector shows no hint.
	 *
	 * @since 1.20.0
	 *
	 * @return array<int, string> The block names.
	 */
	public function blockNames(): array;

	/**
	 * Whether the block is one this source inspects.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<string, mixed>  $block  A single block node.
	 *
	 * @return bool True when the block belongs to this source.
	 */
	public function matches( array $block ): bool;

	/**
	 * The conditions this source supports.
	 *
	 * @since 1.20.0
	 *
	 * @return array<string, string> Condition key => translated label.
	 */
	public function conditions(): array;

	/**
	 * Whether the block carries the data this source reads. An
	 * unresolved block counts as having no content, so {@see passes()}
	 * only ever sees resolved blocks.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<string, mixed>  $block  A block {@see matches()} accepted.
	 *
	 * @return bool True when the block was resolved.
	 */
	public function isResolved( array $block ): bool;

	/**
	 * Whether a resolved block satisfies the condition.
	 *
	 * @since 1.20.0
	 *
	 * @param  string                $condition  A key from {@see conditions()}.
	 * @param  array<string, mixed>  $block      A resolved block.
	 *
	 * @return bool True when the block has content.
	 */
	public function passes( string $condition, array $block ): bool;
}
