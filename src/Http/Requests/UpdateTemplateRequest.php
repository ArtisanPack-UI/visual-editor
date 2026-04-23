<?php

/**
 * UpdateTemplate form request.
 *
 * Validates the payload for updating a `wp_template` record via
 * `PUT /visual-editor/api/templates/{id}`.
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

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;
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
	 * @return array<string, array<int, mixed>>
	 */
	public function rules(): array
	{
		/** @var VisualEditorTemplate $template */
		$template = $this->route( 'template' );

		$resolvedSlug  = $this->input( 'slug', $template->slug );
		$resolvedTheme = $this->input( 'theme', $template->theme );

		// Composite-uniqueness is mirrored on both slug and theme so a
		// PUT that changes *either* field triggers the check. Without
		// the theme-side mirror, `{ "theme": "other" }` (no slug) would
		// slip past the `sometimes`-gated slug rule and rely on the DB
		// constraint to reject the collision as an opaque
		// QueryException.
		$uniqueness = Rule::unique( 'visual_editor_templates', 'slug' )
			->ignore( $template->getKey() )
			->where( fn ( $query ) => $query->where( 'theme', $resolvedTheme ) );

		$themeUniqueness = Rule::unique( 'visual_editor_templates', 'theme' )
			->ignore( $template->getKey() )
			->where( fn ( $query ) => $query->where( 'slug', $resolvedSlug ) );

		return [
			'slug'            => [
				'sometimes',
				'required',
				'string',
				'max:191',
				$uniqueness,
			],
			// `title`, `status`, and `source` back non-nullable DB
			// columns — null is never a legal value. The update
			// controller leaves missing fields untouched, so dropping
			// `nullable` here only tightens the error path without
			// changing what a partial update can do.
			'title'           => [ 'sometimes', 'string', 'max:255' ],
			'description'     => [ 'sometimes', 'nullable', 'string' ],
			'content'         => [ 'sometimes', 'nullable', 'array', $this->envelopeShapeRule() ],
			'content.raw'     => [ 'sometimes', 'nullable', 'string' ],
			'content.blocks'  => [ 'sometimes', 'nullable', 'array', new TemplateBlockTreeRule() ],
			'status'          => [
				'sometimes',
				'string',
				Rule::in( [
					VisualEditorTemplate::STATUS_PUBLISH,
					VisualEditorTemplate::STATUS_DRAFT,
					VisualEditorTemplate::STATUS_PRIVATE,
				] ),
			],
			'theme'           => [
				'sometimes',
				'required',
				'string',
				'max:191',
				$themeUniqueness,
			],
			'source'          => [
				'sometimes',
				'string',
				Rule::in( [
					VisualEditorTemplate::SOURCE_THEME,
					VisualEditorTemplate::SOURCE_CUSTOM,
				] ),
			],
			'origin'          => [
				'sometimes',
				'nullable',
				'string',
				Rule::in( [
					VisualEditorTemplate::ORIGIN_THEME,
					VisualEditorTemplate::ORIGIN_PLUGIN,
					VisualEditorTemplate::ORIGIN_CUSTOM,
				] ),
			],
		];
	}

	/**
	 * Rejects a bare-list `content` payload — see the mirrored method
	 * on {@see StoreTemplateRequest::envelopeShapeRule()} for why.
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
