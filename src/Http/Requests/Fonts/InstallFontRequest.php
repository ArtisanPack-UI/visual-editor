<?php

/**
 * Install font form request.
 *
 * Validates the payload for installing a catalog font from a registered
 * provider — the provider key, the provider-scoped family slug, and one or more
 * requested faces — before the controller hands it to
 * {@see \ArtisanPackUI\VisualEditor\Fonts\Services\FontInstaller::install()}.
 * Whether the provider is actually registered (and whether it recognizes the
 * slug) is resolved in the controller so the miss returns a shaped 404/422
 * rather than a generic validation failure.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests\Fonts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstallFontRequest extends FormRequest
{
	use AuthorizesFontManagement;

	/**
	 * @since 1.7.0
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function rules(): array
	{
		return [
			'provider'       => [ 'required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_-]*$/' ],
			'slug'           => [ 'required', 'string', 'max:191' ],
			'faces'          => [ 'required', 'array', 'min:1', 'max:50' ],
			'faces.*.weight' => [ 'required', 'integer', 'min:1', 'max:1000' ],
			'faces.*.style'  => [ 'sometimes', 'string', Rule::in( [ 'normal', 'italic' ] ) ],
		];
	}

	/**
	 * @since 1.7.0
	 *
	 * @return array<string, string>
	 */
	public function messages(): array
	{
		return [
			'provider.required'     => __( 'A font provider is required to install a font.' ),
			'provider.regex'        => __( 'The font provider key is not valid.' ),
			'slug.required'         => __( 'A font family is required to install a font.' ),
			'faces.required'        => __( 'At least one weight or style must be selected to install a font.' ),
			'faces.min'             => __( 'At least one weight or style must be selected to install a font.' ),
			'faces.*.weight.required' => __( 'Each selected face must include a font weight.' ),
			'faces.*.weight.integer'  => __( 'Font weight must be a number between 1 and 1000.' ),
			'faces.*.style.in'      => __( 'Font style must be either normal or italic.' ),
		];
	}
}
