<?php

/**
 * Content source for a loop's page count.
 *
 * Matches the pagination wrapper, so a rule on a pagination row works
 * inside a loop, and the loop itself, so a rule on a container works
 * when the loop has no pagination block.
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

class PaginationContentSource extends AbstractContentSource
{
	/**
	 * @since 1.20.0
	 *
	 * @return string The source key.
	 */
	public function key(): string
	{
		return 'pagination';
	}

	/**
	 * A bare `Pagination` would resolve to Laravel's `pagination.php`
	 * language file and return an array.
	 *
	 * @since 1.20.0
	 *
	 * @return string The source label.
	 */
	public function label(): string
	{
		return __( 'Query Pagination' );
	}

	/**
	 * @since 1.20.0
	 *
	 * @return array<int, string> The block names.
	 */
	public function blockNames(): array
	{
		return [
			'artisanpack/query-pagination',
			'core/query-pagination',
			'artisanpack/query',
			'core/query',
		];
	}

	/**
	 * @since 1.20.0
	 *
	 * @return array<string, string> Condition key => translated label.
	 */
	public function conditions(): array
	{
		return [ 'hasMultiplePages' => __( 'Has more than one page' ) ];
	}

	/**
	 * Whether the loop spans more than one page.
	 *
	 * @since 1.20.0
	 *
	 * @param  string                $condition  A key from {@see conditions()}.
	 * @param  array<string, mixed>  $block      A resolved block.
	 *
	 * @return bool True when there is a second page.
	 */
	public function passes( string $condition, array $block ): bool
	{
		return (int) $this->attributes( $block )['_resolvedTotalPages'] > 1;
	}

	/**
	 * @since 1.20.0
	 *
	 * @return string The attribute name.
	 */
	protected function resolvedKey(): string
	{
		return '_resolvedTotalPages';
	}
}
