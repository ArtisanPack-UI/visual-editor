<?php

/**
 * UpdateMenuItemRequest — H6 site-editor.
 *
 * Validates the `PUT /visual-editor/api/menu-items/{id}` payload. All
 * fields optional except validation rules where present.
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
use Illuminate\Validation\Rule;

class UpdateMenuItemRequest extends FormRequest
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
			'menu_id'     => [ 'sometimes', 'integer', 'min:1' ],
			'parent_id'   => [ 'nullable', 'integer', 'min:1' ],
			'position'    => [ 'sometimes', 'integer', 'min:0' ],
			'type'        => [ 'sometimes', 'string', Rule::in( StoreMenuItemRequest::TYPES ) ],
			'label'       => [ 'sometimes', 'string', 'max:255' ],
			// Same scheme allowlist as StoreMenuItemRequest — see its
			// `URL_REGEX` constant for the accepted patterns.
			'url'         => [ 'nullable', 'string', 'max:2048', 'regex:' . StoreMenuItemRequest::URL_REGEX ],
			'target'      => [ 'sometimes', 'string', Rule::in( [ '', '_self', '_blank' ] ) ],
			'rel'         => [ 'nullable', 'string', 'max:191' ],
			'classes'     => [ 'nullable', 'string', 'max:191' ],
			'description' => [ 'nullable', 'string' ],
			'object_type' => [ 'nullable', 'string', 'max:191' ],
			'object_id'   => [ 'nullable', 'integer', 'min:1' ],
			'kind'        => [ 'nullable', 'string', 'max:191' ],
		];
	}
}
