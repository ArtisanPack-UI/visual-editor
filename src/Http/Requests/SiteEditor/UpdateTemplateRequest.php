<?php

/**
 * UpdateTemplateRequest — H6 site-editor.
 *
 * Validates the WP REST `wp_template` payload for `PUT
 * /visual-editor/api/templates/{slug}`. Mirrors {@see StoreTemplateRequest}
 * but loosens `slug` and `theme` to optional — the route slug is canonical
 * and the controller may default the theme from the active-theme manager.
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

use ArtisanPackUI\VisualEditor\Rules\TemplateBlockTreeRule;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateRequest extends FormRequest
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
			'slug'           => [ 'sometimes', 'string', 'max:191' ],
			'title'          => [ 'sometimes', 'string', 'max:255' ],
			'description'    => [ 'nullable', 'string' ],
			'content'        => [ 'nullable', 'array', $this->envelopeShapeRule() ],
			'content.raw'    => [ 'nullable', 'string' ],
			'content.blocks' => [ 'nullable', 'array', new TemplateBlockTreeRule() ],
			'status'         => [ 'sometimes', 'string', Rule::in( StoreTemplateRequest::STATUSES ) ],
			'theme'          => [ 'sometimes', 'string', 'max:191' ],
			'is_custom'      => [ 'sometimes', 'boolean' ],
		];
	}

	/**
	 * @since 1.0.0
	 */
	protected function envelopeShapeRule(): Closure
	{
		return function ( string $attribute, mixed $value, Closure $fail ): void {
			if ( is_array( $value ) && [] !== $value && array_is_list( $value ) ) {
				$fail( 'The :attribute must be a { raw, blocks } envelope, not a bare list of blocks.' );
			}
		};
	}
}
