<?php

/**
 * TocResolver — stamps heading anchors and derives table-of-contents
 * entries across a saved block tree.
 *
 * Runs in two passes over the tree:
 *
 * 1. Every `core/heading` / `artisanpack/heading` block that does not
 *    already carry an `anchor` attribute gets one auto-generated from
 *    the heading text. Slugs are unique across the tree — duplicates
 *    receive a `-1`, `-2`, … suffix — so `<a href="#slug">` links land
 *    on exactly one target.
 * 2. Every `artisanpack/toc` block gets `_resolvedItems` stamped from
 *    the same walk, filtered by the block's `minLevel` / `maxLevel`
 *    attributes so the Blade renderer stays declarative.
 *
 * The resolver never touches headings that already have an author-set
 * anchor, and never re-stamps items on TOC blocks that were pre-populated
 * by an upstream pass (a host app may run its own extractor and want the
 * result to win).
 *
 * Callers can pass a binding-resolved copy of the tree as `$contentTree`
 * (#851). Heading text, levels, anchors, and post-content HTML are then
 * read from that copy, so anchors and TOC labels match what the bound
 * blocks render, while the stamps still land on the original tree.
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

use ArtisanPackUI\VisualEditor\Support\BlockShape;
use Normalizer;

class TocResolver
{
	/**
	 * Heading block names this resolver stamps anchors on.
	 *
	 * @var array<int, string>
	 */
	protected const HEADING_BLOCKS = [
		'core/heading',
		'artisanpack/heading',
	];

	/**
	 * Post-content block names whose `_resolvedContent` HTML string this
	 * resolver scans for `<h1>…<h6>` tags. Headings found there are
	 * folded into the TOC just like heading blocks that live in the
	 * template tree — this is what makes an `artisanpack/toc` block
	 * placed inside a single-post template list the actual post body's
	 * headings (#760).
	 *
	 * @var array<int, string>
	 */
	protected const POST_CONTENT_BLOCKS = [
		'core/post-content',
		'artisanpack/post-content',
	];

	/**
	 * Table-of-contents block names this resolver stamps items on.
	 *
	 * @var array<int, string>
	 */
	protected const TOC_BLOCKS = [
		'artisanpack/toc',
	];

	/**
	 * Matches one `<h1>…<h6>` tag: level, attribute string, inner HTML.
	 */
	protected const HEADING_TAG_PATTERN = '#<h([1-6])([^>]*)>(.*?)</h\\1>#is';

	/**
	 * Characters a heading anchor loses before it renders as an `id`.
	 * Mirrors `BlockSupports::resolveAnchor()` in the Blade renderer.
	 *
	 * @since 1.15.0
	 */
	protected const ANCHOR_DISALLOWED = '/[^A-Za-z0-9_:.\-]/';

	/**
	 * Walk the tree twice: once to stamp anchors on headings and collect
	 * the ordered heading list, once to stamp `_resolvedItems` on every
	 * TOC block.
	 *
	 * @since 1.9.0
	 * @since 1.14.0 Accepts an optional `$contentTree` (#851).
	 *
	 * @param  array<int, array<string, mixed>>   $tree         Tree to stamp anchors and TOC items on.
	 * @param  ?array<int, array<string, mixed>>  $contentTree  Structurally identical copy of `$tree`
	 *                                                          with bindings resolved. Heading content
	 *                                                          is read from here when supplied.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function resolveTree( array $tree, ?array $contentTree = null ): array
	{
		$used     = [];
		$headings = [];

		$stamped = $this->stampHeadings( $tree, $used, $headings, $contentTree );

		if ( empty( $headings ) ) {
			// No headings in the tree — TOC blocks still get an empty
			// `_resolvedItems` so their renderer can emit a placeholder
			// message instead of the previous render's stale items.
			return $this->stampTocBlocks( $stamped, [] );
		}

		return $this->stampTocBlocks( $stamped, $headings );
	}

	/**
	 * Whether any heading `content` or post-content `_resolvedContent`
	 * in the tree contains an inline `{{token}}`.
	 *
	 * Callers use this to skip resolving inline tokens on the
	 * `$contentTree` copy when none of the attributes this resolver
	 * reads would change (#858).
	 *
	 * @since 1.14.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 */
	public function hasInlineTokens( array $tree ): bool
	{
		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';

			[ , $attributes ] = BlockShape::readAttrs( $block );

			$key = match ( true ) {
				in_array( $name, self::HEADING_BLOCKS, true )      => 'content',
				in_array( $name, self::POST_CONTENT_BLOCKS, true ) => '_resolvedContent',
				default                                            => null,
			};

			if ( null !== $key && is_string( $attributes[ $key ] ?? null ) && str_contains( $attributes[ $key ], '{{' ) ) {
				return true;
			}

			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) && $this->hasInlineTokens( $block['innerBlocks'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * First pass: stamp anchors on headings and collect their metadata
	 * in document order.
	 *
	 * @since 1.9.0
	 * @since 1.14.0 Accepts an optional `$contentTree` (#851).
	 *
	 * @param  array<int, array<string, mixed>>   $tree
	 * @param  array<string, bool>                $used         Anchor slugs already claimed (by ref).
	 * @param  array<int, array<string, mixed>>   $headings     Collected heading rows (by ref).
	 * @param  ?array<int, array<string, mixed>>  $contentTree  Binding-resolved copy of `$tree`.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function stampHeadings( array $tree, array &$used, array &$headings, ?array $contentTree = null ): array
	{
		$out     = [];
		$sources = null === $contentTree ? [] : array_values( $contentTree );

		foreach ( array_values( $tree ) as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$source = $sources[ $index ] ?? null;

			$out[] = $this->stampHeadingBlock( $block, $used, $headings, is_array( $source ) ? $source : null );
		}

		return $out;
	}

	/**
	 * Stamp a single block (and its inner blocks) with an anchor when the
	 * block is a heading and no author-set anchor is present.
	 *
	 * When `$source` is supplied and is the same block type, its
	 * attributes stand in for the block's own when reading heading
	 * content, level, anchor, and post-content HTML. Stamps are still
	 * written to `$block`.
	 *
	 * @since 1.9.0
	 * @since 1.14.0 Accepts an optional `$source` block (#851).
	 *
	 * @param  array<string, mixed>              $block
	 * @param  array<string, bool>               $used
	 * @param  array<int, array<string, mixed>>  $headings
	 * @param  ?array<string, mixed>             $source    Binding-resolved copy of `$block`.
	 *
	 * @return array<string, mixed>
	 */
	protected function stampHeadingBlock( array $block, array &$used, array &$headings, ?array $source = null ): array
	{
		$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';

		[ $attrKey, $attributes ] = BlockShape::readAttrs( $block );

		// A source of a different type means the copy drifted out of
		// step with the tree; read the block's own attributes instead.
		if ( null !== $source && ( $source['name'] ?? null ) !== $name ) {
			$source = null;
		}

		$sourceAttributes = null !== $source
			? BlockShape::readAttrs( $source )[1]
			: $attributes;

		if ( in_array( $name, self::HEADING_BLOCKS, true ) ) {
			$content  = isset( $sourceAttributes['content'] ) && is_string( $sourceAttributes['content'] ) ? $sourceAttributes['content'] : '';
			$rawLevel = $sourceAttributes['level'] ?? 2;
			$level    = is_numeric( $rawLevel ) ? (int) round( (float) $rawLevel ) : 2;

			if ( $level < 1 ) {
				$level = 1;
			} elseif ( $level > 6 ) {
				$level = 6;
			}

			// The id the heading renders, not the raw value (#888): the
			// renderers strip an anchor to `[A-Za-z0-9_:.-]` (see
			// `BlockSupports::resolveAnchor()`), so `foo bar` renders
			// `foobar`. Claiming and linking the raw value would let a later
			// auto anchor collide with it and point TOC links at a missing
			// id. An anchor that sanitizes to nothing renders no id, so the
			// heading gets an auto anchor instead.
			$existing = isset( $sourceAttributes['anchor'] ) && is_string( $sourceAttributes['anchor'] )
				? (string) preg_replace( self::ANCHOR_DISALLOWED, '', trim( $sourceAttributes['anchor'] ) )
				: '';

			if ( '' !== $existing ) {
				$anchor = $existing;
				// Claim the slug so a later auto-generated anchor does
				// not collide with an author-set one.
				$used[ $anchor ] = true;
			} else {
				$slug = $this->slug( $content );

				if ( '' === $slug ) {
					$anchor = '';
				} else {
					$anchor          = $this->uniqueSlug( $slug, $used );
					$used[ $anchor ] = true;

					$attributes        = array_merge( $attributes, [ 'anchor' => $anchor ] );
					$block[ $attrKey ] = $attributes;
				}
			}

			if ( '' !== $anchor ) {
				$headings[] = [
					'level'  => $level,
					'text'   => $this->plainText( $content ),
					'anchor' => $anchor,
				];
			}
		}

		if ( in_array( $name, self::POST_CONTENT_BLOCKS, true ) ) {
			$content = isset( $sourceAttributes['_resolvedContent'] ) && is_string( $sourceAttributes['_resolvedContent'] )
				? $sourceAttributes['_resolvedContent']
				: '';

			$ownContent = isset( $attributes['_resolvedContent'] ) && is_string( $attributes['_resolvedContent'] )
				? $attributes['_resolvedContent']
				: '';

			if ( '' !== $content ) {
				$rewritten = $this->stampSourceContentHeadings( $ownContent, $content, $used, $headings );

				if ( $rewritten !== ( '' === $ownContent ? $content : $ownContent ) ) {
					$attributes        = array_merge( $attributes, [ '_resolvedContent' => $rewritten ] );
					$block[ $attrKey ] = $attributes;
				}
			}
		}

		if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$sourceInner = null !== $source && isset( $source['innerBlocks'] ) && is_array( $source['innerBlocks'] )
				? $source['innerBlocks']
				: null;

			$block['innerBlocks'] = $this->stampHeadings( $block['innerBlocks'], $used, $headings, $sourceInner );
		}

		return $block;
	}

	/**
	 * Stamp post-content headings using anchors derived from the source
	 * (binding- / token-resolved) HTML, injected into the block's own HTML.
	 *
	 * Writing the source HTML back would hand the renderer content whose
	 * tokens were already resolved, and its own token pass would then
	 * resolve any `{{…}}` inside a resolved value a second time (#858).
	 * Heading tags are paired by position. When the two strings hold a
	 * different number of headings the pairing can't be trusted, so the
	 * block's own HTML is stamped from its own text instead.
	 *
	 * @since 1.14.0
	 *
	 * @param  string                            $own       The block's own `_resolvedContent`.
	 * @param  string                            $source    The content-tree copy's `_resolvedContent`.
	 * @param  array<string, bool>               $used
	 * @param  array<int, array<string, mixed>>  $headings
	 */
	protected function stampSourceContentHeadings( string $own, string $source, array &$used, array &$headings ): string
	{
		if ( '' === $own || $own === $source ) {
			return $this->stampContentHeadings( '' === $own ? $source : $own, $used, $headings );
		}

		$sourceUsed     = $used;
		$sourceHeadings = $headings;
		$sourceIds      = $this->headingIds( $this->stampContentHeadings( $source, $sourceUsed, $sourceHeadings ) );

		if ( count( $sourceIds ) !== count( $this->headingIds( $own ) ) ) {
			return $this->stampContentHeadings( $own, $used, $headings );
		}

		$used     = $sourceUsed;
		$headings = $sourceHeadings;
		$index    = 0;

		$rewritten = preg_replace_callback(
			self::HEADING_TAG_PATTERN,
			function ( array $match ) use ( $sourceIds, &$index ): string {
				$id = $sourceIds[ $index++ ] ?? null;

				if ( null === $id || null !== $this->headingId( $match[2] ) ) {
					return $match[0];
				}

				return sprintf(
					'<h%1$d id="%2$s"%3$s>%4$s</h%1$d>',
					(int) $match[1],
					htmlspecialchars( $id, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
					$match[2],
					$match[3],
				);
			},
			$own,
		);

		return null === $rewritten ? $own : $rewritten;
	}

	/**
	 * The `id` of every `<h1>…<h6>` tag in the HTML, in document order,
	 * with null for a heading that has none.
	 *
	 * @since 1.14.0
	 *
	 * @return array<int, ?string>
	 */
	protected function headingIds( string $html ): array
	{
		if ( false === preg_match_all( self::HEADING_TAG_PATTERN, $html, $matches, PREG_SET_ORDER ) ) {
			return [];
		}

		return array_map( fn ( array $match ): ?string => $this->headingId( $match[2] ), $matches );
	}

	/**
	 * The `id` value in a heading tag's attribute string, or null.
	 *
	 * @since 1.14.0
	 */
	protected function headingId( string $attrs ): ?string
	{
		return preg_match( '#\bid\s*=\s*(["\'])([^"\']+)\1#i', $attrs, $idMatch ) ? $idMatch[2] : null;
	}

	/**
	 * Scan a rendered HTML string for `<h1>…<h6>` tags, add each to the
	 * running heading list, and inject an auto-generated `id="…"` on
	 * headings that do not already carry one. Existing ids are trusted
	 * verbatim (WordPress core's block editor writes `anchor` attributes
	 * out as `id=`, so a post edited in-app already has the same slugs
	 * this resolver would generate), which also means author-set custom
	 * anchors survive the pass unchanged.
	 *
	 * @since 1.9.0
	 *
	 * @param  array<string, bool>               $used
	 * @param  array<int, array<string, mixed>>  $headings
	 */
	protected function stampContentHeadings( string $html, array &$used, array &$headings ): string
	{
		$rewritten = preg_replace_callback(
			self::HEADING_TAG_PATTERN,
			function ( array $match ) use ( &$used, &$headings ): string {
				$level = (int) $match[1];
				$attrs = $match[2];
				$inner = $match[3];

				$plainText = $this->plainText( $inner );

				if ( '' === $plainText ) {
					return $match[0];
				}

				$existingId = $this->headingId( $attrs );

				if ( null !== $existingId ) {
					$used[ $existingId ] = true;
					$headings[]          = [
						'level'  => $level,
						'text'   => $plainText,
						'anchor' => $existingId,
					];

					return $match[0];
				}

				$slug = $this->slug( $inner );

				if ( '' === $slug ) {
					return $match[0];
				}

				$anchor          = $this->uniqueSlug( $slug, $used );
				$used[ $anchor ] = true;

				$headings[] = [
					'level'  => $level,
					'text'   => $plainText,
					'anchor' => $anchor,
				];

				return sprintf(
					'<h%1$d id="%2$s"%3$s>%4$s</h%1$d>',
					$level,
					htmlspecialchars( $anchor, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
					$attrs,
					$inner,
				);
			},
			$html,
		);

		return null === $rewritten ? $html : $rewritten;
	}

	/**
	 * Second pass: stamp `_resolvedItems` on every TOC block, filtered by
	 * that block's `minLevel` / `maxLevel` attributes.
	 *
	 * @since 1.9.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 * @param  array<int, array<string, mixed>>  $headings
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function stampTocBlocks( array $tree, array $headings ): array
	{
		$out = [];

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$out[] = $this->stampTocBlock( $block, $headings );
		}

		return $out;
	}

	/**
	 * Stamp a single block (and its inner blocks) with the derived TOC
	 * items when the block is `artisanpack/toc`.
	 *
	 * @since 1.9.0
	 *
	 * @param  array<string, mixed>              $block
	 * @param  array<int, array<string, mixed>>  $headings
	 *
	 * @return array<string, mixed>
	 */
	protected function stampTocBlock( array $block, array $headings ): array
	{
		$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';

		if ( in_array( $name, self::TOC_BLOCKS, true ) ) {
			[ $attrKey, $attributes ] = BlockShape::readAttrs( $block );

			// Host apps can layer their own heading extractor in front of
			// this resolver — a headless site, for example, might stamp
			// `_resolvedItems` from a stored search index. Respect any
			// pre-populated list rather than clobbering it with our own
			// derivation so upstream work is never silently discarded.
			if ( ! array_key_exists( '_resolvedItems', $attributes ) ) {
				$rawMin   = $attributes['minLevel'] ?? 2;
				$rawMax   = $attributes['maxLevel'] ?? 6;
				$minLevel = is_numeric( $rawMin ) ? (int) round( (float) $rawMin ) : 2;
				$maxLevel = is_numeric( $rawMax ) ? (int) round( (float) $rawMax ) : 6;

				$minLevel = max( 1, min( 6, $minLevel ) );
				$maxLevel = max( 1, min( 6, $maxLevel ) );

				if ( $minLevel > $maxLevel ) {
					// Swap so a mis-ordered config still produces sensible
					// output instead of an empty list.
					[ $minLevel, $maxLevel ] = [ $maxLevel, $minLevel ];
				}

				$filtered = [];

				foreach ( $headings as $heading ) {
					$level = (int) ( $heading['level'] ?? 0 );

					if ( $level < $minLevel || $level > $maxLevel ) {
						continue;
					}

					$filtered[] = $heading;
				}

				$block[ $attrKey ] = array_merge( $attributes, [ '_resolvedItems' => $filtered ] );
			}
		}

		if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = $this->stampTocBlocks( $block['innerBlocks'], $headings );
		}

		return $block;
	}

	/**
	 * Slug a heading string into an HTML-id-safe anchor. Matches the
	 * editor's `blocks/heading/autogenerate-anchors.ts` normalization
	 * order (the `remove-accents` npm package → unicode-aware
	 * non-letter/non-number collapse → lowercase) so a heading like
	 * "Café" produces the same `cafe` anchor client-side and
	 * server-side.
	 *
	 * @since 1.9.0
	 */
	protected function slug( string $content ): string
	{
		$text = $this->plainText( $content );

		if ( '' === $text ) {
			return '';
		}

		$text = $this->removeAccents( $text );

		// Collapse runs of non-letter/non-number characters into a single
		// hyphen. The `u` flag makes the character classes unicode-aware
		// so any non-Latin scripts the accent-removal pass didn't fold
		// (Cyrillic, CJK, etc.) still survive as valid HTML5 id chars.
		$hyphenated = preg_replace( '/[^\p{L}\p{N}]+/u', '-', $text );

		if ( null === $hyphenated ) {
			return '';
		}

		// `mb_strtolower` is Unicode-safe (`strtolower` only handles
		// ASCII, so an unfolded Cyrillic capital "Ф" would leak through
		// as an uppercase char and mismatch what the editor emits).
		$lower = function_exists( 'mb_strtolower' )
			? mb_strtolower( $hyphenated, 'UTF-8' )
			: strtolower( $hyphenated );

		return trim( $lower, '-' );
	}

	/**
	 * Strip diacritics from a UTF-8 string to match the editor's
	 * `remove-accents` npm package. Prefers PHP's Normalizer + combining
	 * marks strip (correct across every Unicode block), and falls back
	 * to iconv's `//TRANSLIT` for environments without the `intl`
	 * extension.
	 *
	 * @since 1.9.0
	 */
	protected function removeAccents( string $text ): string
	{
		if ( class_exists( Normalizer::class ) ) {
			$decomposed = Normalizer::normalize( $text, Normalizer::FORM_D );

			if ( is_string( $decomposed ) ) {
				$stripped = preg_replace( '/\p{Mn}+/u', '', $decomposed );

				if ( is_string( $stripped ) ) {
					return $stripped;
				}
			}
		}

		if ( function_exists( 'iconv' ) ) {
			$translit = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $text );

			if ( false !== $translit ) {
				return $translit;
			}
		}

		return $text;
	}

	/**
	 * Ensure the slug is unique across the tree by appending an integer
	 * suffix. Mirrors the editor's per-client anchor registry.
	 *
	 * @since 1.9.0
	 *
	 * @param  array<string, bool>  $used
	 */
	protected function uniqueSlug( string $slug, array $used ): string
	{
		if ( ! isset( $used[ $slug ] ) ) {
			return $slug;
		}

		$i = 1;

		while ( isset( $used[ $slug . '-' . $i ] ) ) {
			++$i;
		}

		return $slug . '-' . $i;
	}

	/**
	 * Decode entities and strip tags from a heading's stored content so
	 * both the slug source and the TOC label are plain text.
	 *
	 * @since 1.9.0
	 */
	protected function plainText( string $content ): string
	{
		$decoded   = html_entity_decode( strip_tags( $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$collapsed = preg_replace( '/\s+/u', ' ', $decoded );

		return trim( null === $collapsed ? $decoded : $collapsed );
	}
}
