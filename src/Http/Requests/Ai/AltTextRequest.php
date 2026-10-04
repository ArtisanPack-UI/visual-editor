<?php

/**
 * AltText form request.
 *
 * Enforces the string-or-array shape `AltTextGenerationAgent::for()`
 * accepts — closes the review #4 gap where a bare `required` rule let
 * arbitrary scalars (integers, booleans) reach the agent and surface as
 * 500s instead of 422s.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests\Ai;

use ArtisanPackUI\VisualEditor\Ai\Support\AltTextImageGuard;
use Illuminate\Foundation\Http\FormRequest;

class AltTextRequest extends FormRequest
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
			// #828 hardening: only base64 / data URIs and same-site image
			// URLs. A `path` source would read server files.
			'image' => [ 'required', AltTextImageGuard::rule() ],
		];
	}
}
