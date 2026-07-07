<?php

/**
 * HeadingHierarchy form request.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class HeadingHierarchyRequest extends FormRequest
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
			'blocks' => [ 'required', 'array' ],
		];
	}
}
