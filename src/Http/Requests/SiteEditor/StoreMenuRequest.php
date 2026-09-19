<?php

/**
 * StoreMenuRequest — H6 site-editor.
 *
 * Validates the WP REST `wp_navigation` payload for `POST
 * /visual-editor/api/menus`. Mirrors cms-framework's `Menu` model
 * fillable; the (theme, slug) uniqueness check is enforced by
 * cms-framework's DB index, not duplicated here.
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

class StoreMenuRequest extends FormRequest
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
			// `theme` is optional at the request layer and resolved
			// server-side through cms-framework's active ThemeManager
			// when omitted, matching the editor save flow that doesn't
			// repeat the active theme on every payload (#438).
			'theme'          => [ 'sometimes', 'string', 'max:191' ],
			// `slug` is optional and derived from `title`/`name` server-
			// side when omitted (#797). Gutenberg's `core/navigation`
			// placeholder "Create menu" affordance dispatches
			// `saveEntityRecord('postType', 'wp_navigation', { title,
			// content, status: 'publish' })` — no slug, so requiring
			// it here made the create no-op silently.
			'slug'           => [ 'sometimes', 'string', 'max:191' ],
			// Accept either `name` (model-shape) or `title` (WP REST
			// shape — what the editor's create-menu dialog and
			// Gutenberg's navigation placeholder send). At least one
			// must be present; the controller picks whichever it finds
			// and maps to the model's `name` column.
			'name'           => [ 'sometimes', 'string', 'max:255' ],
			'title'          => [ 'sometimes', 'string', 'max:255' ],
			'description'    => [ 'nullable', 'string' ],
			'auto_add_pages' => [ 'sometimes', 'boolean' ],
			// Gutenberg's `saveEntityRecord` on create ships the
			// serialized block-comment markup as `content` alongside
			// `status: 'publish'`. The controller resolves either a
			// string (serialized markup) or `{ raw?, blocks? }` object
			// into a block tree and rebuilds `menu_items` from it.
			// `status` is accepted but unused — cms-framework menus
			// don't carry a status field.
			'content'        => [ 'sometimes', 'nullable' ],
			'status'         => [ 'sometimes', 'string', 'max:32' ],
		];
	}
}
