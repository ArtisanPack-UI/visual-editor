<?php

/**
 * SuggestLayout form request.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class SuggestLayoutRequest extends FormRequest
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
			'section_content'      => [ 'required', 'array' ],
			'available_patterns'   => [ 'required', 'array', 'min:1' ],
			'available_patterns.*' => [ 'string', 'max:128' ],
		];
	}
}
