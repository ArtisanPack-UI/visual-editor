<?php

/**
 * UpdateGlobalStyles form request.
 *
 * Validates the payload for writing a user record through
 * `PUT /visual-editor/api/global-styles/{id}`. The shape mirrors the
 * pinned theme.json schema documented in `docs/global-styles.md` and
 * enforced against `artisanpack.visual-editor.global_styles.schema_version`.
 *
 * The request rejects writes whose `version` does not match the pinned
 * version — a schema bump is a conscious upgrade, not a silent drift on
 * whatever value a client happened to send.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGlobalStylesRequest extends FormRequest
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
		$schemaVersion = (int) config(
			'artisanpack.visual-editor.global_styles.schema_version',
			3
		);

		return [
			'version'                          => [ 'required', 'integer', Rule::in( [ $schemaVersion ] ) ],
			'settings'                         => [ 'required', 'array' ],
			'settings.color.palette'           => [ 'sometimes', 'array', $this->uniqueSlugRule( 'palette' ) ],
			'settings.color.palette.*.slug'    => [ 'required_with:settings.color.palette', 'string' ],
			'settings.color.palette.*.name'    => [ 'required_with:settings.color.palette', 'string' ],
			'settings.color.palette.*.color'   => [ 'required_with:settings.color.palette', 'string' ],
			'settings.typography.fontFamilies' => [ 'sometimes', 'array', $this->uniqueSlugRule( 'fontFamilies' ) ],
			'settings.typography.fontSizes'    => [ 'sometimes', 'array', $this->uniqueSlugRule( 'fontSizes' ) ],
			'styles'                           => [ 'required', 'array' ],
		];
	}

	/**
	 * @return array<string, string>
	 */
	public function messages(): array
	{
		$schemaVersion = (int) config(
			'artisanpack.visual-editor.global_styles.schema_version',
			3
		);

		return [
			'version.in' => 'The version must match the pinned theme.json schema version (' . $schemaVersion . ').',
		];
	}

	/**
	 * Rejects a preset list whose entries share a `slug`.
	 *
	 * theme.json presets are addressed by slug (`var(--wp--preset--color--primary)`)
	 * so two palette entries with the same slug would collapse into a
	 * single CSS variable — the site-editor UI can't distinguish them.
	 * We catch that here rather than letting the duplicate through and
	 * surfacing a confusing downstream bug.
	 *
	 * @since 1.0.0
	 */
	protected function uniqueSlugRule( string $label ): Closure
	{
		return function ( string $attribute, mixed $value, Closure $fail ) use ( $label ): void {
			if ( ! is_array( $value ) ) {
				return;
			}

			$slugs = [];

			foreach ( $value as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}

				$slug = isset( $entry['slug'] ) && is_string( $entry['slug'] ) ? $entry['slug'] : null;

				if ( null === $slug || '' === $slug ) {
					continue;
				}

				if ( in_array( $slug, $slugs, true ) ) {
					$fail( 'The ' . $label . ' presets must have unique slugs (duplicate: "' . $slug . '").' );

					return;
				}

				$slugs[] = $slug;
			}
		};
	}
}
