<?php

/**
 * UpdatePostBlocks form request.
 *
 * Validates the block tree payload sent to the PUT posts endpoint.
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

use ArtisanPackUI\VisualEditor\Rules\BlockTreeRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePostBlocksRequest extends FormRequest
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
			'blocks' => ['required', 'array', new BlockTreeRule()],
		];
	}
}
