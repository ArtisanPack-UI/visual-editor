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
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.9.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Resources;

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
	 * Walk the tree twice: once to stamp anchors on headings and collect
	 * the ordered heading list, once to stamp `_resolvedItems` on every
	 * TOC block.
	 *
	 * @since 1.9.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function resolveTree( array $tree ): array
	{
		$used     = [];
		$headings = [];

		$stamped = $this->stampHeadings( $tree, $used, $headings );

		if ( empty( $headings ) ) {
			// No headings in the tree — TOC blocks still get an empty
			// `_resolvedItems` so their renderer can emit a placeholder
			// message instead of the previous render's stale items.
			return $this->stampTocBlocks( $stamped, [] );
		}

		return $this->stampTocBlocks( $stamped, $headings );
	}

	/**
	 * First pass: stamp anchors on headings and collect their metadata
	 * in document order.
	 *
	 * @since 1.9.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 * @param  array<string, bool>               $used      Anchor slugs already claimed (by ref).
	 * @param  array<int, array<string, mixed>>  $headings  Collected heading rows (by ref).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function stampHeadings( array $tree, array &$used, array &$headings ): array
	{
		$out = [];

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$out[] = $this->stampHeadingBlock( $block, $used, $headings );
		}

		return $out;
	}

	/**
	 * Stamp a single block (and its inner blocks) with an anchor when the
	 * block is a heading and no author-set anchor is present.
	 *
	 * @since 1.9.0
	 *
	 * @param  array<string, mixed>              $block
	 * @param  array<string, bool>               $used
	 * @param  array<int, array<string, mixed>>  $headings
	 *
	 * @return array<string, mixed>
	 */
	protected function stampHeadingBlock( array $block, array &$used, array &$headings ): array
	{
		$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';

		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];

		if ( in_array( $name, self::HEADING_BLOCKS, true ) ) {
			$content = isset( $attributes['content'] ) && is_string( $attributes['content'] ) ? $attributes['content'] : '';
			$rawLevel = $attributes['level'] ?? 2;
			$level    = is_numeric( $rawLevel ) ? (int) round( (float) $rawLevel ) : 2;

			if ( $level < 1 ) {
				$level = 1;
			} elseif ( $level > 6 ) {
				$level = 6;
			}

			$existing = isset( $attributes['anchor'] ) && is_string( $attributes['anchor'] )
				? trim( $attributes['anchor'] )
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
					$anchor = $this->uniqueSlug( $slug, $used );
					$used[ $anchor ] = true;

					$attributes             = array_merge( $attributes, [ 'anchor' => $anchor ] );
					$block['attributes']    = $attributes;
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
			$content = isset( $attributes['_resolvedContent'] ) && is_string( $attributes['_resolvedContent'] )
				? $attributes['_resolvedContent']
				: '';

			if ( '' !== $content ) {
				$rewritten = $this->stampContentHeadings( $content, $used, $headings );

				if ( $rewritten !== $content ) {
					$attributes           = array_merge( $attributes, [ '_resolvedContent' => $rewritten ] );
					$block['attributes']  = $attributes;
				}
			}
		}

		if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = $this->stampHeadings( $block['innerBlocks'], $used, $headings );
		}

		return $block;
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
			'#<h([1-6])([^>]*)>(.*?)</h\1>#is',
			function ( array $match ) use ( &$used, &$headings ): string {
				$level = (int) $match[1];
				$attrs = $match[2];
				$inner = $match[3];

				$plainText = $this->plainText( $inner );

				if ( '' === $plainText ) {
					return $match[0];
				}

				$existingId = '';

				if ( preg_match( '#\bid\s*=\s*(["\'])([^"\']+)\1#i', $attrs, $idMatch ) ) {
					$existingId = $idMatch[2];
				}

				if ( '' !== $existingId ) {
					$used[ $existingId ] = true;
					$headings[] = [
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

				$anchor = $this->uniqueSlug( $slug, $used );
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
					$inner
				);
			},
			$html
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
			$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];

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

				$block['attributes'] = array_merge( $attributes, [ '_resolvedItems' => $filtered ] );
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
		if ( class_exists( \Normalizer::class ) ) {
			$decomposed = \Normalizer::normalize( $text, \Normalizer::FORM_D );

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
		$decoded = html_entity_decode( strip_tags( $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$collapsed = preg_replace( '/\s+/u', ' ', $decoded );

		return trim( null === $collapsed ? $decoded : $collapsed );
	}
}
