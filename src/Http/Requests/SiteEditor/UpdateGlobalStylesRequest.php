<?php

/**
 * UpdateGlobalStylesRequest — H6 site-editor.
 *
 * Validates the WP REST `__unstableBase` payload for `PUT
 * /visual-editor/api/global-styles/{id}`. Both `settings` and `styles`
 * are theme.json-shape free-form maps; cms-framework's H3 module
 * validates the inner shape — visual-editor only checks that they
 * are arrays.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGlobalStylesRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	/**
	 * @since 1.0.0
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function rules(): array
	{
		return [
			'settings'  => [ 'sometimes', 'array' ],
			'styles'    => [ 'sometimes', 'array' ],
			'variation' => [ 'nullable', 'string', 'max:191' ],
			'title'     => [ 'sometimes', 'string', 'max:255' ],
			'theme'     => [ 'sometimes', 'string', 'max:191' ],
		];
	}
}
