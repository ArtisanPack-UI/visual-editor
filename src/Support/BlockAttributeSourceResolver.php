<?php

/**
 * Server-side port of Gutenberg's save-shape attribute matchers.
 *
 * Gutenberg persists most block text NOT in the delimiter's JSON but in
 * the block's saved HTML, recovering it on load by running each
 * `block.json` attribute definition's `source` matcher over the block's
 * `innerHTML`. Everything server-side that starts from block markup
 * (theme `templates/*.html`, `parts/*.html`, `.php` patterns) therefore
 * needs the same recovery pass or it renders structurally-correct but
 * textless output — see #688.
 *
 * This class implements the matchers Gutenberg's `parseWithAttributeSchema`
 * supports over `DOMDocument` + XPath:
 *
 * | source       | value                                                        |
 * |--------------|--------------------------------------------------------------|
 * | `attribute`  | the named HTML attribute of the first match (boolean → presence) |
 * | `html`       | the first match's inner HTML (`multiline` → matching children)   |
 * | `rich-text`  | same as `html`, no multiline handling                            |
 * | `text`       | the first match's text content                                   |
 * | `tag`        | the matched element's lowercased tag name                        |
 * | `query`      | a list, one entry per match, of a nested attribute definition set |
 * | `raw`        | the block's whole inner HTML                                     |
 *
 * A definition with no `selector` matches the context node itself,
 * mirroring hpq's behavior — which is what makes `query` sub-attributes
 * like the table block's per-cell `content` resolve against their own
 * cell rather than the whole table.
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

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Throwable;

class BlockAttributeSourceResolver
{
	/**
	 * `source` values this resolver knows how to evaluate. Definitions
	 * carrying anything else (or no `source` at all) are left to the
	 * delimiter's JSON attributes.
	 *
	 * @var array<int, string>
	 *
	 * @since 1.5.5
	 */
	public const SUPPORTED_SOURCES = [
		'attribute',
		'property',
		'html',
		'rich-text',
		'text',
		'tag',
		'query',
		'raw',
	];

	/**
	 * Selectors already reported as untranslatable, keyed by selector,
	 * so the log records each offending manifest once per process
	 * instead of once per rendered block instance.
	 *
	 * @var array<string, true>
	 *
	 * @since 1.5.5
	 */
	protected static array $reportedSelectors = [];

	/**
	 * Recover every sourced attribute a block type declares from the
	 * block's saved inner HTML.
	 *
	 * Attributes whose matcher finds nothing are omitted entirely (rather
	 * than emitted as `null`) so the caller can layer the recovered bag
	 * UNDER the delimiter's own attributes without a miss blanking a
	 * value the delimiter did persist.
	 *
	 * @since 1.5.5
	 *
	 * @param  array<string, mixed>  $definitions  The block type's `attributes` map from `block.json`.
	 * @param  string                $innerHtml    The block's saved inner HTML.
	 *
	 * @return array<string, mixed> Recovered attributes, keyed by attribute name.
	 */
	public function recover( array $definitions, string $innerHtml ): array
	{
		if ( [] === $definitions || '' === trim( $innerHtml ) ) {
			return [];
		}

		$root     = $this->parseFragment( $innerHtml );
		$document = $root?->ownerDocument;

		if ( null === $root || null === $document ) {
			return [];
		}

		$xpath     = new DOMXPath( $document );
		$recovered = [];

		foreach ( $definitions as $name => $definition ) {
			if ( ! is_string( $name ) || ! is_array( $definition ) ) {
				continue;
			}

			$value = $this->resolve( $definition, $root, $xpath, $innerHtml );

			if ( null !== $value ) {
				$recovered[ $name ] = $value;
			}
		}

		return $recovered;
	}

	/**
	 * Evaluate a single attribute definition against a context node.
	 *
	 * @since 1.5.5
	 *
	 * @param  array<string, mixed>  $definition
	 * @param  ?string               $contextHtml  Verbatim inner HTML of `$context` when the caller has it.
	 *                                             Only the `raw` source reads it; omitted callers get a
	 *                                             re-serialized equivalent, computed lazily so a `query`
	 *                                             with many matches doesn't serialize each one for nothing.
	 *
	 * @return mixed The recovered value, or null when nothing matched.
	 */
	protected function resolve( array $definition, DOMNode $context, DOMXPath $xpath, ?string $contextHtml = null ): mixed
	{
		$source = $definition['source'] ?? null;

		if ( ! is_string( $source ) || ! in_array( $source, self::SUPPORTED_SOURCES, true ) ) {
			return null;
		}

		if ( 'raw' === $source ) {
			return $this->coerce( $contextHtml ?? $this->innerHtml( $context, null ), $definition );
		}

		$selector = isset( $definition['selector'] ) && is_string( $definition['selector'] )
			? trim( $definition['selector'] )
			: '';

		if ( 'query' === $source ) {
			return $this->resolveQuery( $definition, $context, $xpath, $selector );
		}

		$node = '' === $selector ? $context : $this->firstMatch( $xpath, $context, $selector );

		if ( null === $node ) {
			return null;
		}

		return $this->resolveScalar( $source, $definition, $node );
	}

	/**
	 * Evaluate a non-`query`, non-`raw` source against a resolved node.
	 *
	 * @since 1.5.5
	 *
	 * @param  array<string, mixed>  $definition
	 */
	protected function resolveScalar( string $source, array $definition, DOMNode $node ): mixed
	{
		switch ( $source ) {
			case 'attribute':
			case 'property':
				$attribute = isset( $definition['attribute'] ) && is_string( $definition['attribute'] )
					? $definition['attribute']
					: null;

				if ( null === $attribute || ! $node instanceof DOMElement ) {
					return null;
				}

				// HTML boolean attributes are persisted by presence, not
				// by value — `<video loop>` and `<video loop="">` both
				// mean true. Gutenberg models this with
				// `toBooleanAttributeMatcher`; mirror it here so a
				// re-parsed `<video controls>` doesn't come back false.
				if ( 'boolean' === ( $definition['type'] ?? null ) ) {
					return $node->hasAttribute( $attribute );
				}

				if ( ! $node->hasAttribute( $attribute ) ) {
					return null;
				}

				return $this->coerce( $node->getAttribute( $attribute ), $definition );

			case 'html':
			case 'rich-text':
				$multiline = 'html' === $source && isset( $definition['multiline'] ) && is_string( $definition['multiline'] )
					? strtolower( $definition['multiline'] )
					: null;

				return $this->coerce( $this->innerHtml( $node, $multiline ), $definition );

			case 'text':
				return $this->coerce( $node->textContent, $definition );

			case 'tag':
				return $node instanceof DOMElement ? strtolower( $node->tagName ) : null;
		}

		return null;
	}

	/**
	 * Evaluate a `query` source — one entry per selector match, each
	 * built by resolving the nested definition map against that match.
	 *
	 * @since 1.5.5
	 *
	 * @param  array<string, mixed>  $definition
	 *
	 * @return array<int, array<string, mixed>>|null
	 */
	protected function resolveQuery( array $definition, DOMNode $context, DOMXPath $xpath, string $selector ): ?array
	{
		$query = $definition['query'] ?? null;

		if ( ! is_array( $query ) || [] === $query ) {
			return null;
		}

		$matches = '' === $selector ? [ $context ] : $this->allMatches( $xpath, $context, $selector );

		if ( null === $matches ) {
			return null;
		}

		$items = [];

		foreach ( $matches as $match ) {
			$item = [];

			foreach ( $query as $name => $subDefinition ) {
				if ( ! is_string( $name ) || ! is_array( $subDefinition ) ) {
					continue;
				}

				$value = $this->resolve( $subDefinition, $match, $xpath );

				if ( null === $value && array_key_exists( 'default', $subDefinition ) ) {
					$value = $subDefinition['default'];
				}

				if ( null !== $value ) {
					$item[ $name ] = $value;
				}
			}

			$items[] = $item;
		}

		return $items;
	}

	/**
	 * First node matching `$selector` relative to `$context`, or null
	 * when the selector matches nothing or is outside the supported
	 * CSS subset.
	 *
	 * @since 1.5.5
	 */
	protected function firstMatch( DOMXPath $xpath, DOMNode $context, string $selector ): ?DOMNode
	{
		$matches = $this->allMatches( $xpath, $context, $selector );

		return null === $matches ? null : ( $matches[0] ?? null );
	}

	/**
	 * All nodes matching `$selector` relative to `$context`, in document
	 * order. Returns null (rather than an empty list) when the selector
	 * could not be translated, so callers can tell "no match" from
	 * "cannot evaluate".
	 *
	 * @since 1.5.5
	 *
	 * @return array<int, DOMNode>|null
	 */
	protected function allMatches( DOMXPath $xpath, DOMNode $context, string $selector ): ?array
	{
		try {
			$expression = CssSelectorToXPath::translate( $selector );
		} catch ( UnsupportedSelectorException $e ) {
			// Selectors come from a fixed set of registered `block.json`
			// manifests, so an unsupported one would otherwise be
			// reported once per block instance on every page render.
			// Report the first occurrence per process and stay quiet
			// after that — the signal is "this manifest needs a
			// translator update", not "this page had N blocks".
			if ( ! isset( self::$reportedSelectors[ $selector ] ) ) {
				self::$reportedSelectors[ $selector ] = true;

				report( $e );
			}

			return null;
		}

		// A comma group translates to a union of relative paths. XPath
		// unions bind looser than the implicit context, so each branch
		// is already anchored by its own axis — no extra parenthesizing
		// needed. `false` comes back on a malformed expression, which
		// the translator's grammar should preclude; treat it as
		// "cannot evaluate" rather than raising mid-render.
		$nodes = @$xpath->query( $expression, $context );

		if ( false === $nodes ) {
			return null;
		}

		$out = [];

		foreach ( $nodes as $node ) {
			$out[] = $node;
		}

		return $out;
	}

	/**
	 * Serialize a node's children back to an HTML string.
	 *
	 * When `$multilineTag` is set, only child ELEMENTS with that tag
	 * name are kept and each is serialized in full (outer HTML) —
	 * matching hpq's multiline `html` matcher, which is what turns a
	 * saved `<ul><li>a</li><li>b</li></ul>` back into the list block's
	 * `values` attribute.
	 *
	 * @since 1.5.5
	 */
	protected function innerHtml( DOMNode $node, ?string $multilineTag ): string
	{
		$document = $node->ownerDocument;

		if ( null === $document ) {
			return '';
		}

		$html = '';

		foreach ( $node->childNodes as $child ) {
			if ( null !== $multilineTag ) {
				if ( ! $child instanceof DOMElement || strtolower( $child->tagName ) !== $multilineTag ) {
					continue;
				}
			}

			$html .= (string) $document->saveHTML( $child );
		}

		return $html;
	}

	/**
	 * Cast a matched string to the type the definition declares.
	 * Non-castable values fall through unchanged — Gutenberg's `asType`
	 * is equally permissive, preferring a wrong-typed value over a
	 * dropped one.
	 *
	 * @since 1.5.5
	 *
	 * @param  array<string, mixed>  $definition
	 */
	protected function coerce( string $value, array $definition ): mixed
	{
		$type = $definition['type'] ?? null;

		return match ( $type ) {
			'boolean' => '' !== $value && 'false' !== $value,
			'number'  => is_numeric( $value ) ? $value + 0 : null,
			'integer' => is_numeric( $value ) ? (int) $value : null,
			default   => $value,
		};
	}

	/**
	 * Parse a block's inner HTML into a DOM subtree and return the
	 * element the matchers should treat as their context node.
	 *
	 * The fragment is wrapped in a synthetic container so a multi-root
	 * fragment (e.g. a figure plus a trailing caption) still has a
	 * single context, and so `descendant-or-self::` selectors match the
	 * fragment's own roots — that's the case that recovers a
	 * paragraph's `content` from its `<p>` root.
	 *
	 * @since 1.5.5
	 */
	protected function parseFragment( string $html ): ?DOMNode
	{
		$document = new DOMDocument();

		$previous = libxml_use_internal_errors( true );

		try {
			// The XML declaration forces UTF-8 without libxml's legacy
			// "no charset means ISO-8859-1" fallback mangling multibyte
			// text. LIBXML_HTML_NOIMPLIED/NODEFDTD keep libxml from
			// injecting its own <html>/<body> wrappers around ours.
			$loaded = $document->loadHTML(
				'<?xml encoding="UTF-8"?><div id="ve-block-fragment">' . $html . '</div>',
				LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
			);
		} catch ( Throwable $e ) {
			report( $e );

			return null;
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}

		if ( false === $loaded ) {
			return null;
		}

		$root = $document->getElementById( 've-block-fragment' );

		if ( $root instanceof DOMNode ) {
			return $root;
		}

		// `getElementById` needs a DTD-declared id to work on some
		// libxml builds; fall back to a direct XPath lookup.
		$found = ( new DOMXPath( $document ) )->query( "//*[@id='ve-block-fragment']" );

		return ( false !== $found && $found->length > 0 ) ? $found->item( 0 ) : null;
	}
}
