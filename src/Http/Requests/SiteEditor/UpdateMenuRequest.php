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

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Polymorphic shape validator for the `content` field. Gutenberg's
 * default save path serializes the navigation block tree into a
 * string (the WP-REST default for `wp_navigation`); the #440 custom
 * save path sends an object `{ raw?, blocks? }`. Accept both, reject
 * everything else.
 */
final class ContentShapeRule implements ValidationRule
{
	public function validate( string $attribute, mixed $value, Closure $fail ): void
	{
		// `null` happens when Laravel's `ConvertEmptyStringsToNull`
		// middleware normalizes an authored `content: ""` (which
		// Gutenberg sends when the user clears every item) into
		// `null`. Treat it as "empty content" — same semantic as
		// `content.blocks: []` — and let the controller wipe the
		// items.
		if ( null === $value || is_string( $value ) || is_array( $value ) ) {
			return;
		}

		$fail( 'The :attribute field must be a string or an object.' );
	}
}

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
			// `content` arrives in two shapes:
			//  - String: Gutenberg's default save path serializes the
			//    edited block tree into `content` (the WP-REST default
			//    for `wp_navigation`). Keystone #48 — the controller
			//    parses it back via `MenuItemBlockBridge::rawToBlocks`
			//    and routes through the existing replaceMenuItems path.
			//  - Array `{ raw?, blocks? }`: #440's custom path that
			//    bypasses the round-trip. `content.raw` is informational
			//    here; the backend re-derives it from `menu_items`.
			'content'        => [ 'sometimes', new ContentShapeRule() ],
			'content.raw'    => [ 'sometimes', 'nullable', 'string' ],
			'content.blocks' => [ 'sometimes', 'array' ],
		];
	}
}
