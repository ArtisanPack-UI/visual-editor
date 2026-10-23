<?php

/**
 * BlockPreview form request.
 *
 * Validates the `{ name, attributes }` payload sent to the generic dynamic
 * block preview endpoint, or the envelope of an `{ items }` batch (#1010).
 * Batch items are checked one by one in the controller so a bad item
 * doesn't fail the batch. Per-block validation runs later via
 * {@see \ArtisanPackUI\VisualEditor\Blocks\DynamicBlock::validateAttrs()}.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BlockPreviewRequest extends FormRequest
{
	/**
	 * Most blocks one batch request may render.
	 *
	 * @since 1.17.0
	 */
	public const MAX_ITEMS = 25;

	public function authorize(): bool
	{
		return true;
	}

	/**
	 * @since 1.0.0
	 * @since 1.17.0 Accepts an `items` batch in place of `name`.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function rules(): array
	{
		return [
			'items'            => [ 'sometimes', 'array', 'list', 'min:1', 'max:' . self::MAX_ITEMS ],
			'name'             => [ 'required_without:items', 'string', 'regex:/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/' ],
			'attributes'       => [ 'sometimes', 'array' ],
			'innerBlocks'      => [ 'sometimes', 'array' ],
			'bindings'         => [ 'sometimes', 'array' ],
			'context'          => [ 'sometimes', 'array' ],
			'context.resource' => [ 'sometimes', 'string' ],
			'context.id'       => [ 'sometimes' ],
			'context.draft'    => [ 'sometimes', 'array' ],
		];
	}
}
