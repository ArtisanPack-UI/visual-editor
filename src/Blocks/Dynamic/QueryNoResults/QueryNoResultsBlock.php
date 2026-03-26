<?php

/**
 * Query No Results Block.
 *
 * Container block that displays inner blocks when the query
 * loop returns no results, allowing custom "no results"
 * messaging and layout.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\QueryNoResults
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\QueryNoResults;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Query No Results block for the visual editor.
 *
 * Acts as a container block within a Query Loop that only
 * renders its inner blocks when the query returns zero results.
 * Allows users to design custom "no results found" messaging
 * with any blocks they choose.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\QueryNoResults
 *
 * @since      2.0.0
 */
class QueryNoResultsBlock extends BaseBlock
{
	/**
	 * Get the content field schema for the inspector panel.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		return [];
	}
}
