<?php

/**
 * Taxonomy registry.
 *
 * Normalises the host application's `artisanpack.visual-editor.taxonomies`
 * config into the descriptor list the editor stamps onto its mount element
 * as the `data-taxonomies` JSON attribute. The `artisanpack/post-terms`
 * block reads that list at runtime to register one inserter variation per
 * taxonomy and to populate the taxonomy picker in its Settings sidebar,
 * so custom taxonomies registered by the host surface in the editor with
 * no further wiring. The Query Loop reads the same list for its taxonomy
 * filter, narrowed by each entry's optional `postTypes`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.9.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Resources;

use Illuminate\Support\Str;

class TaxonomyRegistry
{
	/**
	 * Slug character set shared with the JS registry's guard. A taxonomy
	 * slug lands verbatim in the block's `term` attribute and the
	 * variation name, so anything outside this set is dropped rather than
	 * risking a malformed variation name or attribute value.
	 */
	protected const SAFE_SLUG_PATTERN = '/^[a-z0-9_-]+$/';

	/**
	 * Post type character set, matching
	 * `QueryPostTypeRegistry::SAFE_VALUE_PATTERN`.
	 */
	protected const SAFE_POST_TYPE_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

	/**
	 * Resolve the configured taxonomies into a normalised descriptor list.
	 *
	 * Falls back to the `category` / `post_tag` defaults when the config
	 * key is missing or resolves to an empty map, so the editor always
	 * surfaces the two built-in taxonomies.
	 *
	 * @since 1.9.0
	 * @since 1.20.0 Descriptors may carry `postTypes`.
	 *
	 * @return array<int, array{slug: string, label: string, plural: string, postTypes?: list<string>}>
	 */
	public static function fromConfig(): array
	{
		$map = (array) config( 'artisanpack.visual-editor.taxonomies', [] );

		return self::normalise( $map );
	}

	/**
	 * Normalise a raw taxonomy map into descriptor rows.
	 *
	 * Accepts either a `slug => label` string entry or a
	 * `slug => ['label' => ..., 'plural' => ..., 'postTypes' => [...]]`
	 * array entry. Entries whose slug is empty, non-string, or outside
	 * {@see self::SAFE_SLUG_PATTERN} are skipped. When the result is empty
	 * the built-in defaults are returned instead.
	 *
	 * @since 1.9.0
	 * @since 1.20.0 Keeps a declared `postTypes` list.
	 *
	 * @param  array<mixed>  $map  Raw config map.
	 *
	 * @return array<int, array{slug: string, label: string, plural: string, postTypes?: list<string>}>
	 */
	protected static function normalise( array $map ): array
	{
		$taxonomies = [];
		$seen       = [];

		foreach ( $map as $slug => $definition ) {
			if ( ! is_string( $slug ) ) {
				continue;
			}

			$slug = strtolower( trim( $slug ) );

			if ( '' === $slug || 1 !== preg_match( self::SAFE_SLUG_PATTERN, $slug ) ) {
				continue;
			}

			// Config keys that collapse to the same normalised slug
			// (`Genre`, ` genre `, `genre`) would otherwise emit
			// duplicate variations + picker rows; keep the first
			// valid descriptor and skip the rest.
			if ( isset( $seen[ $slug ] ) ) {
				continue;
			}

			$seen[ $slug ] = true;

			$label     = self::resolveLabel( $slug, $definition );
			$plural    = self::resolvePlural( $label, $definition );
			$postTypes = self::resolvePostTypes( $definition );

			$descriptor = [
				'slug'   => $slug,
				'label'  => $label,
				'plural' => $plural,
			];

			if ( [] !== $postTypes ) {
				$descriptor['postTypes'] = $postTypes;
			}

			$taxonomies[] = $descriptor;
		}

		if ( [] === $taxonomies ) {
			return self::defaults();
		}

		return $taxonomies;
	}

	/**
	 * Derive a display label from the config entry, falling back to a
	 * title-cased version of the slug.
	 *
	 * @since 1.9.0
	 *
	 * @param  string  $slug        Taxonomy slug.
	 * @param  mixed   $definition  Raw config value for the slug.
	 *
	 * @return string
	 */
	protected static function resolveLabel( string $slug, mixed $definition ): string
	{
		if ( is_string( $definition ) && '' !== trim( $definition ) ) {
			return trim( $definition );
		}

		if ( is_array( $definition ) && isset( $definition['label'] ) && is_string( $definition['label'] ) && '' !== trim( $definition['label'] ) ) {
			return trim( $definition['label'] );
		}

		return ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
	}

	/**
	 * Derive the plural label used for inserter keywords, falling back to
	 * Laravel's inflector applied to the singular label.
	 *
	 * @since 1.9.0
	 *
	 * @param  string  $label       Resolved singular label.
	 * @param  mixed   $definition  Raw config value for the slug.
	 *
	 * @return string
	 */
	protected static function resolvePlural( string $label, mixed $definition ): string
	{
		if ( is_array( $definition ) && isset( $definition['plural'] ) && is_string( $definition['plural'] ) && '' !== trim( $definition['plural'] ) ) {
			return trim( $definition['plural'] );
		}

		return Str::plural( $label );
	}

	/**
	 * Read the post types a taxonomy is bound to, dropping duplicates and
	 * values outside {@see self::SAFE_POST_TYPE_PATTERN}.
	 *
	 * @since 1.20.0
	 *
	 * @param  mixed  $definition  Raw config value for the slug.
	 *
	 * @return list<string> Empty when the entry doesn't declare any.
	 */
	protected static function resolvePostTypes( mixed $definition ): array
	{
		if ( ! is_array( $definition ) || ! isset( $definition['postTypes'] ) || ! is_array( $definition['postTypes'] ) ) {
			return [];
		}

		$postTypes = [];

		foreach ( $definition['postTypes'] as $postType ) {
			if ( ! is_string( $postType ) ) {
				continue;
			}

			$postType = trim( $postType );

			if ( 1 === preg_match( self::SAFE_POST_TYPE_PATTERN, $postType ) && ! in_array( $postType, $postTypes, true ) ) {
				$postTypes[] = $postType;
			}
		}

		return $postTypes;
	}

	/**
	 * The built-in public taxonomies, mirroring WordPress core.
	 *
	 * @since 1.9.0
	 *
	 * @return array<int, array{slug: string, label: string, plural: string}>
	 */
	protected static function defaults(): array
	{
		return [
			[ 'slug' => 'category', 'label' => 'Category', 'plural' => 'Categories' ],
			[ 'slug' => 'post_tag', 'label' => 'Tag', 'plural' => 'Tags' ],
		];
	}
}
