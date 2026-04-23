<?php

/**
 * StoreTemplatePart form request.
 *
 * Validates the payload for creating a `wp_template_part` record via
 * `POST /visual-editor/api/template-parts`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests;

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplatePart;
use ArtisanPackUI\VisualEditor\Rules\TemplateBlockTreeRule;
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
	 * @return array<string, array<int, mixed>>
	 */
	public function rules(): array
	{
		return [
			'slug'           => [
				'required',
				'string',
				'max:191',
				Rule::unique( 'visual_editor_template_parts', 'slug' )
					->where( fn ( $query ) => $query->where( 'theme', $this->input( 'theme' ) ) ),
			],
			// `title` backs a non-nullable DB column (default ''). The store
			// controller falls back to '' when the field is missing, but
			// `null` is never a legal value — rejecting it here keeps the
			// DB from rejecting the write later with an opaque
			// QueryException.
			'title'          => [ 'sometimes', 'string', 'max:255' ],
			'content'        => [ 'nullable', 'array', $this->envelopeShapeRule() ],
			'content.raw'    => [ 'nullable', 'string' ],
			'content.blocks' => [ 'nullable', 'array', new TemplateBlockTreeRule() ],
			'area'           => [
				'required',
				'string',
				Rule::in( VisualEditorTemplatePart::AREAS ),
			],
			'theme'          => [ 'required', 'string', 'max:191' ],
		];
	}

	/**
	 * @return array<string, string>
	 */
	public function messages(): array
	{
		return [
			'area.in' => 'The area must be one of: ' . implode( ', ', VisualEditorTemplatePart::AREAS ) . '.',
		];
	}

	/**
	 * Rejects a bare-list `content` payload.
	 *
	 * `content.blocks` is only validated when the request uses the
	 * `{ raw, blocks }` envelope; without this rule a caller could send
	 * `content: [ <blocks> ]` to skip `TemplateBlockTreeRule` entirely.
	 *
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
