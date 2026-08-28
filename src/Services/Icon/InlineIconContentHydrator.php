<?php

/**
 * Render-time hydrator for inline-icon reference spans (#717).
 *
 * The `artisanpack/inline-icon` RichText format saves registered-set
 * icons as lightweight reference spans:
 *
 *     <span class="ap-inline-icon" data-icon-set="fab" data-icon-name="github">…</span>
 *
 * Static blocks (paragraph, heading, button, …) emit their stored
 * rich-text verbatim with no per-block PHP callback, so the only place
 * to turn those references into real SVG is a pass over the fully
 * rendered content. This service is wired onto the
 * `ap.visualEditor.renderedContent` filter in
 * {@see \ArtisanPackUI\VisualEditor\VisualEditorServiceProvider} and
 * re-resolves every reference span through the shared
 * {@see IconSvgResolver} — the same resolver the standalone Icon block,
 * the picker search endpoint, and the `icons/svg` endpoint use, so the
 * inline icons draw from identical set coverage.
 *
 * Custom-SVG inline icons carry no `data-icon-set` / `data-icon-name`
 * (their sanitized `<svg>` is embedded directly in the content) and are
 * left untouched.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Icon;

/**
 * Replaces the body of every registered-set inline-icon reference span
 * with its freshly resolved SVG.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.7.0
 */
final class InlineIconContentHydrator
{
	/**
	 * Matches a `<span>` whose opening tag carries the `ap-inline-icon`
	 * marker, capturing the attribute run and the inner body. The
	 * lookahead gates the match on the marker so unrelated spans are
	 * skipped; the body is matched non-greedily up to the first
	 * `</span>`, which is safe because an inline icon's body is either
	 * empty or a single `<svg>` (SVG markup never contains `</span>`).
	 */
	private const SPAN_PATTERN = '/<span\b(?=[^>]*\bap-inline-icon\b)([^>]*)>(.*?)<\/span>/is';

	/**
	 * Inline style forced onto every resolved `<svg>` so an inline icon
	 * stays inline (`display:inline-block` overrides CSS resets such as
	 * Tailwind's `svg { display: block }`), sizes to `1em`, inherits the
	 * surrounding text color, and seats on the baseline — all with no
	 * dependency on the frontend stylesheet. Kept in sync with the
	 * editor's `normalizeInlineIconSvg()`.
	 */
	private const SVG_STYLE = 'display:inline-block;width:1em;height:1em;fill:currentColor;vertical-align:-0.125em';

	public function __construct( private readonly IconSvgResolver $resolver ) {}

	/**
	 * Hydrate every registered-set inline-icon reference span in the
	 * given rendered content.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $html  Fully rendered block content.
	 *
	 * @return string The content with reference spans hydrated to SVG.
	 */
	public function hydrate( string $html ): string
	{
		// Fast path: skip the regex entirely when no inline icon is
		// present, which is the overwhelming majority of rendered pages.
		if ( false === stripos( $html, 'ap-inline-icon' ) ) {
			return $html;
		}

		$replaced = preg_replace_callback(
			self::SPAN_PATTERN,
			fn ( array $match ): string => $this->hydrateSpan( $match ),
			$html
		);

		// `preg_replace_callback` returns null only on a PCRE failure
		// (e.g. backtrack-limit blow-up); fall back to the untouched
		// content rather than blanking the page.
		return is_string( $replaced ) ? $replaced : $html;
	}

	/**
	 * Resolve a single matched span. Registered-set references have
	 * their body replaced with the resolved SVG (or emptied on a miss);
	 * custom-SVG spans and malformed references are returned untouched.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<int, string>  $match  `[ full, attributes, body ]` from {@see self::SPAN_PATTERN}.
	 */
	private function hydrateSpan( array $match ): string
	{
		$attributes = $match[1];

		$set  = $this->attributeValue( $attributes, 'data-icon-set' );
		$name = $this->attributeValue( $attributes, 'data-icon-name' );

		// No reference pair → custom-SVG (or an unrelated) span. Leave
		// it exactly as authored.
		if ( null === $set || null === $name ) {
			return $match[0];
		}

		$svg = $this->resolver->resolve( $set, $name );

		// Unknown / removed set, missing file, or invalid slug: empty
		// the body so the icon degrades gracefully to nothing rather
		// than leaving stale markup behind.
		$body = is_string( $svg ) ? $this->normalizeSvg( $svg ) : '';

		return '<span' . $attributes . '>' . $body . '</span>';
	}

	/**
	 * Force the inherit-first sizing / colour styles onto a resolved
	 * SVG's root element: drop intrinsic width/height so the inline `1em`
	 * wins, and merge {@see self::SVG_STYLE} into its `style`. Mirrors the
	 * editor's `normalizeInlineIconSvg()` so editor preview and rendered
	 * output match.
	 *
	 * @since 1.7.0
	 */
	private function normalizeSvg( string $svg ): string
	{
		$trimmed = trim( $svg );

		if ( 1 !== preg_match( '/^<svg\b/i', $trimmed ) ) {
			return $trimmed;
		}

		$replaced = preg_replace_callback(
			'/<svg\b([^>]*?)(\/?)>/i',
			function ( array $match ): string {
				$attributes = preg_replace(
					[ '/\s(?:width|height)\s*=\s*"[^"]*"/i', "/\\s(?:width|height)\\s*=\\s*'[^']*'/i" ],
					'',
					$match[1]
				);
				$attributes = is_string( $attributes ) ? $attributes : $match[1];

				// Matches a `style="…"` or `style='…'` attribute, capturing
				// the declarations under group 1 (double) or 2 (single).
				$styleAttr = '/\sstyle\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i';

				if ( 1 === preg_match( $styleAttr, $attributes ) ) {
					$merged = preg_replace_callback(
						$styleAttr,
						function ( array $style ): string {
							// One alternation branch matched: PHP leaves the
							// other capture as '' (not null), so pick the
							// non-empty one rather than null-coalescing.
							$raw      = '' !== ( $style[1] ?? '' ) ? $style[1] : ( $style[2] ?? '' );
							$existing = rtrim( trim( $raw ), ';' );

							// Enforced declarations go LAST so the icon's own
							// 1em sizing / currentColor win over any source
							// width/height/fill in the existing style.
							$declarations = '' === $existing
								? self::SVG_STYLE
								: $existing . ';' . self::SVG_STYLE;

							return ' style="' . $declarations . '"';
						},
						$attributes,
						1
					);
					$attributes = is_string( $merged ) ? $merged : $attributes;
				} else {
					$attributes .= ' style="' . self::SVG_STYLE . '"';
				}

				return '<svg' . $attributes . $match[2] . '>';
			},
			$trimmed,
			1
		);

		return is_string( $replaced ) ? $replaced : $trimmed;
	}

	/**
	 * Extract a double-quoted attribute value from a span's attribute
	 * run. The `(?<![\w-])` guard keeps `data-icon-set` from matching
	 * inside a longer prefixed attribute name (e.g. `x-data-icon-set`).
	 * Returns null when the attribute is absent or empty.
	 *
	 * @since 1.7.0
	 */
	private function attributeValue( string $attributes, string $attribute ): ?string
	{
		$pattern = '/(?<![\w-])' . preg_quote( $attribute, '/' ) . '="([^"]*)"/i';

		if ( 1 !== preg_match( $pattern, $attributes, $matches ) ) {
			return null;
		}

		$value = trim( $matches[1] );

		return '' === $value ? null : $value;
	}
}
