<?php

/**
 * BlockPreview form request.
 *
 * Validates the `{ name, attributes }` payload sent to the generic dynamic
 * block preview endpoint. Per-block validation runs later via
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
	public function authorize(): bool
	{
		return true;
	}

	/**
	 * @return array<string, array<int, mixed>>
	 */
	public function rules(): array
	{
		return [
			'name'             => [ 'required', 'string', 'regex:/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/' ],
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
