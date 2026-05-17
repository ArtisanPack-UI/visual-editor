<?php

/**
 * Unit tests for {@see MenuItemBlockBridge} — the PHP mirror of
 * `menu-tree.ts`. Pure converter, no DB: `itemsToBlocks()` is fed
 * plain row-shaped objects, `blocksToItemSpecs()` plain arrays.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\SiteEditor\MenuItemBlockBridge;

/**
 * Build a `menu_items`-row-shaped object for the read-path tests.
 *
 * @param  array<string, mixed>  $overrides
 */
function menuItemRow( array $overrides = [] ): object
{
	return (object) array_merge( [
		'id'          => 1,
		'parent_id'   => null,
		'position'    => 0,
		'type'        => 'link',
		'label'       => 'Item',
		'url'         => null,
		'target'      => '_self',
		'rel'         => null,
		'classes'     => null,
		'kind'        => null,
		'object_type' => null,
		'object_id'   => null,
	], $overrides );
}

describe( 'itemsToBlocks() — read path', function (): void {
	it( 'emits a childless row as core/navigation-link', function (): void {
		$blocks = ( new MenuItemBlockBridge() )->itemsToBlocks( [
			menuItemRow( [ 'id' => 1, 'label' => 'Home', 'url' => '/' ] ),
		] );

		expect( $blocks )->toHaveCount( 1 )
			->and( $blocks[0]['name'] )->toBe( 'core/navigation-link' )
			->and( $blocks[0]['attributes']['label'] )->toBe( 'Home' )
			->and( $blocks[0]['attributes']['url'] )->toBe( '/' )
			->and( $blocks[0]['innerBlocks'] )->toBe( [] );
	} );

	it( 'emits a row with children as core/navigation-submenu, nesting recursively', function (): void {
		$blocks = ( new MenuItemBlockBridge() )->itemsToBlocks( [
			menuItemRow( [ 'id' => 1, 'parent_id' => null, 'label' => 'About' ] ),
			menuItemRow( [ 'id' => 2, 'parent_id' => 1, 'label' => 'Team' ] ),
		] );

		expect( $blocks )->toHaveCount( 1 )
			->and( $blocks[0]['name'] )->toBe( 'core/navigation-submenu' )
			->and( $blocks[0]['attributes']['label'] )->toBe( 'About' )
			->and( $blocks[0]['innerBlocks'] )->toHaveCount( 1 )
			->and( $blocks[0]['innerBlocks'][0]['name'] )->toBe( 'core/navigation-link' )
			->and( $blocks[0]['innerBlocks'][0]['attributes']['label'] )->toBe( 'Team' );
	} );

	it( 'is children-driven — a childless submenu-typed row still emits a link', function (): void {
		// Matches `menu-tree.ts`, which collapses childless submenus to
		// links so the round-trip is stable.
		$blocks = ( new MenuItemBlockBridge() )->itemsToBlocks( [
			menuItemRow( [ 'id' => 1, 'type' => 'submenu', 'label' => 'Empty' ] ),
		] );

		expect( $blocks[0]['name'] )->toBe( 'core/navigation-link' );
	} );

	it( 'maps the typed-reference + target columns onto block attributes', function (): void {
		$blocks = ( new MenuItemBlockBridge() )->itemsToBlocks( [
			menuItemRow( [
				'id'          => 1,
				'label'       => 'Blog',
				'url'         => '/blog',
				'target'      => '_blank',
				'rel'         => 'noopener',
				'classes'     => 'is-cta',
				'kind'        => 'post-type',
				'object_type' => 'page',
				'object_id'   => 42,
			] ),
		] );

		$attrs = $blocks[0]['attributes'];
		expect( $attrs['kind'] )->toBe( 'post-type' )
			->and( $attrs['type'] )->toBe( 'page' )
			->and( $attrs['id'] )->toBe( 42 )
			->and( $attrs['opensInNewTab'] )->toBeTrue()
			->and( $attrs['rel'] )->toBe( 'noopener' )
			->and( $attrs['className'] )->toBe( 'is-cta' );
	} );

	it( 'omits empty optional attributes — only label is always present', function (): void {
		$blocks = ( new MenuItemBlockBridge() )->itemsToBlocks( [
			menuItemRow( [ 'id' => 1, 'label' => 'Bare', 'target' => '_self' ] ),
		] );

		expect( $blocks[0]['attributes'] )->toBe( [ 'label' => 'Bare' ] );
	} );

	it( 'normalizes sibling order by (position, id) regardless of input order', function (): void {
		// The bridge no longer depends on the caller pre-sorting — it
		// orders each parent bucket itself. Feed the rows shuffled.
		$blocks = ( new MenuItemBlockBridge() )->itemsToBlocks( [
			menuItemRow( [ 'id' => 9, 'position' => 2, 'label' => 'Third' ] ),
			menuItemRow( [ 'id' => 3, 'position' => 0, 'label' => 'First' ] ),
			menuItemRow( [ 'id' => 7, 'position' => 1, 'label' => 'Second' ] ),
		] );

		expect( array_column( array_column( $blocks, 'attributes' ), 'label' ) )
			->toBe( [ 'First', 'Second', 'Third' ] );
	} );

	it( 'does not infinite-loop on a cyclic parent_id chain', function (): void {
		// Corrupt data — reachable via the /menu-items endpoint, which
		// accepts an arbitrary parent_id. Item 1's parent is 2 and
		// item 2's parent is 1. The cycle guard must bottom out.
		$blocks = ( new MenuItemBlockBridge() )->itemsToBlocks( [
			menuItemRow( [ 'id' => 1, 'parent_id' => 2, 'label' => 'A' ] ),
			menuItemRow( [ 'id' => 2, 'parent_id' => 1, 'label' => 'B' ] ),
		] );

		// Neither item is reachable from the root (both have a non-zero
		// parent), so the projection is simply empty — and crucially,
		// it returns rather than recursing forever.
		expect( $blocks )->toBe( [] );
	} );
} );

describe( 'blocksToItemSpecs() — write path', function (): void {
	it( 'parses a link block into a row spec', function (): void {
		$specs = ( new MenuItemBlockBridge() )->blocksToItemSpecs( [
			[
				'name'        => 'core/navigation-link',
				'attributes'  => [ 'label' => 'Home', 'url' => '/' ],
				'innerBlocks' => [],
			],
		] );

		expect( $specs )->toHaveCount( 1 )
			->and( $specs[0]['attributes']['type'] )->toBe( 'link' )
			->and( $specs[0]['attributes']['label'] )->toBe( 'Home' )
			->and( $specs[0]['attributes']['url'] )->toBe( '/' )
			->and( $specs[0]['attributes']['target'] )->toBe( '_self' )
			->and( $specs[0]['children'] )->toBe( [] );
	} );

	it( 'parses a submenu block, marking type=submenu and nesting children', function (): void {
		$specs = ( new MenuItemBlockBridge() )->blocksToItemSpecs( [
			[
				'name'        => 'core/navigation-submenu',
				'attributes'  => [ 'label' => 'About' ],
				'innerBlocks' => [
					[ 'name' => 'core/navigation-link', 'attributes' => [ 'label' => 'Team' ], 'innerBlocks' => [] ],
				],
			],
		] );

		expect( $specs[0]['attributes']['type'] )->toBe( 'submenu' )
			->and( $specs[0]['children'] )->toHaveCount( 1 )
			->and( $specs[0]['children'][0]['attributes']['type'] )->toBe( 'link' )
			->and( $specs[0]['children'][0]['attributes']['label'] )->toBe( 'Team' );
	} );

	it( 'drops blocks it does not translate', function (): void {
		// Matches `menu-tree.ts` — the native tree editor has no UI for
		// block types it doesn't understand.
		$specs = ( new MenuItemBlockBridge() )->blocksToItemSpecs( [
			[ 'name' => 'core/paragraph', 'attributes' => [ 'content' => 'nope' ], 'innerBlocks' => [] ],
			[ 'name' => 'core/navigation-link', 'attributes' => [ 'label' => 'Kept' ], 'innerBlocks' => [] ],
			'not-an-array',
		] );

		expect( $specs )->toHaveCount( 1 )
			->and( $specs[0]['attributes']['label'] )->toBe( 'Kept' );
	} );

	it( 'maps opensInNewTab + typed-reference attributes onto columns', function (): void {
		$specs = ( new MenuItemBlockBridge() )->blocksToItemSpecs( [
			[
				'name'        => 'core/navigation-link',
				'attributes'  => [
					'label'         => 'Blog',
					'kind'          => 'post-type',
					'type'          => 'page',
					'id'            => 42,
					'opensInNewTab' => true,
					'className'     => 'is-cta',
				],
				'innerBlocks' => [],
			],
		] );

		$attrs = $specs[0]['attributes'];
		expect( $attrs['target'] )->toBe( '_blank' )
			->and( $attrs['kind'] )->toBe( 'post-type' )
			->and( $attrs['object_type'] )->toBe( 'page' )
			->and( $attrs['object_id'] )->toBe( 42 )
			->and( $attrs['classes'] )->toBe( 'is-cta' );
	} );

	it( 'normalizes a non-numeric attributes.id to a null object_id', function (): void {
		// `menu-tree.ts` can emit `id` as a slug string; `object_id` is
		// an unsigned bigint, so only a numeric positive value maps.
		$specs = ( new MenuItemBlockBridge() )->blocksToItemSpecs( [
			[ 'name' => 'core/navigation-link', 'attributes' => [ 'label' => 'X', 'id' => 'a-slug' ], 'innerBlocks' => [] ],
		] );

		expect( $specs[0]['attributes']['object_id'] )->toBeNull();
	} );
} );

describe( 'MenuItemBlockBridge::blocksToRaw (Keystone #48)', function (): void {
	it( 'serializes a leaf nav-link as a self-closing block comment', function (): void {
		$raw = ( new MenuItemBlockBridge() )->blocksToRaw( [
			[
				'name'        => 'core/navigation-link',
				'attributes'  => [ 'label' => 'Home', 'url' => '/' ],
				'innerBlocks' => [],
			],
		] );

		// Strip `core/` prefix, JSON-encode attrs, self-close.
		expect( $raw )->toBe( '<!-- wp:navigation-link {"label":"Home","url":"/"} /-->' );
	} );

	it( 'omits the attrs JSON when the block has no attributes', function (): void {
		$raw = ( new MenuItemBlockBridge() )->blocksToRaw( [
			[ 'name' => 'core/navigation-link', 'attributes' => [], 'innerBlocks' => [] ],
		] );

		expect( $raw )->toBe( '<!-- wp:navigation-link /-->' );
	} );

	it( 'opens / closes a submenu around its serialized children', function (): void {
		$raw = ( new MenuItemBlockBridge() )->blocksToRaw( [
			[
				'name'        => 'core/navigation-submenu',
				'attributes'  => [ 'label' => 'About' ],
				'innerBlocks' => [
					[ 'name' => 'core/navigation-link', 'attributes' => [ 'label' => 'Team' ], 'innerBlocks' => [] ],
				],
			],
		] );

		expect( $raw )->toBe(
			"<!-- wp:navigation-submenu {\"label\":\"About\"} -->\n"
				. "<!-- wp:navigation-link {\"label\":\"Team\"} /-->\n"
				. '<!-- /wp:navigation-submenu -->'
		);
	} );

	it( 'serializes siblings on separate lines so the parse round-trip stays stable', function (): void {
		$raw = ( new MenuItemBlockBridge() )->blocksToRaw( [
			[ 'name' => 'core/navigation-link', 'attributes' => [ 'label' => 'A' ], 'innerBlocks' => [] ],
			[ 'name' => 'core/navigation-link', 'attributes' => [ 'label' => 'B' ], 'innerBlocks' => [] ],
		] );

		expect( $raw )->toBe(
			"<!-- wp:navigation-link {\"label\":\"A\"} /-->\n<!-- wp:navigation-link {\"label\":\"B\"} /-->"
		);
	} );

	it( 'drops unknown block types so a malformed input cannot leak into content.raw', function (): void {
		$raw = ( new MenuItemBlockBridge() )->blocksToRaw( [
			[ 'name' => 'core/paragraph', 'attributes' => [ 'content' => 'nope' ], 'innerBlocks' => [] ],
			[ 'name' => 'core/navigation-link', 'attributes' => [ 'label' => 'Real' ], 'innerBlocks' => [] ],
		] );

		expect( $raw )->toBe( '<!-- wp:navigation-link {"label":"Real"} /-->' );
	} );

	it( 'returns an empty string for an empty tree', function (): void {
		expect( ( new MenuItemBlockBridge() )->blocksToRaw( [] ) )->toBe( '' );
	} );

	it( 'preserves unicode and forward slashes in the JSON attrs', function (): void {
		$raw = ( new MenuItemBlockBridge() )->blocksToRaw( [
			[
				'name'        => 'core/navigation-link',
				'attributes'  => [ 'label' => 'Café', 'url' => 'https://example.com/path' ],
				'innerBlocks' => [],
			],
		] );

		// `JSON_UNESCAPED_UNICODE` keeps the `é`, `JSON_UNESCAPED_SLASHES`
		// keeps `/` readable in the URL — matches WP core's serializer.
		expect( $raw )->toContain( 'Café' );
		expect( $raw )->toContain( 'https://example.com/path' );
		expect( $raw )->not->toContain( 'https:\/\/example.com\/path' );
	} );
} );

describe( 'MenuItemBlockBridge::rawToBlocks (Keystone #48)', function (): void {
	it( 'parses a self-closing nav-link with attrs', function (): void {
		$blocks = ( new MenuItemBlockBridge() )->rawToBlocks(
			'<!-- wp:navigation-link {"label":"Home","url":"/"} /-->'
		);

		expect( $blocks )->toBe( [
			[
				'name'        => 'core/navigation-link',
				'attributes'  => [ 'label' => 'Home', 'url' => '/' ],
				'innerBlocks' => [],
			],
		] );
	} );

	it( 'parses a self-closing nav-link without attrs', function (): void {
		$blocks = ( new MenuItemBlockBridge() )->rawToBlocks( '<!-- wp:navigation-link /-->' );

		expect( $blocks )->toBe( [
			[
				'name'        => 'core/navigation-link',
				'attributes'  => [],
				'innerBlocks' => [],
			],
		] );
	} );

	it( 'parses a submenu with nested children', function (): void {
		$blocks = ( new MenuItemBlockBridge() )->rawToBlocks(
			"<!-- wp:navigation-submenu {\"label\":\"About\"} -->\n"
				. "<!-- wp:navigation-link {\"label\":\"Team\"} /-->\n"
				. '<!-- /wp:navigation-submenu -->'
		);

		expect( $blocks )->toBe( [
			[
				'name'        => 'core/navigation-submenu',
				'attributes'  => [ 'label' => 'About' ],
				'innerBlocks' => [
					[
						'name'        => 'core/navigation-link',
						'attributes'  => [ 'label' => 'Team' ],
						'innerBlocks' => [],
					],
				],
			],
		] );
	} );

	it( 'parses multiple siblings on separate lines', function (): void {
		$blocks = ( new MenuItemBlockBridge() )->rawToBlocks(
			"<!-- wp:navigation-link {\"label\":\"A\"} /-->\n<!-- wp:navigation-link {\"label\":\"B\"} /-->"
		);

		expect( $blocks )->toHaveCount( 2 );
		expect( $blocks[0]['attributes'] )->toBe( [ 'label' => 'A' ] );
		expect( $blocks[1]['attributes'] )->toBe( [ 'label' => 'B' ] );
	} );

	it( 'drops unknown wp:* blocks at the top level so paragraph leakage cannot smuggle in', function (): void {
		$blocks = ( new MenuItemBlockBridge() )->rawToBlocks(
			"<!-- wp:paragraph {\"content\":\"nope\"} /-->\n<!-- wp:navigation-link {\"label\":\"Real\"} /-->"
		);

		expect( $blocks )->toHaveCount( 1 );
		expect( $blocks[0]['attributes'] )->toBe( [ 'label' => 'Real' ] );
	} );

	it( 'returns an empty array for an empty / whitespace-only string', function (): void {
		$bridge = new MenuItemBlockBridge();

		expect( $bridge->rawToBlocks( '' ) )->toBe( [] );
		expect( $bridge->rawToBlocks( "   \n  \t" ) )->toBe( [] );
	} );

	it( 'tolerates a missing close tag without infinite-looping', function (): void {
		// `wp:navigation-submenu` without `/wp:navigation-submenu` —
		// recursive descent should consume to EOF and return what it
		// has rather than spinning. Pathological input from a
		// malformed editor save shouldn't crash the controller.
		$blocks = ( new MenuItemBlockBridge() )->rawToBlocks(
			"<!-- wp:navigation-submenu {\"label\":\"Orphan\"} -->\n"
				. '<!-- wp:navigation-link {"label":"Inside"} /-->'
		);

		expect( $blocks )->toHaveCount( 1 );
		expect( $blocks[0]['name'] )->toBe( 'core/navigation-submenu' );
		expect( $blocks[0]['innerBlocks'][0]['attributes'] )->toBe( [ 'label' => 'Inside' ] );
	} );

	it( 'round-trips blocksToRaw → rawToBlocks → blocksToRaw without losing data', function (): void {
		$tree = [
			[
				'name'        => 'core/navigation-submenu',
				'attributes'  => [ 'label' => 'About', 'url' => '#' ],
				'innerBlocks' => [
					[ 'name' => 'core/navigation-link', 'attributes' => [ 'label' => 'Team', 'url' => '/team' ], 'innerBlocks' => [] ],
					[ 'name' => 'core/navigation-link', 'attributes' => [ 'label' => 'Story' ], 'innerBlocks' => [] ],
				],
			],
			[ 'name' => 'core/navigation-link', 'attributes' => [ 'label' => 'Home', 'url' => '/' ], 'innerBlocks' => [] ],
		];

		$bridge = new MenuItemBlockBridge();

		expect( $bridge->rawToBlocks( $bridge->blocksToRaw( $tree ) ) )->toBe( $tree );
	} );

	it( 'matches close tags by block name so an unknown nested block does not eat the parent close', function (): void {
		// An unsupported `wp:group` opens *inside* a submenu. The
		// previous "return on any close" path treated that group's
		// close as the submenu's close, dropping the sibling link
		// that followed at the top level. With name-matched close
		// handling the submenu keeps its real boundaries and the
		// sibling stays a top-level child.
		$blocks = ( new MenuItemBlockBridge() )->rawToBlocks(
			"<!-- wp:navigation-submenu {\"label\":\"Parent\"} -->\n"
				. "<!-- wp:group -->\n"
				. "<!-- /wp:group -->\n"
				. "<!-- wp:navigation-link {\"label\":\"Inside\"} /-->\n"
				. "<!-- /wp:navigation-submenu -->\n"
				. '<!-- wp:navigation-link {"label":"Sibling"} /-->'
		);

		expect( $blocks )->toHaveCount( 2 );
		expect( $blocks[0]['name'] )->toBe( 'core/navigation-submenu' );
		expect( $blocks[0]['innerBlocks'] )->toHaveCount( 1 );
		expect( $blocks[0]['innerBlocks'][0]['attributes'] )->toBe( [ 'label' => 'Inside' ] );
		expect( $blocks[1]['attributes'] )->toBe( [ 'label' => 'Sibling' ] );
	} );
} );

describe( 'MenuItemBlockBridge::blocksToRaw — WP-compatible attribute escaping (Keystone #48)', function (): void {
	it( 'escapes `-->`, `<`, `>`, `&`, backslash, and escaped-double-quote inside JSON attrs', function (): void {
		// Bare `json_encode` lets these characters through literally,
		// and `-->` in particular closes the surrounding HTML comment
		// early — Gutenberg's parser then either rejects the markup
		// or leaks attr tokens into rendered HTML. Mirror upstream
		// `serialize_block_attributes()` by post-encoding the unsafe
		// sequences as their JSON `\uXXXX` escapes.
		$raw = ( new MenuItemBlockBridge() )->blocksToRaw( [
			[
				'name'        => 'core/navigation-link',
				'attributes'  => [
					'label' => 'A & B --> </span>',
					'url'   => '\\path\\to',
					// A literal `"` would already be JSON-escaped to
					// `\"`; the escape sequence is what serializer
					// rewrites to `"` so the closing quote of
					// the attr string can't be mistaken.
					'quote' => 'has "quote" inside',
				],
				'innerBlocks' => [],
			],
		] );

		// Comment delimiters must remain intact — none of the unsafe
		// sequences can show up literally between `<!--` and `-->`.
		expect( $raw )->toStartWith( '<!-- wp:navigation-link ' );
		expect( $raw )->toEndWith( ' /-->' );

		// Strip the surrounding `<!-- wp:navigation-link ` / ` /-->`.
		$json = substr( $raw, strlen( '<!-- wp:navigation-link ' ) );
		$json = substr( $json, 0, -strlen( ' /-->' ) );

		// Unsafe characters are gone — replaced by `\uXXXX` escapes.
		expect( $json )->not->toContain( '-->' );
		expect( $json )->not->toContain( '<' );
		expect( $json )->not->toContain( '>' );
		expect( $json )->not->toContain( '&' );
		expect( $json )->toContain( '\\u002d\\u002d' );
		expect( $json )->toContain( '\\u003c' );
		expect( $json )->toContain( '\\u003e' );
		expect( $json )->toContain( '\\u0026' );

		// And the escaped form still decodes back to the original
		// value — `json_decode` resolves `\uXXXX` natively, so the
		// round-trip through `rawToBlocks` returns the exact input.
		$reparsed = ( new MenuItemBlockBridge() )->rawToBlocks( $raw );
		expect( $reparsed[0]['attributes']['label'] )->toBe( 'A & B --> </span>' );
		expect( $reparsed[0]['attributes']['url'] )->toBe( '\\path\\to' );
		expect( $reparsed[0]['attributes']['quote'] )->toBe( 'has "quote" inside' );
	} );
} );
