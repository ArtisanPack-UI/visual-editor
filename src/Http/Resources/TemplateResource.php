<?php

/**
 * TemplateResource API resource.
 *
 * Shapes a {@see VisualEditorTemplate} into the B1 core-data shim's
 * `wp_template` record envelope. Keeps the HTTP response contract in
 * one place so controllers and tests don't have to reconstruct the
 * `title.rendered` and `content.{raw,blocks}` wrappers ad hoc.
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

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property VisualEditorTemplate $resource
 */
class TemplateResource extends JsonResource
{
	/**
	 * Transforms the template into the shim-compatible record shape.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function toArray( Request $request ): array
	{
		/** @var VisualEditorTemplate $template */
		$template = $this->resource;

		$envelope = $template->getContentEnvelope();

		return [
			'id'          => $template->getKey(),
			'slug'        => $template->slug,
			'title'       => [
				'rendered' => (string) ( $template->title ?? '' ),
			],
			'description' => (string) ( $template->description ?? '' ),
			'content'     => [
				'raw'    => $envelope['raw'],
				'blocks' => $envelope['blocks'],
			],
			'status'      => $template->status,
			'theme'       => $template->theme,
			'type'        => 'wp_template',
			'source'      => $template->source,
			'origin'      => $template->origin,
		];
	}
}
