<?php

/**
 * StorePattern form request.
 *
 * Validates the payload for creating a `wp_block` record via
 * `POST /visual-editor/api/patterns`.
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

class StorePatternRequest extends FormRequest
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
				Rule::unique( 'visual_editor_patterns', 'slug' ),
			],
			// `title` and `status` are backed by non-nullable DB columns
			// (defaults: '', 'publish'). The store controller falls back
			// to those defaults when the field is missing, but `null` is
			// never a legal value — rejecting it here keeps the DB from
			// rejecting the write later with an opaque QueryException.
			'title'          => [ 'sometimes', 'string', 'max:255' ],
			'content'        => [ 'nullable', 'array', $this->envelopeShapeRule() ],
			'content.raw'    => [ 'nullable', 'string' ],
			'content.blocks' => [ 'nullable', 'array', new TemplateBlockTreeRule() ],
			'synced'         => [ 'sometimes', 'boolean' ],
			'status'         => [
				'sometimes',
				'string',
				Rule::in( [
					VisualEditorPattern::STATUS_PUBLISH,
					VisualEditorPattern::STATUS_DRAFT,
					VisualEditorPattern::STATUS_PRIVATE,
				] ),
			],
			'categories'     => [ 'sometimes', 'array' ],
			'categories.*'   => [ 'string', 'max:191' ],
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
