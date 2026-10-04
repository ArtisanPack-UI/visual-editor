<?php

/**
 * RewriteContent form request.
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

class RewriteContentRequest extends FormRequest
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
			'content' => [ 'required', 'string', 'max:' . BlockPayloadLimiter::limits()['max_text_chars'] ],
			'intent'  => [ 'required', 'string', 'max:256' ],
		];
	}
}
