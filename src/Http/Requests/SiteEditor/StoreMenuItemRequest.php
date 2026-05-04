<?php

/**
 * StoreMenuItemRequest — H6 site-editor.
 *
 * Validates the WP REST `wp_navigation_link` payload for `POST
 * /visual-editor/api/menu-items`. Mirrors cms-framework's `MenuItem`
 * model fillable; `type` enum matches the upstream
 * `core/navigation-link` family (link / submenu / page-list) per
 * plan 14 §8.
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

class StoreMenuItemRequest extends FormRequest
{
	/**
	 * Menu-item link types matching the upstream `core/navigation-*` block
	 * family. Mirrored from cms-framework's `MenuItem::TYPE_*` constants.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const TYPES = [ 'link', 'submenu', 'page-list' ];

	/**
	 * Allowlist regex for menu-item URLs. Accepts:
	 *
	 * - Relative paths: `/about`, `/`, `/blog?tag=foo`
	 * - Fragment anchors: `#main`, `#section-2`
	 * - Absolute URLs with whitelisted schemes: `http://`, `https://`,
	 *   `mailto:user@example.com`, `tel:+1...`
	 *
	 * Rejects dangerous schemes (`javascript:`, `data:`, `file:`, etc.)
	 * at validation time so they never reach a renderer.
	 *
	 * @since 1.0.0
	 */
	public const URL_REGEX = '/^(\\/[^\\s]*|#[^\\s]*|(https?:\\/\\/|mailto:|tel:)[^\\s]+)$/i';

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
			'menu_id'     => [ 'required', 'integer', 'min:1' ],
			'parent_id'   => [ 'nullable', 'integer', 'min:1' ],
			'position'    => [ 'sometimes', 'integer', 'min:0' ],
			'type'        => [ 'sometimes', 'string', Rule::in( self::TYPES ) ],
			'label'       => [ 'required', 'string', 'max:255' ],
			// Allowlist URL schemes — relative paths (`/about`), fragment
			// anchors (`#main`), or absolute URLs limited to `http`,
			// `https`, `mailto`, and `tel`. Rejects `javascript:` /
			// `data:` / etc. before they ever reach a renderer.
			'url'         => [ 'nullable', 'string', 'max:2048', 'regex:' . self::URL_REGEX ],
			'target'      => [ 'sometimes', 'string', Rule::in( [ '', '_self', '_blank' ] ) ],
			'rel'         => [ 'nullable', 'string', 'max:191' ],
			'classes'     => [ 'nullable', 'string', 'max:191' ],
			'description' => [ 'nullable', 'string' ],
			'object_type' => [ 'nullable', 'string', 'max:191' ],
			'object_id'   => [ 'nullable', 'integer', 'min:1' ],
			'kind'        => [ 'nullable', 'string', 'max:191' ],
		];
	}

	/**
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function messages(): array
	{
		return [
			'type.in'    => 'The type must be one of: ' . implode( ', ', self::TYPES ) . '.',
			'url.regex'  => 'The url must be a relative path, a fragment anchor, or an absolute URL using http, https, mailto, or tel.',
		];
	}
}
