<?php

/**
 * StoreTemplate form request.
 *
 * Validates the payload for creating a `wp_template` record via
 * `POST /visual-editor/api/templates`.
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

class StoreTemplateRequest extends FormRequest
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
				Rule::unique( 'visual_editor_templates', 'slug' )
					->where( fn ( $query ) => $query->where( 'theme', $this->input( 'theme' ) ) ),
			],
			// `title`, `status`, and `source` are backed by non-nullable
			// DB columns (defaults: '', 'publish', 'custom'). The store
			// controller falls back to those defaults when the field is
			// missing, but `null` is never a legal value — rejecting it
			// here keeps the DB from rejecting the write later with an
			// opaque QueryException.
			'title'          => [ 'sometimes', 'string', 'max:255' ],
			'description'    => [ 'nullable', 'string' ],
			'content'        => [ 'nullable', 'array', $this->envelopeShapeRule() ],
			'content.raw'    => [ 'nullable', 'string' ],
			'content.blocks' => [ 'nullable', 'array', new TemplateBlockTreeRule() ],
			'status'         => [
				'sometimes',
				'string',
				Rule::in( [
					VisualEditorTemplate::STATUS_PUBLISH,
					VisualEditorTemplate::STATUS_DRAFT,
					VisualEditorTemplate::STATUS_PRIVATE,
				] ),
			],
			'theme'          => [ 'required', 'string', 'max:191' ],
			'source'         => [
				'sometimes',
				'string',
				Rule::in( [
					VisualEditorTemplate::SOURCE_THEME,
					VisualEditorTemplate::SOURCE_CUSTOM,
				] ),
			],
			'origin'         => [
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
