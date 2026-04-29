<?php

/**
 * StorePost form request — validates a `POST /visual-editor/api/posts`
 * payload in the WP-shape envelope.
 *
 * Validation is intentionally permissive on uniqueness (slug
 * collisions surface as DB integrity errors so the host model's own
 * unique constraints stay the source of truth across cms-framework's
 * Post + any host App\Models\Post that registers under the same
 * slug). The shape and types are enforced here so the controller
 * can fill the model without per-field guards.
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

class StorePostRequest extends FormRequest
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
			'categories'       => [ 'sometimes', 'array' ],
			'categories.*'     => [ 'integer' ],
			'tags'             => [ 'sometimes', 'array' ],
			'tags.*'           => [ 'integer' ],
		];
	}

	/**
	 * Rejects a bare-list `content` payload — `content` must be the
	 * `{ raw, blocks }` envelope so `TemplateBlockTreeRule` reaches
	 * the inner blocks array.
	 *
	 * @since 1.0.0
	 */
	protected function envelopeShapeRule(): Closure
	{
		return function ( string $attribute, mixed $value, Closure $fail ): void {
			if ( is_array( $value ) && [] !== $value && array_is_list( $value ) ) {
				$fail( 'The :attribute must be a { raw, blocks } envelope, not a bare list of blocks.' );
			}
		};
	}
}
