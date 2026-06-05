<?php

/**
 * UpdateTemplatePartRequest — H6 site-editor.
 *
 * Validates the WP REST `wp_template_part` payload for `PUT
 * /visual-editor/api/template-parts/{slug}`. Mirrors
 * {@see StoreTemplatePartRequest} with all fields loosened to optional
 * — the route slug is canonical and the controller may default `area`
 * and `theme` from the existing record when the payload omits them.
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
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplatePart;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplatePartRequest extends FormRequest
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
			'area'           => [ 'sometimes', 'string', Rule::in( ResolvedTemplatePart::AREAS ) ],
			'theme'          => [ 'sometimes', 'string', 'max:191' ],
			'is_custom'      => [ 'sometimes', 'boolean' ],
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
			'area.in' => 'The area must be one of: ' . implode( ', ', ResolvedTemplatePart::AREAS ) . '.',
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
