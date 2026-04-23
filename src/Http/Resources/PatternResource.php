<?php

/**
 * PatternResource API resource.
 *
 * Shapes a {@see VisualEditorPattern} into the B1 core-data shim's
 * `wp_block` record envelope. Keeps the HTTP response contract in one
 * place so controllers and tests don't have to reconstruct the
 * `title.rendered`, `content.{raw,blocks}`, and `categories` wrappers
 * ad hoc.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Resources;

use ArtisanPackUI\VisualEditor\Models\VisualEditorPattern;
use ArtisanPackUI\VisualEditor\Models\VisualEditorPatternCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property VisualEditorPattern $resource
 */
class PatternResource extends JsonResource
{
	/**
	 * Transforms the pattern into the shim-compatible record shape.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function toArray( Request $request ): array
	{
		/** @var VisualEditorPattern $pattern */
		$pattern = $this->resource;

		$envelope = $pattern->getContentEnvelope();

		$categories = $pattern->relationLoaded( 'categories' )
			? $pattern->categories
			: $pattern->categories()->get();

		return [
			'id'         => $pattern->getKey(),
			'slug'       => $pattern->slug,
			'title'      => [
				'rendered' => (string) ( $pattern->title ?? '' ),
			],
			'content'    => [
				'raw'    => $envelope['raw'],
				'blocks' => $envelope['blocks'],
			],
			'synced'     => (bool) $pattern->synced,
			'categories' => $categories
				->map( fn ( VisualEditorPatternCategory $category ) => $category->slug )
				->values()
				->all(),
			'status'     => $pattern->status,
			'type'       => 'wp_block',
		];
	}
}
