<?php

/**
 * Validates a request to import a remote image.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape of a media import. Authorization lives on the route and the
 * controller.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.20.0
 */
class ImportMediaRequest extends FormRequest
{
	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @since 1.20.0
	 */
	public function authorize(): bool
	{
		return true;
	}

	/**
	 * The validation rules.
	 *
	 * @since 1.20.0
	 *
	 * @return array<string, array<int, string>>
	 */
	public function rules(): array
	{
		return [
			'url' => [ 'required', 'string', 'max:2048' ],
			'alt' => [ 'nullable', 'string', 'max:1000' ],
		];
	}
}
