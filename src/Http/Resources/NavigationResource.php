<?php

/**
 * NavigationResource API resource.
 *
 * Shapes a {@see VisualEditorNavigation} into the B1 core-data shim's
 * `wp_navigation` record envelope. Keeps the HTTP response contract in
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

use ArtisanPackUI\VisualEditor\Models\VisualEditorNavigation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property VisualEditorNavigation $resource
 */
class NavigationResource extends JsonResource
{
	/**
	 * Transforms the navigation into the shim-compatible record shape.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function toArray( Request $request ): array
	{
		/** @var VisualEditorNavigation $navigation */
		$navigation = $this->resource;

		$envelope = $navigation->getContentEnvelope();

		return [
			'id'         => $navigation->getKey(),
			'slug'       => $navigation->slug,
			'title'      => [
				'rendered' => (string) ( $navigation->title ?? '' ),
			],
			'content'    => [
				'raw'    => $envelope['raw'],
				'blocks' => $envelope['blocks'],
			],
			'status'     => $navigation->status,
			'menu_order' => (int) $navigation->menu_order,
			// `null` is the wire signal "no location assigned" — the
			// site editor's locations panel keys off this directly.
			'location'   => null === $navigation->location || '' === $navigation->location ? null : (string) $navigation->location,
			'type'       => 'wp_navigation',
		];
	}
}
