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

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
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
			'image'        => [ 'required', $this->stringOrArrayRule() ],
			'image.source' => [ 'required_array_keys:source,value', 'string', 'in:path,url,base64' ],
			'image.value'  => [ 'sometimes', 'string' ],
		];
	}

	/**
	 * `image` must be a string OR an array with source+value keys — the
	 * two shapes AltTextGenerationAgent normalizes. Laravel's built-in
	 * rules don't compose "string OR array", so this closure handles it.
	 *
	 * @since 1.3.0
	 */
	private function stringOrArrayRule(): Closure
	{
		return function ( string $attribute, mixed $value, Closure $fail ): void {
			if ( is_string( $value ) && '' !== $value ) {
				return;
			}
			if ( is_array( $value ) && isset( $value['source'], $value['value'] ) ) {
				return;
			}
			$fail( 'The image must be a non-empty string or an object with `source` and `value` keys.' );
		};
	}
}
