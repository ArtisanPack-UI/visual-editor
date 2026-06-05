<?php

/**
 * StorePage form request — validates a `POST /visual-editor/api/pages`
 * payload in the WP-shape envelope. Adds the page-only fields
 * (`parent`, `menu_order`, `template`) on top of the post envelope.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests\Adapters\CmsFramework;

use ArtisanPackUI\VisualEditor\Rules\TemplateBlockTreeRule;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StorePageRequest extends FormRequest
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
			'title'            => [ 'sometimes', 'string', 'max:255' ],
			'slug'             => [ 'sometimes', 'string', 'max:191' ],
			'excerpt'          => [ 'sometimes', 'nullable', 'string' ],
			'status'           => [ 'sometimes', 'string', 'max:32' ],
			'author'           => [ 'sometimes', 'nullable', 'integer' ],
			'featured_media'   => [ 'sometimes', 'nullable', 'integer' ],
			'content'          => [ 'sometimes', 'nullable', 'array', $this->envelopeShapeRule() ],
			'content.raw'      => [ 'sometimes', 'nullable', 'string' ],
			'content.blocks'   => [ 'sometimes', 'nullable', 'array', new TemplateBlockTreeRule() ],
			'parent'           => [ 'sometimes', 'nullable', 'integer' ],
			'menu_order'       => [ 'sometimes', 'integer' ],
			'template'         => [ 'sometimes', 'nullable', 'string', 'max:191' ],
		];
	}

	protected function envelopeShapeRule(): Closure
	{
		return function ( string $attribute, mixed $value, Closure $fail ): void {
			if ( is_array( $value ) && [] !== $value && array_is_list( $value ) ) {
				$fail( 'The :attribute must be a { raw, blocks } envelope, not a bare list of blocks.' );
			}
		};
	}
}
