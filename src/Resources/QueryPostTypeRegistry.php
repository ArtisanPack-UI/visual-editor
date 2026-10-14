<?php

/**
 * Query Loop post-type registry (#847).
 *
 * Resolves the content types the Query Loop block's "Post type" picker
 * offers in the inspector. Hosts extend the list through the
 * `ap.visualEditor.query.postTypes` filter; the normalised result is
 * stamped onto both editor mounts as the `data-query-post-types` JSON
 * attribute, so a host can surface its own content types without
 * shipping a JS bundle. The JS-side `ap.visualEditor.query.postTypes`
 * filter runs on top of this list.
 *
 * Visual-editor stays agnostic about which types exist — whatever
 * `postType` a saved block carries is resolved by the bound
 * `QueryResolverContract`. This registry only decides what the picker
 * shows.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.14.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Resources;

class QueryPostTypeRegistry
{
	/**
	 * Filter hosts hook to add, relabel, or remove picker options.
	 *
	 * @since 1.14.0
	 */
	public const FILTER = 'ap.visualEditor.query.postTypes';

	/**
	 * Character set and length a post-type value must match. The 64-char
	 * cap matches `QueryResolveRequest`'s `postType` rule so a listed type
	 * can always be previewed. Mirrors the JS-side guard in
	 * `blocks/query/post-type-options.ts`.
	 */
	protected const SAFE_VALUE_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

	/**
	 * Resolve the picker options.
	 *
	 * Runs the package defaults (Posts, Pages) through
	 * {@see self::FILTER} and normalises the result. Falls back to the
	 * defaults when the filter returns a non-array or a list with no
	 * valid entries, so the picker is never empty.
	 *
	 * @since 1.14.0
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function resolve(): array
	{
		$defaults = self::defaults();
		$filtered = applyFilters( self::FILTER, $defaults );

		if ( ! is_array( $filtered ) ) {
			return $defaults;
		}

		$options = self::normalise( $filtered );

		return [] === $options ? $defaults : $options;
	}

	/**
	 * Normalise raw filter output into option rows.
	 *
	 * Keeps entries with a non-empty string `label` and a `value` inside
	 * {@see self::SAFE_VALUE_PATTERN}. Duplicate values keep the first
	 * entry, so a host relabels a default by mapping over the list
	 * rather than appending a second row.
	 *
	 * @since 1.14.0
	 *
	 * Public so the `ap.visualEditor.editorConfig` coercer can apply the
	 * same rules to a filter-supplied `queryPostTypes` value.
	 *
	 * @param  array<mixed>  $entries  Raw filter output.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function normalise( array $entries ): array
	{
		$options = [];
		$seen    = [];

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['value'], $entry['label'] ) ) {
				continue;
			}

			if ( ! is_string( $entry['value'] ) || ! is_string( $entry['label'] ) ) {
				continue;
			}

			$value = trim( $entry['value'] );
			$label = trim( $entry['label'] );

			if ( '' === $label || 1 !== preg_match( self::SAFE_VALUE_PATTERN, $value ) ) {
				continue;
			}

			if ( isset( $seen[ $value ] ) ) {
				continue;
			}

			$seen[ $value ] = true;
			$options[]      = [
				'value' => $value,
				'label' => $label,
			];
		}

		return $options;
	}

	/**
	 * The built-in options, matching the picker's pre-#847 hardcoded list.
	 *
	 * @since 1.14.0
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	protected static function defaults(): array
	{
		return [
			[ 'value' => 'post', 'label' => __( 'Posts' ) ],
			[ 'value' => 'page', 'label' => __( 'Pages' ) ],
		];
	}
}
