<?php

/**
 * Rename icon set form request.
 *
 * Phase 6 (#557) of the Icon Block feature (#494). Validates the
 * label-only payload for the rename endpoint — the prefix is fixed by
 * the route parameter (renaming the prefix would force a directory move
 * and break in-flight blocks that already reference the old prefix).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests\Icon;

use ArtisanPackUI\VisualEditor\Services\Icon\UploadedIconSetRegistry;
use Illuminate\Foundation\Http\FormRequest;

class RenameIconSetRequest extends FormRequest
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
			'label' => [
				'required',
				'string',
				'max:' . UploadedIconSetRegistry::LABEL_MAX_LENGTH,
			],
		];
	}
}
