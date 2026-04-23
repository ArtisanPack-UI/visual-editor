<?php

/**
 * UpdatePattern form request.
 *
 * Validates the payload for updating a `wp_block` record via
 * `PUT /visual-editor/api/patterns/{id}`.
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

use ArtisanPackUI\VisualEditor\Models\VisualEditorPattern;
use ArtisanPackUI\VisualEditor\Rules\TemplateBlockTreeRule;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatternRequest extends FormRequest
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
		/** @var VisualEditorPattern $pattern */
		$pattern = $this->route( 'pattern' );

		return [
			'slug'            => [
				'sometimes',
				'required',
				'string',
				'max:191',
				Rule::unique( 'visual_editor_patterns', 'slug' )->ignore( $pattern->getKey() ),
			],
			// `title` and `status` back non-nullable DB columns — null is
			// never a legal value. The update controller leaves missing
			// fields untouched, so dropping `nullable` here only tightens
			// the error path without changing what a partial update can do.
			'title'           => [ 'sometimes', 'string', 'max:255' ],
			'content'         => [ 'sometimes', 'nullable', 'array', $this->envelopeShapeRule() ],
			'content.raw'     => [ 'sometimes', 'nullable', 'string' ],
			'content.blocks'  => [ 'sometimes', 'nullable', 'array', new TemplateBlockTreeRule() ],
			'synced'          => [ 'sometimes', 'boolean' ],
			'status'          => [
				'sometimes',
				'string',
				Rule::in( [
					VisualEditorPattern::STATUS_PUBLISH,
					VisualEditorPattern::STATUS_DRAFT,
					VisualEditorPattern::STATUS_PRIVATE,
				] ),
			],
			'categories'      => [ 'sometimes', 'array' ],
			'categories.*'    => [ 'string', 'max:191' ],
		];
	}

	/**
	 * Rejects a bare-list `content` payload — see the mirrored method on
	 * {@see StorePatternRequest::envelopeShapeRule()} for why.
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
