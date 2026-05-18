<?php

/**
 * Polymorphic validator for the `content` field on
 * `wp_template`-shape REST payloads.
 *
 * Accepts:
 *
 *   - `null` (empty body — happens when Laravel's
 *     `ConvertEmptyStringsToNull` middleware normalizes
 *     `content: ""` into `null`).
 *   - `string` — Gutenberg's default save path serializes a block
 *     tree into a `content` string (`<!-- wp:foo --><p>x</p><!-- /wp:foo -->`).
 *     The store-side controller(s) parse it or pass it through.
 *   - `array { raw?, blocks? }` — the custom save path that ships
 *     both the serialized markup AND the parsed tree.
 *
 * Rejects everything else.
 *
 * Used by both {@see UpdateMenuRequest} (#48 round-trip path) and
 * {@see StoreTemplatePartRequest} (#55 — Gutenberg's Create Overlay
 * POSTs `content` as a string when first creating an overlay
 * template part).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ContentShapeRule implements ValidationRule
{
	public function validate( string $attribute, mixed $value, Closure $fail ): void
	{
		if ( null === $value || is_string( $value ) || is_array( $value ) ) {
			return;
		}

		$fail( 'The :attribute field must be a string or an object.' );
	}
}
