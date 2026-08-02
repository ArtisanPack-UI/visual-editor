<?php

/**
 * WP block markup → renderable block tree.
 *
 * The public, supported server-side path from a raw block-markup string
 * (a block theme's `templates/*.html`, `parts/*.html`, a `.php`
 * pattern, a persisted `post_content`) to the editor-shape tree
 * {@see \ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer::render()}
 * consumes.
 *
 * Two shapes are in play and, before #688, nothing bridged them:
 *
 * | producer                        | shape                                            | text lives in         |
 * |---------------------------------|--------------------------------------------------|-----------------------|
 * | cms-framework `BlockMarkupParser` | `{blockName, attrs, innerBlocks, innerHTML, …}` | `innerHTML`           |
 * | the editor / `BlockRenderer`     | `{name, attributes, innerBlocks}`                 | `attributes.content`  |
 *
 * A key-rename alone is not enough: Gutenberg persists most block text
 * in the SAVED HTML, not in the delimiter's JSON. Across the
 * `artisanpack-ui` theme, zero of its 130 paragraph/heading blocks
 * serialize a `content` attribute — renaming keys produces a
 * structurally-correct, completely textless page. So this hydrator also
 * runs each registered block type's `block.json` attribute definitions
 * back over the saved HTML via {@see BlockAttributeSourceResolver},
 * recovering paragraph/heading `content`, button text + href, image
 * `src`/`alt`/`caption`, list values, quote + citation, table cells,
 * and everything else declared with a `source`.
 *
 * The recovery is driven entirely by the registry rather than by a
 * hand-maintained per-block table, so a block that ships a new sourced
 * attribute is picked up with no change here — which is the whole
 * reason this lives beside the block manifests and partials rather than
 * in cms-framework or a host app.
 *
 * Blocks with no registered definition pass through untouched: their
 * delimiter attributes and inner blocks survive, nothing is recovered.
 *
 * ## Trust boundary
 *
 * Recovered `rich-text` / `html` attributes are HTML FRAGMENTS, and the
 * block partials emit them unescaped (`{!! $content !!}`) — that is what
 * makes a paragraph's `<strong>` survive the round trip. The hydrator is
 * therefore only safe over markup you already trust to render: theme
 * files on disk, patterns, and editor-authored content that passed
 * through the same authorization the post editor enforces. It is NOT a
 * sanitizer, and passing visitor-submitted markup to {@see hydrate()}
 * turns it into stored XSS. This is the same trust boundary Gutenberg
 * itself draws around block markup — the hydrator neither widens nor
 * narrows it. Hosts that must render untrusted markup should run it
 * through their own sanitizer (e.g. `kses()` from
 * `artisanpack-ui/security`) BEFORE hydrating.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.5.5
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

use ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry;
use RuntimeException;

class BlockMarkupHydrator
{
	/**
	 * cms-framework's markup parser. Resolved by name so visual-editor
	 * stays installable without cms-framework — {@see hydrate()} then
	 * degrades to an empty tree while {@see hydrateTree()}, which takes
	 * an already-parsed tree, keeps working.
	 */
	public const PARSER_CLASS = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Support\\BlockMarkupParser';

	/**
	 * Hard cap on `innerBlocks` recursion. Markup coming through
	 * {@see hydrate()} is already bounded by the parser's own depth cap,
	 * but {@see hydrateTree()} is public and accepts a caller-supplied
	 * array — a hand-built or imported payload nested thousands deep
	 * would otherwise blow the PHP stack before the tree ever reaches
	 * the renderer's own {@see \ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer::MAX_INNER_DEPTH}
	 * guard. Sits above the parser's 64 so hydration never truncates a
	 * tree the parser was willing to produce.
	 *
	 * @since 1.5.5
	 */
	public const MAX_DEPTH = 128;

	/**
	 * The DOM matcher that recovers save-shape attributes. Constructed
	 * by default so the hydrator can be `new`-ed with just a registry.
	 *
	 * @since 1.5.5
	 */
	protected BlockAttributeSourceResolver $sources;

	public function __construct(
		protected BlockTypeRegistry $blockTypes,
		?BlockAttributeSourceResolver $sources = null,
	) {
		$this->sources = $sources ?? new BlockAttributeSourceResolver();
	}

	/**
	 * Whether raw-markup hydration is available in this install — i.e.
	 * whether cms-framework's parser is on the classpath. Hosts that
	 * want to fail loudly instead of rendering an empty template can
	 * gate on this.
	 *
	 * @since 1.5.5
	 */
	public static function canParseMarkup(): bool
	{
		return class_exists( self::PARSER_CLASS );
	}

	/**
	 * Turn raw WP block markup into a renderable editor-shape tree.
	 *
	 * Returns an empty tree when the markup is blank or cms-framework's
	 * parser is unavailable.
	 *
	 * @since 1.5.5
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function hydrate( string $markup ): array
	{
		if ( '' === trim( $markup ) || ! self::canParseMarkup() ) {
			return [];
		}

		/** @var class-string $parser */
		$parser = self::PARSER_CLASS;

		return $this->hydrateTree( $parser::parse( $markup ) );
	}

	/**
	 * Convert an already-parsed `parse_blocks()`-shape tree into the
	 * editor shape, recovering sourced attributes as it goes.
	 *
	 * Accepts the editor shape too — a tree that already uses
	 * `{name, attributes}` round-trips, gaining only whatever sourced
	 * attributes its `innerHTML` still carries. That makes the method
	 * safe to run over a mixed tree without sniffing shape first.
	 *
	 * Freeform siblings (`blockName === null`) are dropped: the renderer
	 * has no partial for them and they would surface as
	 * `data-ve-unknown-block` wrappers. Matches what the site editor's
	 * `TemplateAdapter` already does with theme-file content.
	 *
	 * @since 1.5.5
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function hydrateTree( array $tree, int $depth = 0 ): array
	{
		if ( $depth >= self::MAX_DEPTH ) {
			report( new RuntimeException( sprintf(
				'BlockMarkupHydrator depth cap (%d) exceeded — dropping the remaining subtree. Likely a malformed or attacker-crafted block payload.',
				self::MAX_DEPTH,
			) ) );

			return [];
		}

		$out = [];

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$hydrated = $this->hydrateBlock( $block, $depth );

			if ( null !== $hydrated ) {
				$out[] = $hydrated;
			}
		}

		return $out;
	}

	/**
	 * @since 1.5.5
	 *
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>|null Null for blocks that carry no usable name (freeform siblings).
	 */
	protected function hydrateBlock( array $block, int $depth = 0 ): ?array
	{
		$name = $block['blockName'] ?? $block['name'] ?? null;

		if ( ! is_string( $name ) || '' === trim( $name ) ) {
			return null;
		}

		$name = trim( $name );

		[ , $attributes ] = BlockShape::readAttrs( $block );

		$innerHtml = isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ? $block['innerHTML'] : '';
		$recovered = $this->recoverAttributes( $name, $innerHtml );

		$innerBlocks = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] )
			? $this->hydrateTree( $block['innerBlocks'], $depth + 1 )
			: [];

		$hydrated = [
			'name' => $name,
			// Delimiter attributes win. Gutenberg lets the sourced value
			// win outright, but a caller may hand us a tree whose text
			// already lives in `attributes` (an editor-persisted tree, a
			// dynamic block's seeded content) alongside a stale or empty
			// `innerHTML`. Layering recovery UNDERNEATH means this
			// method can never blank a value that was already there —
			// it only fills gaps.
			'attributes'  => array_merge( $recovered, $attributes ),
			'innerBlocks' => $innerBlocks,
		];

		// Carry the saved HTML through rather than dropping it. A block
		// whose content lives ONLY in `innerHTML` and which declares no
		// sourced attributes — `core/html` being the canonical case —
		// would otherwise hydrate to a node with nothing in it at all,
		// silently losing the content. The renderer ignores unknown keys,
		// so this is inert for every block that did recover attributes,
		// and it leaves the bytes available to partials and to callers
		// that reserialize the tree.
		if ( '' !== $innerHtml ) {
			$hydrated['innerHTML'] = $innerHtml;
		}

		return $hydrated;
	}

	/**
	 * Recover a block's sourced attributes from its saved inner HTML.
	 *
	 * Falls back across the `core/` ↔ `artisanpack/` namespace pair:
	 * themes on disk write WP core's names (`<!-- wp:paragraph -->` →
	 * `core/paragraph`) while this package registers the
	 * `artisanpack/*` forks, and the two share their save shape by
	 * construction — the forks exist so both namespaces render
	 * identically.
	 *
	 * @since 1.5.5
	 *
	 * @return array<string, mixed>
	 */
	protected function recoverAttributes( string $name, string $innerHtml ): array
	{
		if ( '' === trim( $innerHtml ) ) {
			return [];
		}

		$definition = $this->blockTypes->get( $name );

		if ( null === $definition ) {
			$alias = $this->aliasFor( $name );

			$definition = null !== $alias ? $this->blockTypes->get( $alias ) : null;
		}

		$attributes = $definition['attributes'] ?? null;

		if ( ! is_array( $attributes ) || [] === $attributes ) {
			return [];
		}

		return $this->sources->recover( $attributes, $innerHtml );
	}

	/**
	 * Map `core/x` ↔ `artisanpack/x`, or null when the name is in
	 * neither namespace.
	 *
	 * @since 1.5.5
	 */
	protected function aliasFor( string $name ): ?string
	{
		if ( str_starts_with( $name, 'core/' ) ) {
			return 'artisanpack/' . substr( $name, strlen( 'core/' ) );
		}

		if ( str_starts_with( $name, 'artisanpack/' ) ) {
			return 'core/' . substr( $name, strlen( 'artisanpack/' ) );
		}

		return null;
	}
}
