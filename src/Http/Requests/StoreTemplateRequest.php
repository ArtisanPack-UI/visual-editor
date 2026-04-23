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
			'title'          => [ 'nullable', 'string', 'max:255' ],
			'description'    => [ 'nullable', 'string' ],
			'content'        => [ 'nullable', 'array' ],
			'content.raw'    => [ 'nullable', 'string' ],
			'content.blocks' => [ 'nullable', 'array', new TemplateBlockTreeRule() ],
			'status'         => [
				'nullable',
				'string',
				Rule::in( [
					VisualEditorTemplate::STATUS_PUBLISH,
					VisualEditorTemplate::STATUS_DRAFT,
					VisualEditorTemplate::STATUS_PRIVATE,
				] ),
			],
			'theme'          => [ 'required', 'string', 'max:191' ],
			'source'         => [
				'nullable',
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
}
