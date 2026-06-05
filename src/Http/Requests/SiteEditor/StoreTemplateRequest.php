<?php

/**
 * StoreTemplateRequest — H6 site-editor.
 *
 * Validates the WP REST `wp_template` payload for `POST
 * /visual-editor/api/templates`. Plan 14 §4.5: visual-editor's controller
 * accepts WP-shape input, then forwards to cms-framework's `Template`
 * model. Per-theme slug uniqueness is enforced by cms-framework's
 * unique index — visual-editor surfaces the resulting `QueryException`
 * as a 409, so duplicating the unique check here would be redundant
 * (and would couple the form request back to a specific persistence
 * layer we no longer own).
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

class StoreTemplateRequest extends FormRequest
{
	/**
	 * Mirrors cms-framework's `Template::STATUS_*` constants. Hardcoded so
	 * the form request stays decoupled from the cms-framework class — the
	 * controller's `class_exists` guard means we can't depend on the
	 * symbol being autoloaded at validation time.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const STATUSES = [ 'publish', 'draft', 'private' ];

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
			'title'          => [ 'sometimes', 'string', 'max:255' ],
			'description'    => [ 'nullable', 'string' ],
			'content'        => [ 'nullable', 'array', $this->envelopeShapeRule() ],
			'content.raw'    => [ 'nullable', 'string' ],
			'content.blocks' => [ 'nullable', 'array', new TemplateBlockTreeRule() ],
			'status'         => [ 'sometimes', 'string', Rule::in( self::STATUSES ) ],
			'theme'          => [ 'required', 'string', 'max:191' ],
			'is_custom'      => [ 'sometimes', 'boolean' ],
		];
	}

	/**
	 * Rejects a bare-list `content` payload — without this guard a caller
	 * could send `content: [ <blocks> ]` to skip {@see TemplateBlockTreeRule}.
	 * The block-tree rule is registered against `content.blocks`, which only
	 * fires when the request uses the `{ raw, blocks }` envelope.
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
