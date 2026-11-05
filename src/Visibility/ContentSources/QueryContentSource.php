<?php

/**
 * Content source for Query Loop blocks.
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

class QueryContentSource extends AbstractContentSource
{
	/**
	 * @since 1.20.0
	 *
	 * @return string The source key.
	 */
	public function key(): string
	{
		return 'query';
	}

	/**
	 * @since 1.20.0
	 *
	 * @return string The source label.
	 */
	public function label(): string
	{
		return __( 'Query Loop' );
	}

	/**
	 * @since 1.20.0
	 *
	 * @return array<int, string> The block names.
	 */
	public function blockNames(): array
	{
		return [ 'artisanpack/query', 'core/query' ];
	}

	/**
	 * @since 1.20.0
	 *
	 * @return array<string, string> Condition key => translated label.
	 */
	public function conditions(): array
	{
		return [ 'hasResults' => __( 'Has results' ) ];
	}

	/**
	 * Whether the loop rendered any rows.
	 *
	 * `_resolvedItems` is checked too because the total counts rows the
	 * page doesn't show: a page past the end, or an offset past the last
	 * related post, has a total above zero and nothing on screen. The
	 * Query Loop's own `query-no-results` gate uses the rows as well.
	 *
	 * @since 1.20.0
	 *
	 * @param  string                $condition  A key from {@see conditions()}.
	 * @param  array<string, mixed>  $block      A resolved block.
	 *
	 * @return bool True when the loop has rows to show.
	 */
	public function passes( string $condition, array $block ): bool
	{
		$attributes = $this->attributes( $block );

		return (int) $attributes['_resolvedTotal'] > 0
			&& (int) ( $attributes['_resolvedItems'] ?? 1 ) > 0;
	}

	/**
	 * @since 1.20.0
	 *
	 * @return string The attribute name.
	 */
	protected function resolvedKey(): string
	{
		return '_resolvedTotal';
	}
}
