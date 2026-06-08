<?php

/**
 * Elements-API support compiler — Keystone #56.
 *
 * Walks a block's `attributes.style.elements.*` tree and produces a
 * per-block scoping class (`wp-elements-{hash}`) plus the inline
 * `<style>` rules scoped under that class. Mirrors the upstream
 * WordPress behavior where the block wrapper grows a hash-derived
 * class and Gutenberg emits a stylesheet block scoped to that class.
 *
 * Currently covers the `link` element's `color.text` (plus `:hover`
 * and `:focus` nested pseudo-state objects). The dedicated link picker
 * in `core/navigation` (and any other block with the link element
 * support) writes its choice here — a path separate from the
 * `textColor` / `customTextColor` attributes handled by
 * {@see BlockSupports::applyColor}.
 *
 * Heading and button element supports follow the same Gutenberg shape
 * (`style.elements.{heading|h1..h6|button}.color.text` etc.) and can
 * extend this helper without changing callers — they would just add
 * their own element selector mapping.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

class ElementsSupport
{
	/**
	 * Map a recognized element key to the descendant selector its rules
	 * should be scoped to. Keys outside this set are ignored so an
	 * attacker-authored attribute tree can't smuggle arbitrary selectors
	 * into the page.
	 *
	 * @var array<string, string>
	 */
	protected const ELEMENT_SELECTORS = [
		'link' => 'a',
	];

	/**
	 * Recognized pseudo-state keys nested under an element node. Order
	 * matches the cascade order WordPress emits, so consumers can rely
	 * on `:hover` overriding base and `:focus` overriding `:hover` when
	 * an author sets all three.
	 *
	 * @var array<int, string>
	 */
	protected const PSEUDO_STATES = [ ':hover', ':focus', ':active' ];

	/**
	 * Compile a block's `style.elements.*` tree into a per-block scoping
	 * class plus the inline `<style>` rules scoped under that class.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attributes  Raw block attributes from the
	 *                                            persisted block tree.
	 *
	 * @return array{class: string, style: string}
	 *         `class` is `''` when no element styles apply; otherwise a
	 *         `wp-elements-{hash}` token derived from the elements
	 *         subtree (stable across renders of the same attributes).
	 *         `style` is the CSS payload (no `<style>` wrapper) ready to
	 *         splice into a Blade partial — empty when nothing applies.
	 */
	public static function compile( array $attributes ): array
	{
		$elements = $attributes['style']['elements'] ?? null;

		if ( ! is_array( $elements ) || [] === $elements ) {
			return [ 'class' => '', 'style' => '' ];
		}

		$rules = [];

		foreach ( self::ELEMENT_SELECTORS as $elementKey => $selector ) {
			$node = $elements[ $elementKey ] ?? null;

			if ( ! is_array( $node ) ) {
				continue;
			}

			$baseDecls = self::declarationsFor( $node );

			if ( [] !== $baseDecls ) {
				$rules[] = [
					'selector'     => $selector,
					'declarations' => $baseDecls,
				];
			}

			foreach ( self::PSEUDO_STATES as $pseudo ) {
				$pseudoNode = $node[ $pseudo ] ?? null;

				if ( ! is_array( $pseudoNode ) ) {
					continue;
				}

				$pseudoDecls = self::declarationsFor( $pseudoNode );

				if ( [] === $pseudoDecls ) {
					continue;
				}

				$rules[] = [
					'selector'     => $selector . $pseudo,
					'declarations' => $pseudoDecls,
				];
			}
		}

		if ( [] === $rules ) {
			return [ 'class' => '', 'style' => '' ];
		}

		// Stable, content-derived hash so re-renders of the same block
		// produce the same class — useful for cache validators and for
		// test snapshots. 8 hex chars is plenty given the per-page
		// uniqueness scope (collision risk in the dozens-of-blocks
		// range is vanishingly small). `serialize()` instead of
		// `json_encode()` because it can't fail on resources / circular
		// refs (which would otherwise collapse multiple distinct trees
		// into the same `false` → identical hash).
		$hash  = substr( md5( serialize( $elements ) ), 0, 8 );
		$class = 'wp-elements-' . $hash;

		$css = '';

		foreach ( $rules as $rule ) {
			$css .= sprintf(
				'.%s %s{%s;}',
				$class,
				$rule['selector'],
				implode( ';', $rule['declarations'] ),
			);
		}

		return [
			'class' => $class,
			'style' => $css,
		];
	}

	/**
	 * Pull the CSS declarations off a single element-or-pseudo node.
	 * Currently only the `color.text` path emits a declaration; adding
	 * `color.background`, `typography.*`, etc. is a matter of mapping
	 * each Gutenberg attribute path to its CSS property here.
	 *
	 * The `!important` flag mirrors upstream WordPress: it defeats the
	 * theme's default `a { color: … }` cascade so an author's link
	 * picker actually wins on the front-end. Without it, a theme that
	 * sets a hard-coded link color would silently swallow the choice.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $node  A single element node (or
	 *                                      pseudo-state nested node).
	 *
	 * @return array<int, string>  CSS declarations (no trailing semicolons).
	 */
	protected static function declarationsFor( array $node ): array
	{
		$declarations = [];

		$text = self::stringAttr( $node['color']['text'] ?? null );

		if ( '' !== $text ) {
			$expanded  = self::expandPresetReference( $text );
			$sanitized = self::sanitizeColorValue( $expanded );

			if ( '' !== $sanitized ) {
				$declarations[] = 'color: ' . $sanitized . ' !important';
			}
		}

		return $declarations;
	}

	/**
	 * Validate that a CSS color value is one of the recognized safe
	 * shapes before it lands in a `<style>` block. Even though the
	 * editor is an authenticated surface, raw attributes can be
	 * authored by lower-privilege roles and pasted from external
	 * sources — a value like `red;}body{display:none;}` would otherwise
	 * break out of the `color:` declaration and inject arbitrary CSS.
	 *
	 * Allowed shapes (the union of what Gutenberg's Link picker can
	 * emit + what {@see expandPresetReference} produces):
	 * - `#abc`, `#aabbcc`, `#aabbccdd` hex literals
	 * - `rgb(...)` / `rgba(...)` / `hsl(...)` / `hsla(...)` functional
	 *   notation containing only digits, commas, spaces, percents,
	 *   slashes (CSS Color 4 syntax), decimal points, and `deg`/`%`
	 * - `var(--…)` references (the path we land on after preset
	 *   expansion)
	 * - bare CSS color keywords (`red`, `transparent`, `currentColor`,
	 *   etc.) — letters only
	 *
	 * Anything outside those shapes drops to `''` so the caller skips
	 * the declaration entirely.
	 *
	 * @since 1.0.0
	 */
	protected static function sanitizeColorValue( string $value ): string
	{
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		// Hex.
		if ( 1 === preg_match( '/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value ) ) {
			return $value;
		}

		// rgb()/rgba()/hsl()/hsla() — restrict the argument list to a
		// safe charset so a closing `)` is the only way out.
		if ( 1 === preg_match( '/^(?:rgb|rgba|hsl|hsla)\(\s*[0-9eE.,%\s\/+-]+\s*\)$/', $value ) ) {
			return $value;
		}

		// var(--...) — restrict to the conservative custom-property
		// alphabet ({alnum, -, _}) plus an optional fallback that is
		// itself a safe color (recursive single hop).
		if ( 1 === preg_match( '/^var\(\s*--[A-Za-z0-9_-]+\s*\)$/', $value ) ) {
			return $value;
		}

		// CSS color keyword — letters only (`red`, `currentColor`,
		// `transparent`, etc.).
		if ( 1 === preg_match( '/^[A-Za-z]+$/', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Expand Gutenberg's `var:preset|{taxonomy}|{slug}` shorthand into
	 * a real CSS `var(--wp--preset--{taxonomy}--{slug})` reference.
	 * Non-preset values pass through untouched. Duplicated here from
	 * {@see BlockSupports::expandPresetReference} rather than reaching
	 * across class boundaries — both compilers are entry points used
	 * independently and the helper is a couple of lines.
	 *
	 * @since 1.0.0
	 */
	protected static function expandPresetReference( string $value ): string
	{
		if ( ! str_starts_with( $value, 'var:preset|' ) ) {
			return $value;
		}

		$parts = explode( '|', substr( $value, strlen( 'var:preset|' ) ) );
		$parts = array_map( static fn ( string $segment ): string => self::kebabCase( $segment ), $parts );

		return 'var(--wp--preset--' . implode( '--', $parts ) . ')';
	}

	/**
	 * Coerce an arbitrary attribute value to a trimmed string. Mirrors
	 * the behavior of {@see BlockSupports::stringAttr}.
	 *
	 * @since 1.0.0
	 */
	protected static function stringAttr( mixed $value ): string
	{
		if ( is_string( $value ) ) {
			return trim( $value );
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		return '';
	}

	/**
	 * Convert `camelCase` → `kebab-case` for preset taxonomy / slug
	 * segments. Mirrors {@see BlockSupports::kebabCase}.
	 *
	 * @since 1.0.0
	 */
	protected static function kebabCase( string $value ): string
	{
		$converted = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $value );

		return strtolower( (string) $converted );
	}
}
