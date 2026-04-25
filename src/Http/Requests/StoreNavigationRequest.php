<?php

/**
 * StoreNavigation form request.
 *
 * Validates the payload for creating a `wp_navigation` record via
 * `POST /visual-editor/api/navigation`.
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

use ArtisanPackUI\VisualEditor\Models\VisualEditorNavigation;
use ArtisanPackUI\VisualEditor\Rules\TemplateBlockTreeRule;
use ArtisanPackUI\VisualEditor\Services\MenuLocationResolver;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNavigationRequest extends FormRequest
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
			'slug'           => [
				'required',
				'string',
				'max:191',
				Rule::unique( 'visual_editor_navigations', 'slug' ),
			],
			// `title` and `status` back non-nullable DB columns (defaults:
			// '', 'publish'). The store controller falls back to those
			// defaults when the field is missing, but `null` is never a
			// legal value — rejecting it here keeps the DB from rejecting
			// the write later with an opaque QueryException.
			'title'          => [ 'sometimes', 'string', 'max:255' ],
			'content'        => [ 'nullable', 'array', $this->envelopeShapeRule() ],
			'content.raw'    => [ 'nullable', 'string' ],
			'content.blocks' => [ 'nullable', 'array', new TemplateBlockTreeRule() ],
			'status'         => [
				'sometimes',
				'string',
				Rule::in( [
					VisualEditorNavigation::STATUS_PUBLISH,
					VisualEditorNavigation::STATUS_DRAFT,
					VisualEditorNavigation::STATUS_PRIVATE,
				] ),
			],
			'menu_order'     => [ 'sometimes', 'integer', 'min:0' ],
			// `location` is nullable (most menus sit unassigned). When
			// present it must name a slug declared in
			// `artisanpack.visual-editor.navigation.locations` so the
			// editor can never write an assignment to a slot the front
			// end has no theme hook for.
			'location'       => [ 'sometimes', 'nullable', 'string', 'max:191', $this->locationSlugRule() ],
		];
	}

	/**
	 * Rejects a bare-list `content` payload.
	 *
	 * `content.blocks` is only validated when the request uses the
	 * `{ raw, blocks }` envelope; without this rule a caller could send
	 * `content: [ <blocks> ]` to skip `TemplateBlockTreeRule` entirely.
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

	/**
	 * Restricts the `location` slug to one of the declared
	 * configuration entries.
	 *
	 * Resolves the resolver lazily through the container so request
	 * tests that don't bootstrap the package config don't trip the
	 * binding.
	 *
	 * @since 1.0.0
	 */
	protected function locationSlugRule(): Closure
	{
		return function ( string $attribute, mixed $value, Closure $fail ): void {
			if ( null === $value || '' === $value ) {
				return;
			}

			if ( ! is_string( $value ) ) {
				return;
			}

			/** @var MenuLocationResolver $resolver */
			$resolver = $this->container->make( MenuLocationResolver::class );
			$slugs    = array_keys( $resolver->locations() );

			if ( ! in_array( $value, $slugs, true ) ) {
				$fail( 'The :attribute is not a configured menu location.' );
			}
		};
	}
}
