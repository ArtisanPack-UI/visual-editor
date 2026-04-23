<?php

/**
 * TemplatePartResource API resource.
 *
 * Shapes a {@see VisualEditorTemplatePart} into the B1 core-data shim's
 * `wp_template_part` record envelope. Keeps the HTTP response contract
 * in one place so controllers and tests don't have to reconstruct the
 * `title.rendered` and `content.{raw,blocks}` wrappers ad hoc.
 *
 * The optional `referenced_by` key is populated on `show` responses via
 * `additional([...])` — it's a derived list of template slugs whose
 * block tree embeds this part through a `core/template-part` block.
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

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplatePart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property VisualEditorTemplatePart $resource
 */
class TemplatePartResource extends JsonResource
{
	/**
	 * Transforms the template part into the shim-compatible record shape.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function toArray( Request $request ): array
	{
		/** @var VisualEditorTemplatePart $part */
		$part = $this->resource;

		$envelope = $part->getContentEnvelope();

		return [
			'id'      => $part->getKey(),
			'slug'    => $part->slug,
			'title'   => [
				'rendered' => (string) ( $part->title ?? '' ),
			],
			'content' => [
				'raw'    => $envelope['raw'],
				'blocks' => $envelope['blocks'],
			],
			'area'    => $part->area,
			'theme'   => $part->theme,
			'type'    => 'wp_template_part',
		];
	}
}
