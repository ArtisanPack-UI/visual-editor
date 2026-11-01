<?php

/**
 * Shared base for {@see ContentSource} implementations that match on
 * block name and read a single `_resolved*` key.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Visibility\ContentSources;

use ArtisanPackUI\VisualEditor\Visibility\ContentSource;

abstract class AbstractContentSource implements ContentSource
{
	/**
	 * {@inheritDoc}
	 *
	 * @since 1.20.0
	 *
	 * @param  array<string, mixed>  $block  A single block node.
	 *
	 * @return bool True when the block's name is in {@see blockNames()}.
	 */
	public function matches( array $block ): bool
	{
		$name = $block['name'] ?? null;

		return is_string( $name ) && in_array( $name, $this->blockNames(), true );
	}

	/**
	 * {@inheritDoc}
	 *
	 * A block whose loop failed to resolve carries `_resolutionError`
	 * (see `QueryInliner::markFailed()`) and has no content.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<string, mixed>  $block  A block {@see matches()} accepted.
	 *
	 * @return bool True when the block was resolved.
	 */
	public function isResolved( array $block ): bool
	{
		$attributes = $this->attributes( $block );

		return ! array_key_exists( '_resolutionError', $attributes )
			&& array_key_exists( $this->resolvedKey(), $attributes );
	}

	/**
	 * The attribute that is present once this source has been resolved.
	 *
	 * @since 1.20.0
	 *
	 * @return string The attribute name.
	 */
	abstract protected function resolvedKey(): string;

	/**
	 * The block's attribute bag.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<string, mixed>  $block  A single block node.
	 *
	 * @return array<string, mixed> The attributes, or `[]` when malformed.
	 */
	protected function attributes( array $block ): array
	{
		return isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];
	}
}
