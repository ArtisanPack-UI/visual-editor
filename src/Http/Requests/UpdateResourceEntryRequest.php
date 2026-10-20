<?php

/**
 * UpdateResourceEntry form request.
 *
 * Validates the title/excerpt payload sent to the generic resource entry
 * PUT endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.3
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResourceEntryRequest extends FormRequest
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
			'title'   => [ 'sometimes', 'nullable', 'string', 'max:255' ],
			'excerpt' => [ 'sometimes', 'nullable', 'string' ],
		];
	}
}
