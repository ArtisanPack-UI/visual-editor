<?php

/**
 * GlobalStylesResource API resource.
 *
 * Shapes a {@see VisualEditorGlobalStyles} record into the B1 core-data
 * shim's `globalStyles` envelope — `{ id, version, settings, styles }`.
 * Keeps the HTTP response shape in one place so the controller's
 * `show` and `update` paths do not reconstruct it by hand.
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

use ArtisanPackUI\VisualEditor\Models\VisualEditorGlobalStyles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property VisualEditorGlobalStyles $resource
 */
class GlobalStylesResource extends JsonResource
{
	/**
	 * Transforms the record into the shim-compatible envelope.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function toArray( Request $request ): array
	{
		/** @var VisualEditorGlobalStyles $record */
		$record = $this->resource;

		$settings = $record->settings;
		$styles   = $record->styles;

		return [
			'id'       => $record->getKey(),
			'version'  => (int) $record->version,
			'settings' => is_array( $settings ) ? $settings : [],
			'styles'   => is_array( $styles ) ? $styles : [],
		];
	}
}
