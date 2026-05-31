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

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;

class BlockSupports
{
	/**
	 * Path → CSS property mapping for responsive overrides. Paths
	 * outside this map are ignored by the generic emitter; blocks
	 * with bespoke layout concerns (e.g. `artisanpack/columns` and
	 * its `columnCount`) handle their own responsive paths inside
	 * the partial.
	 */
	protected const RESPONSIVE_CSS_PROPERTY_MAP = [
		'style.spacing.padding'  => 'padding',
		'style.spacing.margin'   => 'margin',
		'style.spacing.blockGap' => 'gap',
		'style.border.radius'    => 'border-radius',
		'style.border.width'     => 'border-width',
		'style.border.style'     => 'border-style',
		'style.border.color'     => 'border-color',
		// `width` is intentionally absent. The artisanpack/column block
		// translates its `width` attribute to a `flex-basis` declaration
		// (not `width`), and the WP core `.wp-block-columns:not(.is-not-stacked-on-mobile) > .wp-block-column { flex-basis: 100% !important }`
		// stacking rule only loses to another `flex-basis` rule of
		// equal-or-greater specificity. The column partial handles
		// `responsive.width` itself (see column.blade.php) — same
		// pattern as columns.blade.php handles `responsive.columnCount`.
	];

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

		$responsive = self::compileResponsive( $attributes );

		if ( '' !== $responsive['class'] ) {
			$classes[] = $responsive['class'];

			// Push into the per-request accumulator so a single
			// `<style data-ve-responsive>` block at the top of the
			// render output picks up every block's rules. The push is
			// keyed by scope class so the same payload on N siblings
			// only emits once.
			self::pushResponsive( $responsive['class'], $responsive['rules'] );
		}

		return [
			'classes'         => $classes,
			'style'           => $styleString,
			'id'              => $id,
			'responsiveCss'   => '',
			'responsiveClass' => $responsive['class'],
			'responsiveRules' => $responsive['rules'],
		];
	}

	/**
	 * Push a scope's rules into the request-scoped accumulator so the
	 * consolidated `<style data-ve-responsive>` block at the top of the
	 * render output picks them up. Called automatically from compile();
	 * the columns/column partial helpers below call it too for their
	 * own bespoke rules (columnCount, flex-basis).
	 *
	 * @since 1.0.0
	 */
	public static function pushResponsive( string $scope, string $rules ): void
	{
		if ( '' === $scope || '' === $rules ) {
			return;
		}

		if ( ! function_exists( 'app' ) ) {
			return;
		}

		try {
			app( ResponsiveCssAccumulator::class )->push( $scope, $rules );
		} catch ( \Throwable $e ) {
			// Container not booted (very-early call path or a unit
			// test that exercises BlockSupports without the package
			// service provider). Silently drop — the call is
			// idempotent and the accumulator is the side channel,
			// not the data path.
		}
	}

	/**
	 * Walk the `attributes.responsive` payload and emit a scoped
	 * `<style>` block that overrides the base wrapper styles at each
	 * declared breakpoint.
	 *
	 * The payload is path-keyed (`{ "style.spacing.padding": { md: "2rem" } }`
	 * — same shape the `withResponsiveAttributes` editor HOC writes).
	 * Paths in {@see self::RESPONSIVE_CSS_PROPERTY_MAP} are handled by
	 * the generic CSS emitter; everything else (e.g. the columns
	 * block's `columnCount` override) is the partial's responsibility.
	 *
	 * Returns `{class, rules}` — the class merges into the wrapper
	 * class list so the emitted `@media` rules target it; `rules` is
	 * the bare CSS body (no surrounding `<style>` tag) that gets
	 * pushed into the per-request {@see ResponsiveCssAccumulator}.
	 * Both are empty when no actionable overrides are present.
	 *
	 * #509 — consolidated emission. compile() automatically pushes
	 * these rules into the accumulator, and `<x-ve-blocks>` /
	 * `<x-ve-template>` drain the accumulator into one
	 * `<style data-ve-responsive>` block at the top of the render
	 * output. Per-block partials no longer emit their own `<style>`
	 * tags inline.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array{class: string, rules: string}
	 */
	public static function compileResponsive( array $attributes ): array
	{
		$responsive = $attributes['responsive'] ?? null;

		if ( ! is_array( $responsive ) || [] === $responsive ) {
			return [ 'class' => '', 'rules' => '' ];
		}

		$registry = BreakpointRegistry::fromLayers();
		$rules    = [];
		$scope    = self::generateResponsiveScopeClass( $responsive );

		// Tripled scope class boosts the rule's specificity to
		// (0,0,3,0), matching WP core's own
		// `.wp-block-columns:not(.is-not-stacked-on-mobile) > .wp-block-column`
		// rule. Both rules then carry `!important`, so source order
		// decides — and this `<style>` block is emitted right before
		// the wrapper, after every WP core stylesheet, so we win.
		$selector = sprintf( '.%1$s.%1$s.%1$s', $scope );

		foreach ( $responsive as $path => $overrides ) {
			if ( ! is_array( $overrides ) ) {
				continue;
			}

			if ( ! isset( self::RESPONSIVE_CSS_PROPERTY_MAP[ $path ] ) ) {
				continue;
			}

			$property = self::RESPONSIVE_CSS_PROPERTY_MAP[ $path ];

			foreach ( $overrides as $breakpoint => $value ) {
				if ( null === $value || '' === $value ) {
					continue;
				}

				$declarations = self::responsiveDeclarations( $property, $value );

				if ( '' === $declarations ) {
					continue;
				}

				if ( BreakpointRegistry::BASE_KEY === $breakpoint ) {
					$rules[] = sprintf( '%s{%s}', $selector, $declarations );

					continue;
				}

				$minWidth = $registry->get( (string) $breakpoint );

				if ( null === $minWidth ) {
					continue;
				}

				$rules[] = sprintf(
					'@media (min-width:%dpx){%s{%s}}',
					$minWidth,
					$selector,
					$declarations
				);
			}
		}

		if ( [] === $rules ) {
			return [ 'class' => '', 'rules' => '' ];
		}

		return [
			'class' => $scope,
			'rules' => implode( '', $rules ),
		];
	}

	/**
	 * Turn a single override value into one or more CSS declarations.
	 * Scalars become `property: value`. Per-side objects (the shape
	 * Gutenberg uses for spacing/border: `{top, right, bottom, left}`)
	 * become per-side declarations (`padding-top: ...`, etc.). The
	 * resulting declaration list is `;`-joined with no trailing
	 * semicolon — callers add the wrapping `{}` and any trailing `;`
	 * they need.
	 *
	 * Every declaration is suffixed with `!important` because the
	 * block-supports compiler also writes scalar values into the
	 * wrapper's inline `style="…"` attribute (Gutenberg's default
	 * shape for padding/margin/border). Inline styles always beat
	 * class-based rules on specificity, so without `!important` the
	 * per-breakpoint overrides emit but never apply on the front end.
	 * #509 (consolidated emission) will keep the same suffix.
	 */
	protected static function responsiveDeclarations( string $property, $value ): string
	{
		if ( is_scalar( $value ) ) {
			return $property . ':' . self::resolvePresetVar( (string) $value ) . '!important';
		}

		if ( ! is_array( $value ) ) {
			return '';
		}

		$pieces = [];

		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			if ( ! isset( $value[ $side ] ) || null === $value[ $side ] || '' === $value[ $side ] ) {
				continue;
			}

			$pieces[] = sprintf(
				'%s-%s:%s!important',
				$property,
				$side,
				self::resolvePresetVar( (string) $value[ $side ] )
			);
		}

		return implode( ';', $pieces );
	}

	/**
	 * `var:preset|color|primary` → `var(--wp--preset--color--primary)`,
	 * mirroring Gutenberg's serializer. Plain values pass through.
	 */
	protected static function resolvePresetVar( string $value ): string
	{
		if ( ! str_starts_with( $value, 'var:preset|' ) ) {
			return $value;
		}

		$segments = explode( '|', substr( $value, strlen( 'var:preset|' ) ) );
		$slug     = implode( '--', array_map(
			static fn ( string $segment ): string => str_replace( '_', '-', $segment ),
			$segments
		) );

		return sprintf( 'var(--wp--preset--%s)', $slug );
	}

	/**
	 * Generates a stable, content-derived class so the same responsive
	 * payload re-renders to the same scope (cache-friendly + idempotent).
	 */
	protected static function generateResponsiveScopeClass( array $responsive ): string
	{
		$hash = substr(
			hash( 'xxh3', (string) json_encode( $responsive ) ),
			0,
			10
		);

		return 've-r-' . $hash;
	}

	/**
	 * Back-compat shim. Previously emitted a per-block `<style>` tag
	 * before the wrapper; now a no-op because compile() pushes the
	 * rules into the per-request accumulator and
	 * `<x-ve-blocks>` / `<x-ve-template>` drain it into one
	 * consolidated `<style data-ve-responsive>` block at the top of
	 * the render output. Kept so partials calling
	 * `{!! BlockSupports::wrapperCss( $attributes ) !!}` continue to
	 * compile — the call becomes harmless.
	 *
	 * Forked or host-app partials that referenced this should drop
	 * the line; nothing else needs to change.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @deprecated 1.0.0 — accumulator-based emission supersedes per-block tags.
	 */
	public static function wrapperCss( array $attributes ): string
	{
		// Trigger the side-effect (accumulator push) for partials
		// that don't go through compile() — though every shipped
		// partial does. Keeps behavior consistent if a host calls
		// wrapperCss() without first calling wrapperAttrs().
		$responsive = self::compileResponsive( $attributes );

		if ( '' !== $responsive['class'] ) {
			self::pushResponsive( $responsive['class'], $responsive['rules'] );
		}

		return '';
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
		// Pre-`supports.typography.textAlign` blocks stored the value
		// at the top level. Newer blocks (paragraph, heading, list,
		// quote, …) declare the support via block.json typography and
		// the editor stores it under `style.typography.textAlign`.
		// Check both — top-level wins if both are present (matches the
		// editor's serialization precedence).
		$value = $attributes['textAlign']
			?? ( $attributes['style']['typography']['textAlign'] ?? null );

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

		// Per-side overrides. Track which sides need a per-side
		// style fallback so a side-only `width` doesn't end up
		// triggering a global `border-style: solid` that turns on
		// the untouched edges with browser-default widths
		// (CodeRabbit on PR #457: `style.border.top.width: 2px`
		// should produce a single top border, not a full box).
		$sideNeedsStyleFallback = [];

		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			$sideValues = $border[ $side ] ?? null;

			if ( ! is_array( $sideValues ) ) {
				continue;
			}

			$sideHasWidth         = false;
			$sideHasExplicitStyle = false;

			foreach ( [ 'color', 'style', 'width' ] as $property ) {
				$value = self::stringAttr( $sideValues[ $property ] ?? null );

				if ( '' === $value ) {
					continue;
				}

				$style[] = sprintf( 'border-%s-%s: %s', $side, $property, self::expandPresetReference( $value ) );

				if ( 'width' === $property ) {
					$sideHasWidth = true;
				}

				if ( 'style' === $property ) {
					$sideHasExplicitStyle = true;
				}
			}

			if ( $sideHasWidth && ! $sideHasExplicitStyle ) {
				$sideNeedsStyleFallback[] = $side;
			}
		}

		// Root-level fallback only when the ROOT declaration set a
		// width or style. Width without an explicit style would
		// render nothing in some browsers, so default to `solid` —
		// matches WP core.
		if ( $hasRootWidthOrStyle && ! self::styleListContains( $style, 'border-style:' ) ) {
			$style[] = 'border-style: solid';
		}

		// Side-specific fallbacks — only the sides that actually
		// have a `width` without a matching `style` get a per-side
		// `border-{side}-style: solid`. Doesn't touch the other
		// three edges.
		foreach ( $sideNeedsStyleFallback as $side ) {
			if ( ! self::styleListContains( $style, 'border-' . $side . '-style:' ) ) {
				$style[] = 'border-' . $side . '-style: solid';
			}
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
