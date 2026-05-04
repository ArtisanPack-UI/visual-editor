<?php

/**
 * StorePatternRequest — H6 site-editor.
 *
 * Validates the WP REST `wp_block` payload for `POST
 * /visual-editor/api/patterns`. Theme patterns are file-only — they
 * never come through this endpoint — so `source` is constrained to
 * `user`. cms-framework's `BlockPattern` model auto-prefixes user
 * slugs with `user/` at storage (plan 14 §5.6); this form request
 * accepts the user-facing (unprefixed) slug.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor;

use ArtisanPackUI\VisualEditor\Rules\TemplateBlockTreeRule;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatternRequest extends FormRequest
{
	/**
	 * Pattern source enum. `theme` is read-only (file-backed) so writes
	 * are restricted to `user`.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const STORE_SOURCES = [ 'user' ];

	public function authorize(): bool
	{
		return true;
	}

	/**
	 * @since 1.0.0
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function rules(): array
	{
		return [
			'slug'           => [ 'required', 'string', 'max:191' ],
			'title'          => [ 'required', 'string', 'max:255' ],
			'description'    => [ 'nullable', 'string' ],
			'content'        => [ 'nullable', 'array', $this->envelopeShapeRule() ],
			'content.raw'    => [ 'nullable', 'string' ],
			'content.blocks' => [ 'nullable', 'array', new TemplateBlockTreeRule() ],
			'source'         => [ 'sometimes', 'string', Rule::in( self::STORE_SOURCES ) ],
			'synced'         => [ 'sometimes', 'boolean' ],
			'categories'     => [ 'sometimes', 'array' ],
			'categories.*'   => [ 'string', 'max:191' ],
			'block_types'    => [ 'sometimes', 'array' ],
			'block_types.*'  => [ 'string', 'max:191' ],
			'theme'          => [ 'nullable', 'string', 'max:191' ],
		];
	}

	/**
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
