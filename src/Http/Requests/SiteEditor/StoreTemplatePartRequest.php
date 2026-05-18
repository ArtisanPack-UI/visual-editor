<?php

/**
 * StoreTemplatePartRequest — H6 site-editor.
 *
 * Validates the WP REST `wp_template_part` payload for `POST
 * /visual-editor/api/template-parts`. Adds the closed-list `area` field
 * to {@see StoreTemplateRequest}'s shape; the area enum is defined on
 * {@see ResolvedTemplatePart} so visual-editor and cms-framework agree
 * on the V1 set without each owning their own copy.
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

class StoreTemplatePartRequest extends FormRequest
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
			'slug'           => [ 'required', 'string', 'max:191' ],
			'title'          => [ 'sometimes', 'string', 'max:255' ],
			'description'    => [ 'nullable', 'string' ],
			'content'        => [ 'nullable', 'array', $this->envelopeShapeRule() ],
			'content.raw'    => [ 'nullable', 'string' ],
			'content.blocks' => [ 'nullable', 'array', new TemplateBlockTreeRule() ],
			'area'           => [ 'required', 'string', Rule::in( ResolvedTemplatePart::AREAS ) ],
			// `theme` is optional on the request — Gutenberg's Create
			// Overlay action POSTs without it. The controller falls back
			// to the active theme via `ThemeManager::getActiveTheme()`
			// when the field is missing, so a host with cms-framework
			// integrated always lands with a non-empty theme on the row
			// (Keystone #55).
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
