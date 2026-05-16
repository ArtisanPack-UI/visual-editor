<?php

/**
 * Menu-item ↔ navigation-block bridge — H6 site-editor (#440).
 *
 * The PHP mirror of `resources/js/visual-editor/site-editor/navigation/
 * menu-tree.ts`. The site editor serializes its navigation tree into a
 * `core/navigation-link` / `core/navigation-submenu` block tree and
 * PUTs it as `content.blocks`; this class bridges that wire shape to
 * and from cms-framework's relational `menu_items` rows.
 *
 * Read direction ({@see itemsToBlocks()}): a flat, pre-ordered list of
 * `MenuItem` rows → a nested block tree. An item is emitted as
 * `core/navigation-submenu` iff it has children, `core/navigation-link`
 * otherwise — matching `menu-tree.ts`, which is purely children-driven
 * (it collapses childless submenus to links). The stored `type` column
 * is therefore advisory on read.
 *
 * Write direction ({@see blocksToItemSpecs()}): a block tree → a nested
 * list of row-attribute specs. Blocks whose `name` is neither
 * `core/navigation-link` nor `core/navigation-submenu` are dropped
 * silently (same as `menu-tree.ts` — the native tree editor has no UI
 * for block types it doesn't understand). `parent_id` / `position` are
 * NOT in the specs: they're relational and only known once each row is
 * inserted, so the controller assigns them while it walks the nested
 * specs depth-first.
 *
 * Block ↔ column mapping:
 *
 *   | block                                  | column                        |
 *   |----------------------------------------|-------------------------------|
 *   | name (`-link` vs `-submenu`)           | `type` (`link` / `submenu`)   |
 *   | `attributes.label`                     | `label`                       |
 *   | `attributes.url`                       | `url`                         |
 *   | `attributes.opensInNewTab` (bool)      | `target` (`_blank` / `_self`) |
 *   | `attributes.rel`                       | `rel`                         |
 *   | `attributes.className`                 | `classes`                     |
 *   | `attributes.kind`                      | `kind`                        |
 *   | `attributes.type`                      | `object_type`                 |
 *   | `attributes.id`                        | `object_id`                   |
 *   | `innerBlocks` (recursive)              | `parent_id` + sibling index   |
 *
 * `description` has no representation in the block wire shape, so it is
 * neither read into nor written from blocks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SiteEditor;

class MenuItemBlockBridge
{
	/**
	 * @since 1.0.0
	 */
	public const NAV_LINK = 'core/navigation-link';

	/**
	 * @since 1.0.0
	 */
	public const NAV_SUBMENU = 'core/navigation-submenu';

	/**
	 * Read path — project a flat list of `MenuItem` rows into a nested
	 * `core/navigation-*` block tree.
	 *
	 * Sibling order is normalized here by `(position, id)` rather than
	 * relying on the caller to pre-sort — that mirrors cms-framework's
	 * `Menu::items()` relation ordering and keeps the projection
	 * deterministic for any input (an `index()` eager-load, a lazy
	 * relation read, a hand-built collection in a test, …).
	 *
	 * @since 1.0.0
	 *
	 * @param  iterable<object>  $items  Flat MenuItem rows, any order.
	 *
	 * @return array<int, array<string, mixed>>  Nested block tree.
	 */
	public function itemsToBlocks( iterable $items ): array
	{
		$byParent = [];

		foreach ( $items as $item ) {
			$byParent[ (int) ( $item->parent_id ?? 0 ) ][] = $item;
		}

		foreach ( $byParent as &$siblings ) {
			usort(
				$siblings,
				static fn ( object $a, object $b ): int =>
					[ (int) ( $a->position ?? 0 ), (int) ( $a->id ?? 0 ) ]
					<=> [ (int) ( $b->position ?? 0 ), (int) ( $b->id ?? 0 ) ],
			);
		}
		unset( $siblings );

		return $this->buildBlockBranch( $byParent, 0, [] );
	}

	/**
	 * Serialize a `core/navigation-*` block tree into the WordPress
	 * block-comment markup Gutenberg's `core/navigation` block reads
	 * from `content.raw` (Keystone #48).
	 *
	 * Output format mirrors WP core's `serialize_block()`: the
	 * `core/` prefix is stripped, attributes are JSON-encoded only
	 * when non-empty, leaf blocks self-close, and submenus open /
	 * close around recursively-serialized inner blocks. Lines stay
	 * separated by `\n` so the parser on the way back produces the
	 * same tree we started with.
	 *
	 * Returns an empty string when the tree is empty — keeps the
	 * `content.raw` field present in the envelope but signals to
	 * Gutenberg's nav block that there are no items, the same way an
	 * empty menu would.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, mixed>  $blocks
	 */
	public function blocksToRaw( array $blocks ): string
	{
		$lines = [];

		foreach ( $blocks as $block ) {
			$rendered = $this->serializeBlock( $block );

			if ( '' === $rendered ) {
				continue;
			}

			$lines[] = $rendered;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Parse a WordPress block-comment string back into the block tree
	 * `core/navigation`'s save flow sends us (Keystone #48). Inverse of
	 * {@see blocksToRaw} — Gutenberg's data-store save layer serializes
	 * edited blocks back to `content.raw` (a string) before PUT-ing,
	 * which our `replaceMenuItems` path needs as a structured array.
	 *
	 * Scope is intentionally narrow: only `wp:navigation-link` and
	 * `wp:navigation-submenu` comments are recognized. Anything else
	 * (`wp:paragraph`, free-text between tokens, malformed comments)
	 * is skipped — the controller's `blocksToItemSpecs` step would
	 * drop them anyway, so the parser stays defensive rather than
	 * fighting bad input.
	 *
	 * Tokenize first, then build the tree depth-first against a stack.
	 * Lets a recursive structure (submenu containing submenu containing
	 * link) come back out the way it went in without juggling indices.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function rawToBlocks( string $raw ): array
	{
		$tokens = $this->tokenize( $raw );
		$index  = 0;

		return $this->consumeChildren( $tokens, $index );
	}

	/**
	 * Tokenize a block-comment string into a flat list of OPEN /
	 * SELFCLOSE / CLOSE tokens. Each token carries the canonical block
	 * name (with `core/` prefix re-added) and, for openers, the
	 * decoded attributes array.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function tokenize( string $raw ): array
	{
		$tokens = [];
		$pos    = 0;
		$len    = strlen( $raw );

		while ( $pos < $len ) {
			$openAt = strpos( $raw, '<!--', $pos );

			if ( false === $openAt ) {
				break;
			}

			$closeAt = strpos( $raw, '-->', $openAt );

			if ( false === $closeAt ) {
				break;
			}

			$body = trim( substr( $raw, $openAt + 4, $closeAt - $openAt - 4 ) );
			$pos  = $closeAt + 3;

			$selfClose = false;

			if ( str_ends_with( $body, '/' ) ) {
				$selfClose = true;
				$body      = rtrim( substr( $body, 0, -1 ) );
			}

			if ( str_starts_with( $body, '/wp:' ) ) {
				$tokens[] = [
					'kind' => 'close',
					'name' => 'core/' . trim( substr( $body, 4 ) ),
				];

				continue;
			}

			if ( ! str_starts_with( $body, 'wp:' ) ) {
				continue;
			}

			$tail = substr( $body, 3 );

			// Split block name from optional JSON attrs. The name ends
			// at the first whitespace or `{`; whatever follows is the
			// JSON payload (or empty when the block has no attrs).
			$nameEnd = strcspn( $tail, " \t\n\r\f\v{" );
			$name    = 'core/' . substr( $tail, 0, $nameEnd );
			$attrsTr = trim( substr( $tail, $nameEnd ) );

			$attrs = [];

			if ( '' !== $attrsTr ) {
				$decoded = json_decode( $attrsTr, true );
				$attrs   = is_array( $decoded ) ? $decoded : [];
			}

			$tokens[] = [
				'kind'       => $selfClose ? 'selfclose' : 'open',
				'name'       => $name,
				'attributes' => $attrs,
			];
		}

		return $tokens;
	}

	/**
	 * Recursive tree builder. Consumes tokens until it hits a `close`
	 * (which the caller is responsible for skipping) or runs out.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, array<string, mixed>>  $tokens
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function consumeChildren( array $tokens, int &$index ): array
	{
		$children = [];

		while ( $index < count( $tokens ) ) {
			$token = $tokens[ $index ];

			if ( 'close' === $token['kind'] ) {
				return $children;
			}

			$name = (string) $token['name'];

			if ( self::NAV_LINK !== $name && self::NAV_SUBMENU !== $name ) {
				// Skip unknown blocks — and their close tag if any —
				// rather than letting them break the structural pair.
				$index++;

				if ( 'open' === $token['kind'] ) {
					$this->consumeChildren( $tokens, $index );
					$index++;
				}

				continue;
			}

			if ( 'selfclose' === $token['kind'] ) {
				$children[] = [
					'name'        => $name,
					'attributes'  => $token['attributes'] ?? [],
					'innerBlocks' => [],
				];
				$index++;

				continue;
			}

			// `open` — recurse for inner blocks, then skip the
			// matching `close`.
			$index++;
			$inner = $this->consumeChildren( $tokens, $index );
			$index++;

			$children[] = [
				'name'        => $name,
				'attributes'  => $token['attributes'] ?? [],
				'innerBlocks' => $inner,
			];
		}

		return $children;
	}

	/**
	 * Serialize a single navigation block into the WP-comment shape.
	 * Drops unknown block names so a malformed input doesn't slip an
	 * unsupported block type through the serializer.
	 *
	 * @since 1.1.0
	 *
	 * @param  mixed  $block
	 */
	protected function serializeBlock( mixed $block ): string
	{
		if ( ! is_array( $block ) ) {
			return '';
		}

		$name = $block['name'] ?? null;

		if ( self::NAV_LINK !== $name && self::NAV_SUBMENU !== $name ) {
			return '';
		}

		// Strip the `core/` prefix to match WP's serialize_block output.
		// `core/navigation-link` → `navigation-link`.
		$shortName = substr( (string) $name, strlen( 'core/' ) );

		$attributes = is_array( $block['attributes'] ?? null ) ? $block['attributes'] : [];
		$json       = [] === $attributes
			? ''
			: ' ' . json_encode( $attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		$innerBlocks = is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : [];

		// Leaf blocks (no children) use the self-closing `/-->` form.
		// Submenus open / close around their serialized children.
		if ( [] === $innerBlocks ) {
			return sprintf( '<!-- wp:%s%s /-->', $shortName, $json );
		}

		$inner = $this->blocksToRaw( $innerBlocks );

		return sprintf(
			"<!-- wp:%s%s -->\n%s\n<!-- /wp:%s -->",
			$shortName,
			$json,
			$inner,
			$shortName
		);
	}

	/**
	 * Write path — parse a `core/navigation-*` block tree into a nested
	 * list of row-attribute specs the controller inserts depth-first.
	 *
	 * Each spec is `{ attributes: array<string,mixed>, children: array }`.
	 * `parent_id` / `position` are deliberately absent — they're
	 * relational and assigned at insert time.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, mixed>  $blocks
	 *
	 * @return array<int, array{attributes: array<string, mixed>, children: array<int, mixed>}>
	 */
	public function blocksToItemSpecs( array $blocks ): array
	{
		$specs = [];

		foreach ( $blocks as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$name = $raw['name'] ?? null;

			// Drop blocks we don't translate — matches `menu-tree.ts`:
			// the native editor has no UI for them, and preserving
			// opaque rows the user can't act on is worse than dropping.
			if ( self::NAV_LINK !== $name && self::NAV_SUBMENU !== $name ) {
				continue;
			}

			$attributes = is_array( $raw['attributes'] ?? null ) ? $raw['attributes'] : [];
			$innerBlocks = is_array( $raw['innerBlocks'] ?? null ) ? $raw['innerBlocks'] : [];

			$children = $this->blocksToItemSpecs( $innerBlocks );

			$specs[] = [
				'attributes' => $this->blockAttributesToRow( $attributes, [] !== $children ),
				'children'   => $children,
			];
		}

		return $specs;
	}

	/**
	 * Recursive nesting helper for {@see itemsToBlocks()}.
	 *
	 * `$visited` tracks the parent ids already on the current ancestor
	 * path. A corrupt `parent_id` chain — reachable via the
	 * `/menu-items` endpoint, which accepts an arbitrary `parent_id` —
	 * would otherwise recurse forever; once a parent id repeats, its
	 * branch is treated as empty. A non-positive `id` (0 / null) is
	 * never recursed into for the same reason: `parent_id = 0` is the
	 * root bucket key, so an item with `id = 0` would re-walk the root.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<int, object>>  $byParent
	 * @param  array<int, true>  $visited
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function buildBlockBranch( array $byParent, int $parentId, array $visited ): array
	{
		if ( isset( $visited[ $parentId ] ) ) {
			return [];
		}

		$visited[ $parentId ] = true;

		$blocks = [];

		foreach ( $byParent[ $parentId ] ?? [] as $item ) {
			$itemId = (int) ( $item->id ?? 0 );

			$children = $itemId > 0
				? $this->buildBlockBranch( $byParent, $itemId, $visited )
				: [];

			$blocks[] = [
				// Children-driven, matching `menu-tree.ts` — a childless
				// `submenu`-typed row round-trips as a link.
				'name'        => [] !== $children ? self::NAV_SUBMENU : self::NAV_LINK,
				'attributes'  => $this->rowToBlockAttributes( $item ),
				'innerBlocks' => $children,
			];
		}

		return $blocks;
	}

	/**
	 * Map a `MenuItem` row onto a navigation block's `attributes`. Only
	 * non-empty values are emitted so the wire shape stays minimal and
	 * round-trips cleanly against `menu-tree.ts`'s reader.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function rowToBlockAttributes( object $item ): array
	{
		$attributes = [];

		if ( null !== $item->kind && '' !== (string) $item->kind ) {
			$attributes['kind'] = (string) $item->kind;
		}

		if ( null !== $item->object_type && '' !== (string) $item->object_type ) {
			$attributes['type'] = (string) $item->object_type;
		}

		if ( null !== $item->object_id ) {
			$attributes['id'] = (int) $item->object_id;
		}

		$attributes['label'] = (string) ( $item->label ?? '' );

		if ( null !== $item->url && '' !== (string) $item->url ) {
			$attributes['url'] = (string) $item->url;
		}

		if ( '_blank' === $item->target ) {
			$attributes['opensInNewTab'] = true;
		}

		if ( null !== $item->classes && '' !== (string) $item->classes ) {
			$attributes['className'] = (string) $item->classes;
		}

		if ( null !== $item->rel && '' !== (string) $item->rel ) {
			$attributes['rel'] = (string) $item->rel;
		}

		return $attributes;
	}

	/**
	 * Map a navigation block's `attributes` onto `MenuItem` column
	 * values. `type` is derived from whether the block carries children
	 * (children-driven, matching `menu-tree.ts`).
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array<string, mixed>
	 */
	protected function blockAttributesToRow( array $attributes, bool $hasChildren ): array
	{
		return [
			'type'        => $hasChildren ? 'submenu' : 'link',
			'label'       => (string) ( $attributes['label'] ?? '' ),
			'url'         => $this->nullableString( $attributes['url'] ?? null ),
			'target'      => ( true === ( $attributes['opensInNewTab'] ?? false ) ) ? '_blank' : '_self',
			'rel'         => $this->nullableString( $attributes['rel'] ?? null ),
			'classes'     => $this->nullableString( $attributes['className'] ?? null ),
			'kind'        => $this->nullableString( $attributes['kind'] ?? null ),
			'object_type' => $this->nullableString( $attributes['type'] ?? null ),
			'object_id'   => $this->nullableId( $attributes['id'] ?? null ),
		];
	}

	/**
	 * Coerce a wire value to a non-empty string or null.
	 *
	 * @since 1.0.0
	 */
	protected function nullableString( mixed $value ): ?string
	{
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		return $value;
	}

	/**
	 * Coerce a wire value to a positive integer object id or null.
	 * `menu-tree.ts` can emit `attributes.id` as a number or a string;
	 * `menu_items.object_id` is an unsigned bigint, so only a numeric,
	 * positive value maps — anything else (a slug, 0, garbage) is null.
	 *
	 * @since 1.0.0
	 */
	protected function nullableId( mixed $value ): ?int
	{
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : null;
		}

		if ( is_string( $value ) && ctype_digit( $value ) ) {
			$id = (int) $value;

			return $id > 0 ? $id : null;
		}

		return null;
	}
}
