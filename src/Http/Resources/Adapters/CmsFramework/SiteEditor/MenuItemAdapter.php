<?php

/**
 * Menu-item adapter — WP `wp_navigation_link` block-attribute shape.
 *
 * Shapes a single item entry from {@see ResolvedMenu::$items} into the
 * record envelope WP's `/wp/v2/menu-items` endpoint emits. Items already
 * arrive in `core/navigation-link` shape from cms-framework's H4 resolver
 * (label, url, type, kind, ...) so this adapter mostly forwards fields
 * with light renaming (`label` → `title.{raw,rendered}`) and parent-menu
 * back-reference enrichment.
 *
 * Open question: V1's editor-side `/visual-editor/api/menu-items` endpoint
 * may not be needed at all — the `core/navigation` block reads items from
 * the parent menu's `items` field, not from a separate fetch. This adapter
 * exists so the issue's stated REST surface compiles; H7's UI rescope is
 * the natural place to confirm or drop the endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor;

class MenuItemAdapter
{
	/**
	 * Single-record `wp_navigation_link` envelope.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $item    Raw item entry from {@see ResolvedMenu::$items}.
	 * @param  int|string  $menuId            Parent menu identifier — propagated to the
	 *                                        `menus` field so the editor can group items
	 *                                        by parent without re-walking the menu list.
	 *
	 * @return array{
	 *     id: int|string,
	 *     menus: int|string,
	 *     parent: int,
	 *     position: int,
	 *     type: string,
	 *     title: array{rendered: string, raw: string},
	 *     url: string,
	 *     target: string,
	 *     classes: array<int, string>,
	 *     xfn: array<int, string>,
	 *     description: string,
	 *     object: string|null,
	 *     object_id: int|null,
	 *     kind: string|null
	 * }
	 */
	public function toArray( array $item, int|string $menuId ): array
	{
		$label = isset( $item['label'] ) && is_string( $item['label'] ) ? $item['label'] : '';

		return [
			'id'          => $this->scalarOr( $item['id'] ?? null, '' ),
			'menus'       => $menuId,
			'parent'      => isset( $item['parent_id'] ) ? (int) $item['parent_id'] : 0,
			'position'    => isset( $item['position'] ) ? (int) $item['position'] : 0,
			'type'        => isset( $item['type'] ) && is_string( $item['type'] ) ? $item['type'] : 'link',
			'title'       => [
				'rendered' => $label,
				'raw'      => $label,
			],
			'url'         => isset( $item['url'] ) && is_string( $item['url'] ) ? $item['url'] : '',
			'target'      => isset( $item['target'] ) && is_string( $item['target'] ) ? $item['target'] : '',
			'classes'     => $this->stringList( $item['classes'] ?? null ),
			'xfn'         => $this->stringList( $item['rel'] ?? null ),
			'description' => isset( $item['description'] ) && is_string( $item['description'] ) ? $item['description'] : '',
			'object'      => isset( $item['object_type'] ) && is_string( $item['object_type'] ) ? $item['object_type'] : null,
			'object_id'   => isset( $item['object_id'] ) ? (int) $item['object_id'] : null,
			'kind'        => isset( $item['kind'] ) && is_string( $item['kind'] ) ? $item['kind'] : null,
		];
	}

	/**
	 * Flatten a menu's items into a list of `wp_navigation_link` envelopes.
	 *
	 * @since 1.0.0
	 *
	 * @param  iterable<array<string, mixed>>  $items
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function collection( iterable $items, int|string $menuId ): array
	{
		$out = [];

		foreach ( $items as $item ) {
			$out[] = $this->toArray( $item, $menuId );
		}

		return $out;
	}

	/**
	 * Coerces a value to a string-or-int scalar with a fallback. Used for
	 * the `id` field where a contributor may supply either form (DB row
	 * ids are ints, soft-id slugs are strings) and we want to surface
	 * whichever was supplied without forcing one shape on the wire.
	 *
	 * @since 1.0.0
	 */
	protected function scalarOr( mixed $value, string $fallback ): int|string
	{
		if ( is_int( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}

		return $fallback;
	}

	/**
	 * Normalize a comma-separated string or string array into a list of
	 * non-empty strings. Items arriving from cms-framework's H4 resolver
	 * may use either shape depending on whether the underlying column was
	 * stored as text or JSON.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function stringList( mixed $value ): array
	{
		if ( is_string( $value ) ) {
			$parts = preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY );

			return false === $parts ? [] : array_values( $parts );
		}

		if ( is_array( $value ) ) {
			// Match the string-input branch's `PREG_SPLIT_NO_EMPTY` semantics:
			// drop empty strings as well as non-strings so an array like
			// `['', 'nofollow']` collapses to `['nofollow']`.
			return array_values( array_filter(
				$value,
				static fn ( $entry ): bool => is_string( $entry ) && '' !== $entry,
			) );
		}

		return [];
	}
}
