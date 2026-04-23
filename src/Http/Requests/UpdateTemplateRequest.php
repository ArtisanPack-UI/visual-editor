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

		return [
			'slug'            => [
				'sometimes',
				'required',
				'string',
				'max:191',
				Rule::unique( 'visual_editor_templates', 'slug' )
					->ignore( $template->getKey() )
					->where( fn ( $query ) => $query->where( 'theme', $this->input( 'theme', $template->theme ) ) ),
			],
			'title'           => [ 'sometimes', 'nullable', 'string', 'max:255' ],
			'description'     => [ 'sometimes', 'nullable', 'string' ],
			'content'         => [ 'sometimes', 'nullable', 'array' ],
			'content.raw'     => [ 'sometimes', 'nullable', 'string' ],
			'content.blocks'  => [ 'sometimes', 'nullable', 'array', new TemplateBlockTreeRule() ],
			'status'          => [
				'sometimes',
				'nullable',
				'string',
				Rule::in( [
					VisualEditorTemplate::STATUS_PUBLISH,
					VisualEditorTemplate::STATUS_DRAFT,
					VisualEditorTemplate::STATUS_PRIVATE,
				] ),
			],
			'theme'           => [ 'sometimes', 'required', 'string', 'max:191' ],
			'source'          => [
				'sometimes',
				'nullable',
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
}
