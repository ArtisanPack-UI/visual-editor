<?php

/**
 * Block-supports compiler — Keystone #50.
 *
 * Walks a block's attribute tree and produces the canonical wrapper
 * classes + inline style declarations that WordPress core's
 * `apply_block_supports()` would emit server-side. Each Blade block
 * partial calls {@see compile} and merges the result with its own
 * block-specific classes (e.g. `wp-block-group`, `is-layout-flex`).
 *
 * Coverage parity with WordPress core block-supports modules:
 * - `align`        → `align{wide|full|left|center|right}` class
 * - `color`        → `backgroundColor`, `textColor`, `gradient`
 *                    palette slugs as `has-{slug}-*` classes plus the
 *                    `has-background` / `has-text-color` markers;
 *                    custom values from `style.color.*` as inline
 *                    declarations + the same marker classes.
 * - `spacing`      → `style.spacing.padding` / `style.spacing.margin`
 *                    (string or per-side object) and `blockGap`.
 * - `border`       → radius (string or per-corner object), color,
 *                    style, width; plus per-side
 *                    `style.border.{top|right|bottom|left}` objects.
 *                    Palette slugs via `borderColor`.
 * - `typography`   → custom values from `style.typography.*`;
 *                    palette slugs from `fontSize` / `fontFamily`.
 * - `align (text)` → `textAlign` attribute → inline `text-align`.
 * - `className`    → user-supplied class string appended verbatim.
 * - `anchor`       → `attributes.anchor` lifted into the `id` slot.
 *
 * Preset references emitted by Gutenberg as `var:preset|{path}` are
 * expanded into CSS `var(--wp--preset--{kebab-path})` so that a
 * `style.color.background = "var:preset|color|primary"` lands in the
 * rendered HTML as `background-color: var(--wp--preset--color--primary)`.
 * That mirrors WordPress core's behavior in
 * `_wp_to_kebab_case`-driven serializers.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

class BlockSupports
{
	/**
	 * Recognized values for the block-level `align` attribute. Values
	 * outside this set are dropped to keep stored data from injecting
	 * arbitrary class names into the wrapper.
	 */
	protected const ALIGN_VALUES = [ 'wide', 'full', 'left', 'center', 'right' ];

	/**
	 * Compile a block's attributes into the wrapper class / style / id
	 * triple every Blade partial merges with its own block-specific
	 * classes.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes  Raw block attributes from
	 *                                            the persisted block tree.
	 *
	 * @return array{classes: array<int, string>, style: string, id: ?string}
	 *         `classes` is deduped + ordered. `style` is a
	 *         semicolon-separated declaration list (no leading/trailing
	 *         whitespace; trailing semicolon present iff any declaration
	 *         was emitted). `id` is `null` when no `anchor` attribute is
	 *         set.
	 */
	public static function compile( array $attributes ): array
	{
		$classes = [];
		$style   = [];

		// Some legacy block schemas stored `style` as a string variant
		// id (e.g. `core/separator` shipped with `style: "default"`).
		// Normalize: if `style` isn't an associative array, drop it
		// from the compile pass so the per-category resolvers don't
		// trip on `'default'['color']` lookups. The block partial keeps
		// access to the original string attribute through its own
		// `$attributes` variable.
		if ( isset( $attributes['style'] ) && ! is_array( $attributes['style'] ) ) {
			unset( $attributes['style'] );
		}

		self::applyAlign( $attributes, $classes );
		self::applyTextAlign( $attributes, $classes );
		self::applyColor( $attributes, $classes, $style );
		self::applySpacing( $attributes, $style );
		self::applyBorder( $attributes, $classes, $style );
		self::applyTypography( $attributes, $classes, $style );
		self::applyClassName( $attributes, $classes );

		$id = self::resolveAnchor( $attributes );

		// Stable order: preserve the insertion order produced by the
		// applier methods. `array_unique` keeps the first occurrence,
		// `array_values` flattens the keys for predictable iteration.
		$classes = array_values( array_unique( array_filter( $classes, static fn ( string $class ): bool => '' !== trim( $class ) ) ) );

		$styleString = '' === implode( '', $style ) ? '' : implode( '; ', $style ) . ';';

		return [
			'classes' => $classes,
			'style'   => $styleString,
			'id'      => $id,
		];
	}

	/**
	 * Render the wrapper-element attribute string a Blade partial
	 * splices straight into its opening tag:
	 *
	 *     <{{ $tag }} {!! BlockSupports::wrapperAttrs($attributes, ['wp-block-group']) !!}>
	 *
	 * Outputs a leading space when any attribute is emitted (`class="…"
	 * style="…" id="…"`), or an empty string when none are. `$baseClasses`
	 * are prepended verbatim before the compiled support classes so each
	 * block partial can declare its own `wp-block-{name}` / layout class
	 * list without the compiler knowing about them.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 * @param  array<int, string>    $baseClasses
	 */
	public static function wrapperAttrs( array $attributes, array $baseClasses = [] ): string
	{
		$compiled = self::compile( $attributes );

		$classes = array_values( array_unique( array_filter(
			array_merge( $baseClasses, $compiled['classes'] ),
			static fn ( string $class ): bool => '' !== trim( $class ),
		) ) );

		$parts = [];

		if ( [] !== $classes ) {
			$parts[] = sprintf( 'class="%s"', e( implode( ' ', $classes ) ) );
		}

		if ( '' !== $compiled['style'] ) {
			$parts[] = sprintf( 'style="%s"', e( $compiled['style'] ) );
		}

		if ( null !== $compiled['id'] ) {
			$parts[] = sprintf( 'id="%s"', e( $compiled['id'] ) );
		}

		return [] === $parts ? '' : ' ' . implode( ' ', $parts );
	}

	/**
	 * Block-level alignment → `align{value}` class. For the left /
	 * center / right values we ALSO emit `has-text-align-{value}` so
	 * text blocks (paragraph, heading) that key their styling off
	 * Gutenberg's text-alignment class are covered too — WP core
	 * emits both and lets the consuming stylesheet pick the
	 * semantically relevant one based on block type.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 * @param  array<int, string>   &$classes
	 */
	protected static function applyAlign( array $attributes, array &$classes ): void
	{
		$align = $attributes['align'] ?? null;

		if ( ! is_string( $align ) || ! in_array( $align, self::ALIGN_VALUES, true ) ) {
			return;
		}

		$classes[] = 'align' . $align;

		if ( in_array( $align, [ 'left', 'center', 'right' ], true ) ) {
			$classes[] = 'has-text-align-' . $align;
		}
	}

	/**
	 * Heading / paragraph-style `textAlign` attribute → the canonical
	 * `has-text-align-{value}` class. WP core emits a class — not an
	 * inline `text-align: …` rule — so the theme's stylesheet can
	 * pick it up the same way it does on the front-end. `justify` is
	 * allowed alongside left/center/right (matches WP's attribute
	 * enum).
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 * @param  array<int, string>   &$classes
	 */
	protected static function applyTextAlign( array $attributes, array &$classes ): void
	{
		$value = $attributes['textAlign'] ?? null;

		if ( ! is_string( $value ) || ! in_array( $value, [ 'left', 'center', 'right', 'justify' ], true ) ) {
			return;
		}

		$classes[] = 'has-text-align-' . $value;
	}

	/**
	 * Color support: palette-slug references (`backgroundColor`,
	 * `textColor`, `gradient`) become `has-{slug}-*` classes; custom
	 * values under `style.color.*` become inline declarations. In
	 * either case the marker class (`has-background`, `has-text-color`)
	 * is added so theme stylesheets can target the parent without
	 * caring whether the value came from a slug or a custom picker.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 * @param  array<int, string>   &$classes
	 * @param  array<int, string>   &$style
	 */
	protected static function applyColor( array $attributes, array &$classes, array &$style ): void
	{
		$backgroundSlug = self::stringAttr( $attributes['backgroundColor'] ?? null );
		$textSlug       = self::stringAttr( $attributes['textColor'] ?? null );
		$gradientSlug   = self::stringAttr( $attributes['gradient'] ?? null );

		$customBackground = self::stringAttr( $attributes['style']['color']['background'] ?? null );
		$customText       = self::stringAttr( $attributes['style']['color']['text'] ?? null );
		$customGradient   = self::stringAttr( $attributes['style']['color']['gradient'] ?? null );

		// Background — slug wins over custom when both are set (mirrors
		// WP core, which serializes slug first and uses the custom
		// value as a fallback when no slug is present).
		if ( '' !== $backgroundSlug ) {
			$classes[] = 'has-' . self::slugify( $backgroundSlug ) . '-background-color';
			$classes[] = 'has-background';
		} elseif ( '' !== $customBackground ) {
			$style[]   = 'background-color: ' . self::expandPresetReference( $customBackground );
			$classes[] = 'has-background';
		}

		// Gradient — same priority story; gradient marker reuses
		// `has-background` because WP groups them.
		if ( '' !== $gradientSlug ) {
			$classes[] = 'has-' . self::slugify( $gradientSlug ) . '-gradient-background';
			$classes[] = 'has-background';
		} elseif ( '' !== $customGradient ) {
			$style[]   = 'background: ' . self::expandPresetReference( $customGradient );
			$classes[] = 'has-background';
		}

		// Text color.
		if ( '' !== $textSlug ) {
			$classes[] = 'has-' . self::slugify( $textSlug ) . '-color';
			$classes[] = 'has-text-color';
		} elseif ( '' !== $customText ) {
			$style[]   = 'color: ' . self::expandPresetReference( $customText );
			$classes[] = 'has-text-color';
		}
	}

	/**
	 * Spacing support: padding / margin (string or per-side object) +
	 * `blockGap` (string or per-axis object → `--wp--style--block-gap`
	 * custom property).
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 * @param  array<int, string>   &$style
	 */
	protected static function applySpacing( array $attributes, array &$style ): void
	{
		$spacing = $attributes['style']['spacing'] ?? null;

		if ( ! is_array( $spacing ) ) {
			return;
		}

		foreach ( [ 'padding', 'margin' ] as $box ) {
			self::applyBoxModel( $box, $spacing[ $box ] ?? null, $style );
		}

		$blockGap = $spacing['blockGap'] ?? null;

		if ( is_string( $blockGap ) && '' !== $blockGap ) {
			$style[] = '--wp--style--block-gap: ' . self::expandPresetReference( $blockGap );
		} elseif ( is_array( $blockGap ) ) {
			$top  = self::stringAttr( $blockGap['top'] ?? null );
			$left = self::stringAttr( $blockGap['left'] ?? null );

			if ( '' !== $top ) {
				$style[] = 'row-gap: ' . self::expandPresetReference( $top );
			}

			if ( '' !== $left ) {
				$style[] = 'column-gap: ' . self::expandPresetReference( $left );
			}
		}
	}

	/**
	 * Emit `padding-*` or `margin-*` declarations from either a
	 * single-string value (all four sides) or a per-side associative
	 * array.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, string>  &$style
	 */
	protected static function applyBoxModel( string $box, mixed $value, array &$style ): void
	{
		if ( is_string( $value ) && '' !== $value ) {
			$style[] = $box . ': ' . self::expandPresetReference( $value );

			return;
		}

		if ( ! is_array( $value ) ) {
			return;
		}

		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			$side_value = self::stringAttr( $value[ $side ] ?? null );

			if ( '' === $side_value ) {
				continue;
			}

			$style[] = sprintf( '%s-%s: %s', $box, $side, self::expandPresetReference( $side_value ) );
		}
	}

	/**
	 * Border support — radius (string or per-corner object), per-side
	 * style/width/color (object), and the palette slug
	 * `borderColor` → `has-{slug}-border-color has-border-color`.
	 *
	 * Border `style` / `width` declared at the root force an explicit
	 * `border-style: solid` baseline so that a width-only value (e.g.
	 * `style.border.width = "2px"`) actually renders the border —
	 * matching WP core which adds the same fallback when serializing.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 * @param  array<int, string>   &$classes
	 * @param  array<int, string>   &$style
	 */
	protected static function applyBorder( array $attributes, array &$classes, array &$style ): void
	{
		$borderColorSlug = self::stringAttr( $attributes['borderColor'] ?? null );

		if ( '' !== $borderColorSlug ) {
			$classes[] = 'has-border-color';
			$classes[] = 'has-' . self::slugify( $borderColorSlug ) . '-border-color';
		}

		$border = $attributes['style']['border'] ?? null;

		if ( ! is_array( $border ) ) {
			return;
		}

		// Radius can be a single value (all corners) or an object
		// with per-corner keys.
		$radius = $border['radius'] ?? null;

		if ( is_string( $radius ) && '' !== $radius ) {
			$style[] = 'border-radius: ' . self::expandPresetReference( $radius );
		} elseif ( is_array( $radius ) ) {
			foreach ( [ 'topLeft', 'topRight', 'bottomLeft', 'bottomRight' ] as $corner ) {
				$value = self::stringAttr( $radius[ $corner ] ?? null );

				if ( '' === $value ) {
					continue;
				}

				$style[] = sprintf( 'border-%s-radius: %s', self::kebabCase( $corner ), self::expandPresetReference( $value ) );
			}
		}

		$hasRootWidthOrStyle = false;

		foreach ( [ 'color', 'style', 'width' ] as $property ) {
			$value = self::stringAttr( $border[ $property ] ?? null );

			if ( '' === $value ) {
				continue;
			}

			$style[] = 'border-' . $property . ': ' . self::expandPresetReference( $value );

			if ( 'width' === $property || 'style' === $property ) {
				$hasRootWidthOrStyle = true;
			}
		}

		// Per-side overrides.
		$hasSideWidthOrStyle = false;

		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			$sideValues = $border[ $side ] ?? null;

			if ( ! is_array( $sideValues ) ) {
				continue;
			}

			foreach ( [ 'color', 'style', 'width' ] as $property ) {
				$value = self::stringAttr( $sideValues[ $property ] ?? null );

				if ( '' === $value ) {
					continue;
				}

				$style[] = sprintf( 'border-%s-%s: %s', $side, $property, self::expandPresetReference( $value ) );

				if ( 'width' === $property || 'style' === $property ) {
					$hasSideWidthOrStyle = true;
				}
			}
		}

		// Width without an explicit style would render nothing in some
		// browsers — fall back to `solid` so the configured width is
		// actually visible. WP core does the same.
		if ( ( $hasRootWidthOrStyle || $hasSideWidthOrStyle ) && ! self::styleListContains( $style, 'border-style:' ) ) {
			$style[] = 'border-style: solid';
		}
	}

	/**
	 * Typography support: palette slugs (`fontSize`, `fontFamily`)
	 * become `has-*` classes; custom values under `style.typography.*`
	 * become inline declarations covering font size, family, weight,
	 * style, line height, letter spacing, text transform, and text
	 * decoration.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 * @param  array<int, string>   &$classes
	 * @param  array<int, string>   &$style
	 */
	protected static function applyTypography( array $attributes, array &$classes, array &$style ): void
	{
		$fontSizeSlug = self::stringAttr( $attributes['fontSize'] ?? null );

		if ( '' !== $fontSizeSlug ) {
			$classes[] = 'has-' . self::slugify( $fontSizeSlug ) . '-font-size';
			$classes[] = 'has-defined-font-size';
		}

		$fontFamilySlug = self::stringAttr( $attributes['fontFamily'] ?? null );

		if ( '' !== $fontFamilySlug ) {
			$classes[] = 'has-' . self::slugify( $fontFamilySlug ) . '-font-family';
		}

		$typography = $attributes['style']['typography'] ?? null;

		if ( ! is_array( $typography ) ) {
			return;
		}

		$mappings = [
			'fontSize'       => 'font-size',
			'fontFamily'     => 'font-family',
			'fontWeight'     => 'font-weight',
			'fontStyle'      => 'font-style',
			'lineHeight'     => 'line-height',
			'letterSpacing'  => 'letter-spacing',
			'textTransform'  => 'text-transform',
			'textDecoration' => 'text-decoration',
		];

		foreach ( $mappings as $attrKey => $cssProperty ) {
			// Slug-over-custom precedence — matches the color path's
			// behavior (CodeRabbit on PR #457). When `fontSize` /
			// `fontFamily` slugs are set, the `has-{slug}-*` class is
			// authoritative and the inline `style.typography.{key}`
			// value is ignored. Without this guard both would emit;
			// the inline style would win the cascade, contradicting
			// the documented priority.
			if ( 'fontSize' === $attrKey && '' !== $fontSizeSlug ) {
				continue;
			}

			if ( 'fontFamily' === $attrKey && '' !== $fontFamilySlug ) {
				continue;
			}

			$value = self::stringAttr( $typography[ $attrKey ] ?? null );

			if ( '' === $value ) {
				continue;
			}

			$style[] = $cssProperty . ': ' . self::expandPresetReference( $value );
		}
	}

	/**
	 * Append the user-supplied `className` attribute verbatim. Each
	 * whitespace-separated token is added as its own class so the
	 * deduplication pass {@see compile} runs covers them.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 * @param  array<int, string>   &$classes
	 */
	protected static function applyClassName( array $attributes, array &$classes ): void
	{
		$value = self::stringAttr( $attributes['className'] ?? null );

		if ( '' === $value ) {
			return;
		}

		foreach ( preg_split( '/\s+/', $value ) ?: [] as $token ) {
			$token = trim( $token );

			if ( '' === $token ) {
				continue;
			}

			$classes[] = $token;
		}
	}

	/**
	 * Resolve the `anchor` attribute into the `id` slot. Filter out
	 * characters that would let an authored anchor break out of the
	 * attribute (`"`, `'`, `<`, `>`, whitespace) — server-side trust
	 * is on the editor not to write bad values, but cheap to enforce
	 * at the boundary.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 */
	protected static function resolveAnchor( array $attributes ): ?string
	{
		$anchor = self::stringAttr( $attributes['anchor'] ?? null );

		if ( '' === $anchor ) {
			return null;
		}

		$sanitized = preg_replace( '/[^A-Za-z0-9_:.\-]/', '', $anchor );

		return ( null === $sanitized || '' === $sanitized ) ? null : $sanitized;
	}

	/**
	 * Expand Gutenberg's `var:preset|{taxonomy}|{slug}` shorthand into
	 * a real CSS `var(--wp--preset--{taxonomy}--{slug})` reference. A
	 * non-preset value passes through untouched.
	 *
	 * @since 1.1.0
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
	 * Coerce an arbitrary attribute value to a trimmed string. `null`,
	 * arrays, and non-scalar types collapse to `''` so callers can
	 * treat the result as "value is unset".
	 *
	 * @since 1.1.0
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
	 * Slugify the user-supplied palette slug so a custom slug like
	 * `Primary Accent` lands as `primary-accent` in the class list.
	 * Mirrors WP core's behavior for class generation.
	 *
	 * @since 1.1.0
	 */
	protected static function slugify( string $value ): string
	{
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );
		$value = trim( (string) $value, '-' );

		return $value;
	}

	/**
	 * Convert `topLeft` → `top-left` for border-corner CSS property
	 * names. Single-pass regex; no need to handle leading caps because
	 * the input is always a fixed enum.
	 *
	 * @since 1.1.0
	 */
	protected static function kebabCase( string $value ): string
	{
		$converted = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $value );

		return strtolower( (string) $converted );
	}

	/**
	 * Check the accumulated style list for an existing declaration
	 * starting with `$prefix`. Used by the border applier to avoid
	 * stomping a user-supplied `border-style` with the automatic
	 * `solid` fallback.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, string>  $style
	 */
	protected static function styleListContains( array $style, string $prefix ): bool
	{
		foreach ( $style as $declaration ) {
			if ( str_starts_with( $declaration, $prefix ) ) {
				return true;
			}
		}

		return false;
	}
}
