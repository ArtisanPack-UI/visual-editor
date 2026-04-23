<?php

/**
 * UpdateNavigation form request.
 *
 * Validates the payload for updating a `wp_navigation` record via
 * `PUT /visual-editor/api/navigation/{id}`.
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

use ArtisanPackUI\VisualEditor\Models\VisualEditorNavigation;
use ArtisanPackUI\VisualEditor\Rules\TemplateBlockTreeRule;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNavigationRequest extends FormRequest
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
		/** @var VisualEditorNavigation $navigation */
		$navigation = $this->route( 'navigation' );

		return [
			'slug'           => [
				'sometimes',
				'required',
				'string',
				'max:191',
				Rule::unique( 'visual_editor_navigations', 'slug' )
					->ignore( $navigation->getKey() ),
			],
			// `title` and `status` back non-nullable DB columns — null is
			// never a legal value. The update controller leaves missing
			// fields untouched, so dropping `nullable` here only tightens
			// the error path without changing what a partial update can
			// do.
			'title'          => [ 'sometimes', 'string', 'max:255' ],
			'content'        => [ 'sometimes', 'nullable', 'array', $this->envelopeShapeRule() ],
			'content.raw'    => [ 'sometimes', 'nullable', 'string' ],
			'content.blocks' => [ 'sometimes', 'nullable', 'array', new TemplateBlockTreeRule() ],
			'status'         => [
				'sometimes',
				'string',
				Rule::in( [
					VisualEditorNavigation::STATUS_PUBLISH,
					VisualEditorNavigation::STATUS_DRAFT,
					VisualEditorNavigation::STATUS_PRIVATE,
				] ),
			],
			'menu_order'     => [ 'sometimes', 'integer', 'min:0' ],
		];
	}

	/**
	 * Rejects a bare-list `content` payload — see the mirrored method on
	 * {@see StoreNavigationRequest::envelopeShapeRule()} for why.
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
