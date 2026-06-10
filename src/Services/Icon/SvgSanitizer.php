<?php

/**
 * SVG sanitizer — strips dangerous markup before rendering icon SVGs.
 *
 * Phase 2 (#553) of the Icon Block feature (#494). Phase 1 ships the
 * service contract + a tight allowlist implementation; Phase 2 issue
 * tightens edge cases (e.g. CSS expression() in `style` attributes,
 * data:image/svg+xml in `xlink:href`).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Icon;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Author-supplied SVGs (pasted blocks, admin uploads) can contain `<script>`
 * blocks, event handlers, external references via `xlink:href`, and CSS
 * `expression()` in inline styles. We DOM-parse the SVG, drop everything
 * outside a strict allowlist of tags + attributes, and serialize the
 * survivors back out. Warnings track what was removed so the editor can
 * surface them inline.
 */
class SvgSanitizer
{
	/**
	 * Tag allowlist. Everything else is dropped wholesale (including the
	 * subtree under it — `<foreignObject>` is the obvious vector for
	 * injecting non-SVG content like `<iframe>` or `<script>`).
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_TAGS = [
		'svg',
		'g',
		'title',
		'desc',
		'defs',
		'path',
		'rect',
		'circle',
		'ellipse',
		'line',
		'polyline',
		'polygon',
		'text',
		'tspan',
		'use',
		'symbol',
		'clipPath',
		'mask',
		'linearGradient',
		'radialGradient',
		'stop',
	];

	/**
	 * Attributes that we let through after value-scrubbing. The full SVG
	 * attribute surface is huge; this list covers what icon sets actually
	 * use in practice (FA 6, Heroicons, Material) without dragging in
	 * presentation attributes that double as script vectors (e.g.
	 * `onload`, `onclick`).
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_ATTRS = [
		'class',
		'id',
		'viewBox',
		'xmlns',
		'xmlns:xlink',
		'preserveAspectRatio',
		'width',
		'height',
		'x',
		'y',
		'x1',
		'x2',
		'y1',
		'y2',
		'cx',
		'cy',
		'r',
		'rx',
		'ry',
		'd',
		'points',
		'fill',
		'fill-rule',
		'fill-opacity',
		'stroke',
		'stroke-width',
		'stroke-linecap',
		'stroke-linejoin',
		'stroke-dasharray',
		'stroke-opacity',
		'opacity',
		'transform',
		'clip-path',
		'mask',
		'offset',
		'stop-color',
		'stop-opacity',
		'gradientUnits',
		'gradientTransform',
		'spreadMethod',
		// Modern authoring tools (Illustrator, Figma, Inkscape) write
		// presentation rules into `style="…"` rather than discrete
		// `fill=` / `stroke=` attributes — stripping it wholesale turns
		// any of those exports into a black silhouette. We let it
		// through and scrub the value below (`expression()`, external
		// `url()`, `javascript:` are rejected).
		'style',
		// Harmless metadata that Illustrator stamps onto the root.
		// Excluding them just produces noisy warnings on every paste.
		'version',
		'xml:space',
		// URI attributes — passed through the allowlist gate so the
		// downstream `isSafeUri` check gets a chance to keep internal
		// anchor references (`#gradient1`) and reject everything else.
		'href',
		'xlink:href',
		'src',
	];

	public function sanitize( string $svg ): SvgSanitizationResult
	{
		$trimmed = trim( $svg );
		if ( '' === $trimmed ) {
			return new SvgSanitizationResult( '' );
		}

		// Strip XML declarations + DOCTYPEs up front. They're never part of
		// an inline icon and DOMDocument resolves doctypes by default — an
		// attacker could otherwise smuggle an external entity reference.
		$cleaned = preg_replace( '/<\?xml.*?\?>/si', '', $trimmed ) ?? $trimmed;

		// Strip DOCTYPEs *including* internal subsets — a naive `<!DOCTYPE[^>]*>`
		// regex would stop at the first `>` inside an `<!ENTITY>` declaration,
		// leaving the XXE payload in place for libxml to ingest.
		$cleaned = preg_replace( '/<!DOCTYPE\b[^[>]*(\[[^\]]*\])?[^>]*>/si', '', $cleaned ) ?? $cleaned;

		$warnings = [];

		$dom                     = new DOMDocument();
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput       = false;

		$prev = libxml_use_internal_errors( true );

		// `LIBXML_NONET` blocks network fetches for external entities.
		$wrapped = '<?xml version="1.0" encoding="UTF-8"?>' . $cleaned;
		$loaded  = $dom->loadXML( $wrapped, LIBXML_NONET | LIBXML_NOENT );

		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( ! $loaded || null === $dom->documentElement ) {
			return new SvgSanitizationResult( '', [ 'svg failed to parse' ] );
		}

		$root = $dom->documentElement;
		if ( 'svg' !== $root->localName ) {
			return new SvgSanitizationResult( '', [ 'root element is not <svg>' ] );
		}

		// Scrub the root's own attributes first — `scrubNode` walks
		// CHILDREN, so without this an `onload` or `onclick` on the
		// outer <svg> tag would survive untouched.
		$this->scrubAttributes( $root, $warnings );
		$this->scrubNode( $root, $warnings );

		$serialized = $dom->saveXML( $root );
		if ( false === $serialized ) {
			return new SvgSanitizationResult( '', [ 'svg failed to serialize' ] );
		}

		return new SvgSanitizationResult( $serialized, $warnings );
	}

	/**
	 * Recursively walk the SVG tree, removing disallowed tags + attributes.
	 *
	 * Iterates children into a snapshot first because we mutate the live
	 * NodeList as we remove nodes — iterating it directly skips siblings.
	 *
	 * @param  array<int, string> $warnings
	 */
	private function scrubNode( DOMNode $node, array &$warnings ): void
	{
		$children = [];
		foreach ( $node->childNodes as $child ) {
			$children[] = $child;
		}

		foreach ( $children as $child ) {
			if ( ! $child instanceof DOMElement ) {
				continue;
			}

			$tag = $child->localName;
			if ( ! in_array( $tag, self::ALLOWED_TAGS, true ) ) {
				$warnings[] = sprintf( 'removed <%s> element', $tag );
				$node->removeChild( $child );
				continue;
			}

			$this->scrubAttributes( $child, $warnings );
			$this->scrubNode( $child, $warnings );
		}
	}

	/**
	 * @param  array<int, string> $warnings
	 */
	private function scrubAttributes( DOMElement $element, array &$warnings ): void
	{
		$drop = [];
		foreach ( $element->attributes as $attr ) {
			$name = $attr->nodeName;
			if ( ! in_array( $name, self::ALLOWED_ATTRS, true ) ) {
				$drop[]     = $name;
				$warnings[] = sprintf( 'removed attribute "%s" from <%s>', $name, $element->localName );
				continue;
			}

			$value = (string) $attr->nodeValue;

			if ( 'style' === $name ) {
				$cleaned = $this->scrubStyleValue( $value, $element->localName, $warnings );
				if ( '' === $cleaned ) {
					$drop[] = $name;
				} elseif ( $cleaned !== $value ) {
					$attr->nodeValue = $cleaned;
				}
				continue;
			}

			if ( $this->isUriAttr( $name ) && ! $this->isSafeUri( $value ) ) {
				$drop[]     = $name;
				$warnings[] = sprintf( 'removed unsafe URI from %s on <%s>', $name, $element->localName );
				continue;
			}
		}

		foreach ( $drop as $attrName ) {
			$element->removeAttribute( $attrName );
		}
	}

	/**
	 * Filter individual CSS declarations from a `style="…"` value.
	 *
	 * We keep declarations whose values are inert (`fill: #abc`, `opacity: 0.5`)
	 * and reject anything carrying a known script vector — `expression(…)`
	 * (IE legacy), `javascript:` / `vbscript:` URIs, `-moz-binding`, and
	 * external `url(http://…)` / `url(data:…)` references. Internal anchor
	 * refs (`url(#gradient1)`) are preserved because gradients depend on
	 * them.
	 *
	 * @param  array<int, string> $warnings
	 */
	private function scrubStyleValue( string $value, string $tag, array &$warnings ): string
	{
		$kept = [];
		foreach ( explode( ';', $value ) as $declaration ) {
			$trimmed = trim( $declaration );
			if ( '' === $trimmed ) {
				continue;
			}

			$lower = strtolower( $trimmed );
			$bad   = false;

			if ( false !== strpos( $lower, 'expression(' ) ) {
				$bad = true;
			} elseif ( false !== strpos( $lower, 'javascript:' ) || false !== strpos( $lower, 'vbscript:' ) ) {
				$bad = true;
			} elseif ( false !== strpos( $lower, '-moz-binding' ) ) {
				$bad = true;
			} elseif ( preg_match( '/url\(\s*["\']?\s*(?!#)([a-z]+:|\/\/)/i', $lower ) ) {
				// `url(http://…)`, `url(//host/…)`, `url(data:…)` — any
				// scheme-bearing or protocol-relative reference. The
				// `(?!#)` negative lookahead keeps `url(#gradient1)`.
				$bad = true;
			}

			if ( $bad ) {
				$warnings[] = sprintf( 'removed unsafe style declaration on <%s>', $tag );
				continue;
			}

			$kept[] = $trimmed;
		}

		return implode( '; ', $kept );
	}

	private function isUriAttr( string $name ): bool
	{
		return in_array( $name, [ 'href', 'xlink:href', 'src' ], true );
	}

	private function isSafeUri( string $value ): bool
	{
		$value = trim( $value );
		if ( '' === $value ) {
			return false;
		}

		// Internal anchor references (`#gradient1`) are safe — they're how
		// gradient `<defs>` are referenced from `<use>` elements.
		if ( str_starts_with( $value, '#' ) ) {
			return true;
		}

		// Everything else (`javascript:`, `data:`, `file://`, plain http)
		// is rejected. Icons never need to fetch remote payloads at render
		// time; if a future use case appears, it should be opt-in per set.
		return false;
	}
}
