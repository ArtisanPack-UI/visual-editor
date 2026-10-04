<?php

/**
 * SuggestNextBlock form request.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests\Ai;

use ArtisanPackUI\VisualEditor\Ai\Support\BlockPayloadLimiter;
use Illuminate\Foundation\Http\FormRequest;

class SuggestNextBlockRequest extends FormRequest
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
			'existing_blocks' => [ 'required', 'array', BlockPayloadLimiter::rule() ],
			'cursor_position' => [ 'required', 'integer', 'min:0' ],
			'document_type'   => [ 'nullable', 'string', 'max:64' ],
		];
	}
}
