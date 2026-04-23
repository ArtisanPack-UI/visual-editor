<?php

/**
 * UpdateTemplatePart form request.
 *
 * Validates the payload for updating a `wp_template_part` record via
 * `PUT /visual-editor/api/template-parts/{id}`.
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

class UpdateTemplatePartRequest extends FormRequest
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
		/** @var VisualEditorTemplatePart $templatePart */
		$templatePart = $this->route( 'templatePart' );

		$resolvedSlug  = $this->input( 'slug', $templatePart->slug );
		$resolvedTheme = $this->input( 'theme', $templatePart->theme );

		// Composite-uniqueness is mirrored on both slug and theme so a
		// PUT that changes *either* field triggers the check. Without
		// the theme-side mirror, `{ "theme": "other" }` (no slug) would
		// slip past the `sometimes`-gated slug rule and rely on the DB
		// constraint to reject the collision as an opaque
		// QueryException.
		$uniqueness = Rule::unique( 'visual_editor_template_parts', 'slug' )
			->ignore( $templatePart->getKey() )
			->where( fn ( $query ) => $query->where( 'theme', $resolvedTheme ) );

		$themeUniqueness = Rule::unique( 'visual_editor_template_parts', 'theme' )
			->ignore( $templatePart->getKey() )
			->where( fn ( $query ) => $query->where( 'slug', $resolvedSlug ) );

		return [
			'slug'           => [
				'sometimes',
				'required',
				'string',
				'max:191',
				$uniqueness,
			],
			// `title` backs a non-nullable DB column — null is never a
			// legal value. The update controller leaves missing fields
			// untouched, so dropping `nullable` here only tightens the
			// error path without changing what a partial update can do.
			'title'          => [ 'sometimes', 'string', 'max:255' ],
			'content'        => [ 'sometimes', 'nullable', 'array', $this->envelopeShapeRule() ],
			'content.raw'    => [ 'sometimes', 'nullable', 'string' ],
			'content.blocks' => [ 'sometimes', 'nullable', 'array', new TemplateBlockTreeRule() ],
			'area'           => [
				'sometimes',
				'required',
				'string',
				Rule::in( VisualEditorTemplatePart::AREAS ),
			],
			'theme'          => [
				'sometimes',
				'required',
				'string',
				'max:191',
				$themeUniqueness,
			],
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
	 * Rejects a bare-list `content` payload — see the mirrored method
	 * on {@see StoreTemplatePartRequest::envelopeShapeRule()} for why.
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
