<?php

/**
 * SuggestLayout form request.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests\Ai;

use ArtisanPackUI\VisualEditor\Ai\Agents\LayoutSuggestionAgent;
use ArtisanPackUI\VisualEditor\Ai\Support\BlockPayloadLimiter;
use Illuminate\Foundation\Http\FormRequest;

class SuggestLayoutRequest extends FormRequest
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
			'section_content'      => [ 'required', 'array', BlockPayloadLimiter::rule() ],
			'available_patterns'   => [ 'required', 'array', 'min:1', 'max:' . LayoutSuggestionAgent::MAX_PATTERNS ],
			'available_patterns.*' => [ 'string', 'max:128' ],
		];
	}
}
