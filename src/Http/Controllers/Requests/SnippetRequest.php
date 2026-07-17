<?php

/**
 * Snippet form request.
 *
 * Enforces slug uniqueness, title bounds, and block-tree shape for
 * both create and update.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers\Requests;

use ArtisanPackUI\VisualEditor\Models\Snippet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SnippetRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		$snippet = $this->route( 'snippet' );
		$id      = $snippet instanceof Snippet ? $snippet->id : null;

		return [
			'slug'   => [
				'required',
				'string',
				'max:64',
				'regex:' . Snippet::slugPattern(),
				Rule::unique( 've_snippets', 'slug' )->ignore( $id ),
			],
			'title'  => [ 'nullable', 'string', 'max:255' ],
			'blocks' => [ 'nullable', 'array' ],
		];
	}

	public function messages(): array
	{
		return [
			'slug.regex' => 'The slug must start with a lowercase letter and use only letters, digits, and underscores.',
		];
	}
}
