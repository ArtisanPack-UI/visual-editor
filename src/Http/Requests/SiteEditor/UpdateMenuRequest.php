<?php

/**
 * UpdateMenuRequest — H6 site-editor.
 *
 * Validates the `PUT /visual-editor/api/menus/{id}` payload. All fields
 * optional.
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

class UpdateMenuRequest extends FormRequest
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
			'theme'          => [ 'sometimes', 'string', 'max:191' ],
			'slug'           => [ 'sometimes', 'string', 'max:191' ],
			// Accept either `name` (model shape) or `title` (WP REST shape
			// — what the editor's update flow sends). The controller maps
			// `title` → `name` via `modelAttributesFromRequest`.
			'name'           => [ 'sometimes', 'string', 'max:255' ],
			'title'          => [ 'sometimes', 'string', 'max:255' ],
			'description'    => [ 'nullable', 'string' ],
			'auto_add_pages' => [ 'sometimes', 'boolean' ],
			// #440. The site editor PUTs its navigation tree as a
			// `core/navigation-*` block tree under `content.blocks`;
			// the controller bridges it to / from cms-framework's
			// relational `menu_items` rows. `content.raw` is accepted
			// but ignored — the backend re-derives it from the tree.
			'content'        => [ 'sometimes', 'array' ],
			'content.raw'    => [ 'sometimes', 'nullable', 'string' ],
			'content.blocks' => [ 'sometimes', 'array' ],
		];
	}
}
