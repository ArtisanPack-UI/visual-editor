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
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

use ArtisanPackUI\VisualEditor\BoxShadow\BoxShadowEmitter;
use ArtisanPackUI\VisualEditor\BoxShadow\BoxShadowResolver;
use ArtisanPackUI\VisualEditor\GradientBorder\GradientBorderEmitter;
use ArtisanPackUI\VisualEditor\GradientBorder\GradientBorderResolver;
use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\States\StateCssEmitter;
use ArtisanPackUI\VisualEditor\States\StateRegistry;
use ArtisanPackUI\VisualEditor\States\StateValueResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\BoxShadowCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GradientBorderCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\StateCssAccumulator;

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
	 * @since 1.0.0
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

		$states = self::compileStates( $attributes );

		if ( '' !== $states['class'] ) {
			$classes[] = $states['class'];

			self::pushStates( $states['class'], $states['rules'] );
		}

		$gradientBorder = self::compileGradientBorder( $attributes );

		if ( '' !== $gradientBorder['class'] ) {
			$classes[] = $gradientBorder['class'];

			self::pushGradientBorder( $gradientBorder['class'], $gradientBorder['rules'] );
		}

		$boxShadow = self::compileBoxShadow( $attributes );

		if ( '' !== $boxShadow['class'] ) {
			$classes[] = $boxShadow['class'];

			self::pushBoxShadow( $boxShadow['class'], $boxShadow['rules'] );
		}

		return [
			'classes'             => $classes,
			'style'               => $styleString,
			'id'                  => $id,
			'responsiveCss'       => '',
			'responsiveClass'     => $responsive['class'],
			'responsiveRules'     => $responsive['rules'],
			'statesClass'         => $states['class'],
			'statesRules'         => $states['rules'],
			'gradientBorderClass' => $gradientBorder['class'],
			'gradientBorderRules' => $gradientBorder['rules'],
			'boxShadowClass'      => $boxShadow['class'],
			'boxShadowRules'      => $boxShadow['rules'],
		];
	}

	/**
	 * Resolve the request-scoped breakpoint registry from the
	 * container, falling back to package defaults when the container
	 * isn't booted (very-early call path / unit-test isolation).
	 *
	 * @since 1.0.0
	 */
	protected static function resolveRegistry(): BreakpointRegistry
	{
		try {
			return app( BreakpointRegistry::class );
		} catch ( \Throwable $e ) {
			return BreakpointRegistry::fromLayers();
		}
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
	 * Mirror of {@see pushResponsive} for state design tools (#488).
	 *
	 * @since 1.0.0
	 */
	public static function pushStates( string $scope, string $rules ): void
	{
		if ( '' === $scope || '' === $rules ) {
			return;
		}

		if ( ! function_exists( 'app' ) ) {
			return;
		}

		try {
			app( StateCssAccumulator::class )->push( $scope, $rules );
		} catch ( \Throwable $e ) {
			// See pushResponsive() — same drop-silently rationale.
		}
	}

	/**
	 * Mirror of {@see pushResponsive} for gradient borders (#490).
	 *
	 * @since 1.1.0
	 */
	public static function pushGradientBorder( string $scope, string $rules ): void
	{
		if ( '' === $scope || '' === $rules ) {
			return;
		}

		if ( ! function_exists( 'app' ) ) {
			return;
		}

		try {
			app( GradientBorderCssAccumulator::class )->push( $scope, $rules );
		} catch ( \Throwable $e ) {
			// See pushResponsive() — same drop-silently rationale.
		}
	}

	/**
	 * Mirror of {@see pushResponsive} for box shadows (#607).
	 *
	 * @since 1.2.0
	 */
	public static function pushBoxShadow( string $scope, string $rules ): void
	{
		if ( '' === $scope || '' === $rules ) {
			return;
		}

		if ( ! function_exists( 'app' ) ) {
			return;
		}

		try {
			app( BoxShadowCssAccumulator::class )->push( $scope, $rules );
		} catch ( \Throwable $e ) {
			// See pushResponsive() — same drop-silently rationale.
		}
	}

	/**
	 * Split {@see compile}'s class / style output into a `background`
	 * bucket and a `wrapper` bucket (#583).
	 *
	 * Most blocks paint their background on the same element that
	 * carries the wrapper class — so {@see wrapperAttrs} just merges
	 * everything onto that element and the partial never needs to
	 * worry about routing. The `core/cover` block is the exception:
	 * the painted surface is the `wp-block-cover__background` overlay
	 * span layered on top of the image, not the outer `<div>`. Background
	 * classes / inline declarations applied to the wrapper sit behind
	 * the image and never render. This helper carves the
	 * background-affecting output out of `compile`'s return so the
	 * cover partial can route it onto the overlay while keeping
	 * everything else (layout, alignment, text color, anchor, border,
	 * spacing, typography, animations) on the wrapper.
	 *
	 * Classes routed to the `background` bucket:
	 * - `has-background` marker.
	 * - `has-{slug}-background-color` (palette background slug).
	 * - `has-{slug}-gradient-background` (palette gradient slug).
	 *
	 * Inline declarations routed to the `background` bucket:
	 * - `background-color: ...`
	 * - `background-image: ...`
	 * - `background: ...` (shorthand / custom gradient).
	 *
	 * Every other class / declaration is kept in the `wrapper` bucket
	 * untouched.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, string>  $classes  `compile`'s `classes` return.
	 * @param  string              $style    `compile`'s `style` return
	 *                                       (semicolon-separated
	 *                                       declaration list).
	 *
	 * @return array{
	 *     background: array{classes: array<int, string>, style: string},
	 *     wrapper:    array{classes: array<int, string>, style: string},
	 * }
	 */
	public static function splitBackgroundOutput( array $classes, string $style ): array
	{
		$backgroundClasses = [];
		$wrapperClasses    = [];

		foreach ( $classes as $class ) {
			if ( ! is_string( $class ) ) {
				continue;
			}

			if ( self::isBackgroundClass( $class ) ) {
				$backgroundClasses[] = $class;

				continue;
			}

			$wrapperClasses[] = $class;
		}

		[ $backgroundStyle, $wrapperStyle ] = self::partitionBackgroundStyle( $style );

		return [
			'background' => [
				'classes' => $backgroundClasses,
				'style'   => $backgroundStyle,
			],
			'wrapper'    => [
				'classes' => $wrapperClasses,
				'style'   => $wrapperStyle,
			],
		];
	}

	/**
	 * Compile the cover block's overlay-specific color / gradient
	 * attributes into a `{classes, style}` bundle the cover partial
	 * merges into the overlay span (#583).
	 *
	 * The cover block stores its overlay color under bespoke top-level
	 * attribute names (`overlayColor`, `customOverlayColor`,
	 * `customGradient`) instead of the generic `backgroundColor` /
	 * `style.color.background` slots that {@see compile} understands.
	 * Without this helper those values land on neither the wrapper nor
	 * the overlay — they're silently dropped — because `compile`
	 * doesn't look at the cover-only attribute names. The `gradient`
	 * palette slug shares its attribute name with the generic block-
	 * supports input, so it already flows through `compile` → split →
	 * overlay and is intentionally NOT re-emitted here.
	 *
	 * Precedence (matches WordPress core's `render_block_core_cover`):
	 * palette `overlayColor` wins over `customOverlayColor`; the
	 * `customGradient` declaration layers on top of either color
	 * because `background` shorthand and `background-color` map to
	 * different cascade slots on the overlay span.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array{classes: array<int, string>, style: string}
	 */
	public static function compileCoverOverlay( array $attributes ): array
	{
		$classes = [];
		$style   = [];

		$overlaySlug      = self::stringAttr( $attributes['overlayColor'] ?? null );
		$customOverlay    = self::stringAttr( $attributes['customOverlayColor'] ?? null );
		$customGradient   = self::stringAttr( $attributes['customGradient'] ?? null );

		if ( '' !== $overlaySlug ) {
			$classes[] = 'has-' . self::slugify( $overlaySlug ) . '-background-color';
			$classes[] = 'has-background';
		} elseif ( '' !== $customOverlay ) {
			$style[]   = 'background-color: ' . self::expandPresetReference( $customOverlay );
			$classes[] = 'has-background';
		}

		if ( '' !== $customGradient ) {
			$style[]   = 'background: ' . self::expandPresetReference( $customGradient );
			$classes[] = 'has-background';
		}

		return [
			'classes' => array_values( array_unique( $classes ) ),
			'style'   => [] === $style ? '' : implode( '; ', $style ) . ';',
		];
	}

	/**
	 * Identify the class tokens emitted by {@see applyColor} that
	 * correspond to background painting — the `has-background` marker
	 * and the slug-derived `has-{slug}-background-color` /
	 * `has-{slug}-gradient-background` classes. Text-color classes
	 * (`has-text-color`, `has-{slug}-color`) and every non-color class
	 * fall through to `false` so they stay on the wrapper.
	 *
	 * @since 1.1.0
	 */
	protected static function isBackgroundClass( string $class ): bool
	{
		if ( 'has-background' === $class ) {
			return true;
		}

		return 1 === preg_match(
			'/^has-[a-z0-9](?:[a-z0-9-]*[a-z0-9])?-(?:background-color|gradient-background)$/',
			$class
		);
	}

	/**
	 * Carve `background-color`, `background-image`, and shorthand
	 * `background` declarations out of a semicolon-separated style
	 * string, returning `[backgroundStyle, wrapperStyle]`. Each output
	 * string is `;`-suffixed iff non-empty so callers can splice them
	 * back into a wrapper attribute without re-checking emptiness.
	 *
	 * Declarations with a leading custom-property name (e.g.
	 * `--wp--style--block-gap`) are NOT background output and stay on
	 * the wrapper.
	 *
	 * @since 1.1.0
	 *
	 * @return array{0: string, 1: string}
	 */
	protected static function partitionBackgroundStyle( string $style ): array
	{
		if ( '' === $style ) {
			return [ '', '' ];
		}

		$background = [];
		$wrapper    = [];

		foreach ( explode( ';', rtrim( $style, ';' ) ) as $declaration ) {
			$declaration = trim( $declaration );

			if ( '' === $declaration ) {
				continue;
			}

			if ( self::isBackgroundDeclaration( $declaration ) ) {
				$background[] = $declaration;

				continue;
			}

			$wrapper[] = $declaration;
		}

		$backgroundString = [] === $background ? '' : implode( '; ', $background ) . ';';
		$wrapperString    = [] === $wrapper    ? '' : implode( '; ', $wrapper )    . ';';

		return [ $backgroundString, $wrapperString ];
	}

	/**
	 * Check a single trimmed CSS declaration for a `background-color`,
	 * `background-image`, or shorthand `background` property name. The
	 * match is anchored to the property — declarations with a value
	 * containing the literal `background` keyword (e.g.
	 * `transition: background 200ms`) are left on the wrapper.
	 *
	 * @since 1.1.0
	 */
	protected static function isBackgroundDeclaration( string $declaration ): bool
	{
		$colonPosition = strpos( $declaration, ':' );

		if ( false === $colonPosition ) {
			return false;
		}

		$property = strtolower( rtrim( substr( $declaration, 0, $colonPosition ) ) );

		return 'background' === $property
			|| 'background-color' === $property
			|| 'background-image' === $property;
	}

	/**
	 * Apply the gradient border feature to an already-rendered block's
	 * HTML output (#490).
	 *
	 * Static blocks (Blade partials) get gradient border handling for
	 * free via {@see wrapperAttrs} → {@see compile}. Dynamic blocks
	 * (subclasses of `DynamicBlock`) render their own HTML and never
	 * call into the compile pipeline — so the renderer pipes their
	 * output through this method to push the scope's CSS rule into the
	 * accumulator AND stamp the scope class onto the first opening
	 * tag.
	 *
	 * No-op when the block carries no gradient border configuration at
	 * any cascade level. Idempotent on the class injection — calling
	 * twice with the same scope is harmless (the class is deduped at
	 * the wrapper level by the consumer).
	 *
	 * @since 1.1.0
	 *
	 * @param  string               $html       Block's pre-rendered HTML.
	 * @param  array<string, mixed> $attributes Block's attribute payload
	 *                                          (the same shape `compile`
	 *                                          would receive).
	 */
	public static function applyGradientBorder( string $html, array $attributes ): string
	{
		if ( '' === $html ) {
			return $html;
		}

		$compiled = self::compileGradientBorder( $attributes );

		if ( '' === $compiled['class'] || '' === $compiled['rules'] ) {
			return $html;
		}

		self::pushGradientBorder( $compiled['class'], $compiled['rules'] );

		return self::injectClassIntoFirstTag( $html, $compiled['class'] );
	}

	/**
	 * Merge a single class token into the `class` attribute of the
	 * first opening tag in `$html`. Adds the attribute when missing.
	 * Returns the input unchanged when no opening tag is found (broken
	 * markup, comment-only output, etc.).
	 *
	 * @since 1.1.0
	 */
	protected static function injectClassIntoFirstTag( string $html, string $class ): string
	{
		// Existing class attribute on the first opening tag — append.
		// Case-insensitive (`/i`) so `CLASS` / `Class` variants are
		// still detected, and the quote-style is captured as a back-
		// reference so single AND double quotes are merged (instead of
		// either being missed → the injector falls through to the
		// add-new-attribute branch and writes a SECOND `class`
		// attribute, which is invalid HTML).
		if ( 1 === preg_match( '/^(\s*<[a-zA-Z][^>]*?)\bclass\s*=\s*(["\'])(.*?)\2([^>]*>)/is', $html, $matches ) ) {
			$existing = trim( $matches[3] );
			$tokens   = '' === $existing ? [] : ( preg_split( '/\s+/', $existing ) ?: [] );

			if ( in_array( $class, $tokens, true ) ) {
				return $html;
			}

			$nextClass = '' === $existing ? $class : $existing . ' ' . $class;
			$prefix    = $matches[1] . 'class="' . $nextClass . '"' . $matches[4];

			return $prefix . substr( $html, strlen( $matches[0] ) );
		}

		// No `class` attribute — add one to the first opening tag.
		if ( 1 === preg_match( '/^(\s*<[a-zA-Z][^>]*?)(\/?>)/s', $html, $matches ) ) {
			$prefix = $matches[1] . ' class="' . $class . '"' . $matches[2];

			return $prefix . substr( $html, strlen( $matches[0] ) );
		}

		return $html;
	}

	/**
	 * Compile the `attributes.states` payload into a `{class, rules}`
	 * pair the wrapper can merge in and the accumulator can dedupe (#488).
	 *
	 * The payload shape is path-keyed
	 * (`{ "backgroundColor": { idle: 'a', hover: 'b' }, "_scopeId": 'abc' }` —
	 * same shape `withStateAttributes` writes from the editor). The
	 * `_scopeId` key is reserved for the unique-class housekeeping field
	 * minted by `withStateStyles`.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array{class: string, rules: string}
	 */
	protected static function compileStates( array $attributes ): array
	{
		$bag = $attributes['states'] ?? null;
		if ( ! is_array( $bag ) || [] === $bag ) {
			return [ 'class' => '', 'rules' => '' ];
		}

		// `_scopeId` is interpolated raw into the emitted `<style>` block
		// and into the wrapper's class attribute, so its content has to
		// be a safe identifier-style token. A crafted value containing
		// `<`, `>`, `"`, `'`, whitespace, or other CSS punctuation could
		// otherwise break out of the `<style>` context (XSS) or out of
		// the selector (rule injection). The JS minter only ever produces
		// alphanumeric base-36 strings; anything else is treated as
		// hostile / corrupted and dropped.
		$scopeId = null;
		if ( isset( $bag['_scopeId'] ) && is_string( $bag['_scopeId'] ) ) {
			$candidate = $bag['_scopeId'];
			if ( 1 === preg_match( '/^[a-z0-9][a-z0-9_-]*$/i', $candidate ) && strlen( $candidate ) <= 64 ) {
				$scopeId = $candidate;
			}
		}

		if ( null === $scopeId ) {
			return [ 'class' => '', 'rules' => '' ];
		}

		$paths = $bag;
		unset( $paths['_scopeId'] );

		if ( [] === $paths ) {
			return [ 'class' => '', 'rules' => '' ];
		}

		$bucket = self::normaliseStatePaths( $paths );

		if ( [] === $bucket ) {
			return [ 'class' => '', 'rules' => '' ];
		}

		try {
			$registry = self::resolveStateRegistry();
			$resolver = new StateValueResolver( $registry );
			$emitter  = new StateCssEmitter( $registry, $resolver );

			$scope = '.ap-state-' . $scopeId;
			$css   = $emitter->emit( $scope, $bucket );

			if ( '' === $css ) {
				return [ 'class' => '', 'rules' => '' ];
			}

			// Mirror the JS-side selector cascade so server-rendered
			// markup matches the editor preview: re-emit each rule
			// against descendant interactive elements
			// (`.scope :is(a, button)`).
			$css = self::expandStateSelectors( $css, $scope );

			return [
				'class' => 'ap-state-' . $scopeId,
				'rules' => $css,
			];
		} catch ( \Throwable $e ) {
			return [ 'class' => '', 'rules' => '' ];
		}
	}

	/**
	 * Map the state path → CSS property mapping the JS emitter uses
	 * back to a `{ cssProperty => stateful-value }` bag that the PHP
	 * {@see StateCssEmitter} consumes.
	 *
	 * Palette slugs are translated to `var(--wp--preset--{kind}--{slug})`
	 * so server output matches the editor canvas.
	 *
	 * @param  array<string, mixed>  $paths
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected static function normaliseStatePaths( array $paths ): array
	{
		$config = self::statePathConfig();
		$out    = [];

		foreach ( $paths as $path => $value ) {
			if ( ! is_string( $path ) || ! is_array( $value ) ) {
				continue;
			}

			$normalised = str_starts_with( $path, 'style.' ) ? substr( $path, 6 ) : $path;

			if ( ! isset( $config[ $normalised ] ) ) {
				continue;
			}

			$entry = $config[ $normalised ];

			$converted = [];
			foreach ( $value as $state => $raw ) {
				if ( ! is_string( $state ) ) {
					continue;
				}

				$converted[ $state ] = self::convertStateValue( $raw, $entry );
			}

			if ( [] === $converted ) {
				continue;
			}

			$out[ $entry['property'] ] = $converted;
		}

		return $out;
	}

	/**
	 * @return array<string, array{property: string, preset?: string}>
	 */
	protected static function statePathConfig(): array
	{
		return [
			'backgroundColor'           => [ 'property' => 'background-color', 'preset' => 'color' ],
			'textColor'                 => [ 'property' => 'color',            'preset' => 'color' ],
			'gradient'                  => [ 'property' => 'background',       'preset' => 'gradient' ],
			'color.background'          => [ 'property' => 'background-color' ],
			'color.text'                => [ 'property' => 'color' ],
			'color.gradient'            => [ 'property' => 'background' ],
			'border.color'              => [ 'property' => 'border-color' ],
			'border.width'              => [ 'property' => 'border-width' ],
			'border.style'              => [ 'property' => 'border-style' ],
			'border.radius'             => [ 'property' => 'border-radius' ],
			'shadow'                    => [ 'property' => 'box-shadow' ],
			'typography.textDecoration' => [ 'property' => 'text-decoration' ],
			'dimensions.transform'      => [ 'property' => 'transform' ],
			'transition'                => [ 'property' => 'transition' ],
		];
	}

	/**
	 * @param  array{property: string, preset?: string}  $entry
	 */
	protected static function convertStateValue( mixed $raw, array $entry ): mixed
	{
		if ( null === $raw ) {
			return null;
		}

		if ( ! is_string( $raw ) ) {
			return $raw;
		}

		if ( '' === $raw ) {
			return null;
		}

		if ( isset( $entry['preset'] ) && 1 === preg_match( '/^[a-z0-9][a-z0-9_-]*$/i', $raw ) ) {
			return sprintf( 'var(--wp--preset--%s--%s)', $entry['preset'], $raw );
		}

		return $raw;
	}

	protected static function resolveStateRegistry(): StateRegistry
	{
		try {
			return app( StateRegistry::class );
		} catch ( \Throwable $e ) {
			return StateRegistry::fromLayers();
		}
	}

	/**
	 * Append descendant-element selectors (`scope :is(a, button)`) to
	 * each pseudo-state selector in the emitted CSS so the rules apply
	 * to inner interactive elements (e.g. `wp-block-button__link`).
	 *
	 * The emitter outputs rules in the form
	 * `selector { props } @media (hover: hover) { selector { props } }`.
	 * This rewrite re-emits each pseudo selector as
	 * `selector, selector :is(a, button)`.
	 *
	 * Idle (bare scope) selectors are LEFT UNCHANGED — mirroring there
	 * would leak the block-level idle color into every descendant
	 * link, which is wrong for container blocks like cover and
	 * media-text. The WP `has-{slug}-*` utility classes plus the
	 * block's own root selector already handle the idle case.
	 *
	 * Kept conservative: only matches selectors that start with the
	 * known scope class to avoid mangling unrelated CSS literals.
	 */
	protected static function expandStateSelectors( string $css, string $scope ): string
	{
		$escaped = preg_quote( $scope, '/' );

		return (string) preg_replace_callback(
			'/(' . $escaped . '[^{,]*?)\s*(\{|,)/',
			static function ( array $matches ) use ( $scope ) : string {
				$selector = trim( $matches[1] );
				$delim    = $matches[2];

				if ( '' === $selector || str_contains( $selector, ':is(a, button)' ) ) {
					return $selector . ' ' . $delim;
				}

				// Bare-scope (idle) selectors get no descendant mirror;
				// only pseudo / attribute-extended state selectors do.
				if ( $selector === $scope ) {
					return $selector . ' ' . $delim;
				}

				return $selector . ', ' . $selector . ' :is(a, button) ' . $delim;
			},
			$css
		);
	}

	/**
	 * Compile the `style.border` gradient payload into a `{class, rules}`
	 * pair the wrapper merges in and the accumulator dedupes (#490).
	 *
	 * Returns `{class: '', rules: ''}` when no gradient configuration
	 * is present at any cascade level — callers should treat empty as
	 * a signal to skip both wrapper merge and accumulator push.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array{class: string, rules: string}
	 */
	protected static function compileGradientBorder( array $attributes ): array
	{
		$payload = GradientBorderResolver::resolve( $attributes );

		if ( null === $payload ) {
			return [ 'class' => '', 'rules' => '' ];
		}

		try {
			$states      = self::resolveStateRegistry();
			$breakpoints = function_exists( 'app' )
				? self::resolveRegistry()
				: BreakpointRegistry::fromLayers();

			$emitter = new GradientBorderEmitter( $states, $breakpoints );

			$scopeClass = self::resolveGradientBorderScopeClass( $attributes, $payload );
			$scope      = '.' . $scopeClass;
			$css        = $emitter->emit( $scope, $payload );

			if ( '' === $css ) {
				return [ 'class' => '', 'rules' => '' ];
			}

			return [
				'class' => $scopeClass,
				'rules' => $css,
			];
		} catch ( \Throwable $e ) {
			return [ 'class' => '', 'rules' => '' ];
		}
	}

	/**
	 * Prefer the editor-minted `style.border._gradientScopeId` so the
	 * editor preview and the server-rendered output target the same
	 * scope class. Falls back to a content-derived hash when no id was
	 * stamped (legacy content, hand-authored blocks, server-only
	 * pipelines).
	 *
	 * The minted id is interpolated into both the `<style>` block and
	 * the wrapper's `class` attribute, so its content has to be a
	 * safe identifier-style token — same defense-in-depth check the
	 * state pipeline runs on its `_scopeId`.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 * @param  array<string, mixed>  $payload
	 */
	protected static function resolveGradientBorderScopeClass( array $attributes, array $payload ): string
	{
		$border = $attributes['style']['border'] ?? null;

		if ( is_array( $border ) && isset( $border['_gradientScopeId'] ) && is_string( $border['_gradientScopeId'] ) ) {
			$candidate = $border['_gradientScopeId'];

			if ( 1 === preg_match( '/^[a-z0-9][a-z0-9_-]*$/i', $candidate ) && strlen( $candidate ) <= 64 ) {
				return 've-gb-' . $candidate;
			}
		}

		$hash = substr(
			hash( 'xxh3', (string) json_encode( $payload ) ),
			0,
			10
		);

		return 've-gb-' . $hash;
	}

	/**
	 * Compile the `style.shadow` payload into a `{class, rules}` pair
	 * the wrapper merges in and the accumulator dedupes (#607).
	 *
	 * Returns `{class: '', rules: ''}` when no shadow configuration
	 * is present at any cascade level — callers should treat empty as
	 * a signal to skip both wrapper merge and accumulator push.
	 *
	 * @since 1.2.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array{class: string, rules: string}
	 */
	protected static function compileBoxShadow( array $attributes ): array
	{
		$payload = BoxShadowResolver::resolve( $attributes );

		if ( null === $payload ) {
			return [ 'class' => '', 'rules' => '' ];
		}

		try {
			$states      = self::resolveStateRegistry();
			$breakpoints = function_exists( 'app' )
				? self::resolveRegistry()
				: BreakpointRegistry::fromLayers();

			$emitter = new BoxShadowEmitter( $states, $breakpoints );

			$scopeClass = self::resolveBoxShadowScopeClass( $attributes, $payload );
			$scope      = '.' . $scopeClass;
			$css        = $emitter->emit( $scope, $payload );

			if ( '' === $css ) {
				return [ 'class' => '', 'rules' => '' ];
			}

			return [
				'class' => $scopeClass,
				'rules' => $css,
			];
		} catch ( \Throwable $e ) {
			return [ 'class' => '', 'rules' => '' ];
		}
	}

	/**
	 * Prefer the editor-minted `style.shadow._shadowScopeId` so the
	 * editor preview and the server-rendered output target the same
	 * scope class. Falls back to a content-derived hash when no id was
	 * stamped (legacy content, hand-authored blocks, server-only
	 * pipelines).
	 *
	 * @since 1.2.0
	 *
	 * @param  array<string, mixed>  $attributes
	 * @param  array<string, mixed>  $payload
	 */
	protected static function resolveBoxShadowScopeClass( array $attributes, array $payload ): string
	{
		$shadow = $attributes['style']['shadow'] ?? null;

		if ( is_array( $shadow ) && isset( $shadow['_shadowScopeId'] ) && is_string( $shadow['_shadowScopeId'] ) ) {
			$candidate = $shadow['_shadowScopeId'];

			if ( 1 === preg_match( '/^[a-z0-9][a-z0-9_-]*$/i', $candidate ) && strlen( $candidate ) <= 64 ) {
				return 've-bs-' . $candidate;
			}
		}

		$hash = substr(
			hash( 'xxh3', (string) json_encode( $payload ) ),
			0,
			10
		);

		return 've-bs-' . $hash;
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

		// Pull the request-scoped registry from the container so
		// host-configured breakpoints (theme.json → config) are
		// respected. `fromLayers()` without args only returns the
		// Tailwind defaults — any custom key (e.g. `3xl`) would
		// resolve to null here and the override would be silently
		// dropped at render time. The fallback to defaults preserves
		// the behavior for callers (mostly tests) that hit
		// `compileResponsive` outside the container.
		$registry = function_exists( 'app' )
			? self::resolveRegistry()
			: BreakpointRegistry::fromLayers();
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
			$resolved = self::sanitizeCssValue( self::resolvePresetVar( (string) $value ) );

			if ( '' === $resolved ) {
				return '';
			}

			return $property . ':' . $resolved . '!important';
		}

		if ( ! is_array( $value ) ) {
			return '';
		}

		$pieces = [];

		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			if ( ! isset( $value[ $side ] ) || null === $value[ $side ] || '' === $value[ $side ] ) {
				continue;
			}

			$resolved = self::sanitizeCssValue( self::resolvePresetVar( (string) $value[ $side ] ) );

			if ( '' === $resolved ) {
				continue;
			}

			$pieces[] = sprintf(
				'%s-%s:%s!important',
				$property,
				$side,
				$resolved
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
	 * Whitelists characters legal in a CSS value expression — letters,
	 * digits, units, calc() operators (`+`, `-`, `*`, `/`), CSS var
	 * punctuation, whitespace. Drops everything else so a stored block
	 * tree with a hostile value (e.g. `</style><script>…`) can never
	 * close the `<style data-ve-responsive>` block we emit it inside.
	 *
	 * Defense-in-depth: block-tree JSON is editor-authored content that
	 * WordPress treats as trusted, but the responsive payload reaches
	 * the renderer via `{!! !!}` (raw output) inside a `<style>` tag,
	 * so an escaping bypass anywhere upstream would land directly in
	 * the DOM. The whitelist is the last line of defense.
	 *
	 * @since 1.0.0
	 */
	protected static function sanitizeCssValue( string $value ): string
	{
		return (string) preg_replace( '/[^a-zA-Z0-9_+\-*\/.,()%#\s]/', '', $value );
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
	 * @since 1.0.0
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

		// #489 — drop the animation wrapper classes, scope class, and
		// data-* attributes onto any block carrying an
		// `artisanpackAnimations` attribute bag. Centralising it here
		// means every block partial that uses `wrapperAttrs` (image,
		// table, navigation, etc.) picks up animations for free; cover
		// builds its attrs by hand and handles this inline.
		$animations = self::resolveAnimations( $attributes );

		if ( null !== $animations ) {
			foreach ( $animations['classes'] as $class ) {
				$classes[] = $class;
			}
		}

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

		if ( null !== $animations && '' !== $animations['dataString'] ) {
			$parts[] = $animations['dataString'];
		}

		return [] === $parts ? '' : ' ' . implode( ' ', $parts );
	}

	/**
	 * Resolves the animation markup pieces for a block scope and pushes
	 * the per-block CSS into the request-scoped accumulator. Returns
	 * `null` when the block has no animations configured.
	 *
	 * The scope class is derived from a stable hash of the block's
	 * attribute bag so identical render passes (e.g. the same block
	 * inside a query loop) collide on key, letting the accumulator
	 * dedupe.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array{classes: array<int, string>, dataString: string}|null
	 */
	protected static function resolveAnimations( array $attributes ): ?array
	{
		$bag = $attributes['artisanpackAnimations'] ?? null;
		if ( ! is_array( $bag ) || [] === $bag ) {
			return null;
		}

		if ( ! function_exists( 'app' ) ) {
			return null;
		}

		try {
			$resolver = app( \ArtisanPackUI\VisualEditorRendererBlade\Animations\AnimationMarkupResolver::class );
			$accumulator = app( \ArtisanPackUI\VisualEditorRendererBlade\Services\AnimationCssAccumulator::class );
		} catch ( \Throwable $e ) {
			return null;
		}

		// `serialize()` is the deterministic content-stable fallback —
		// `spl_object_hash()` keys on object identity, which would
		// break dedupe across identical bags rendered in separate
		// passes (e.g. cached fragments).
		$hashSource  = json_encode( $attributes );
		$source      = false === $hashSource ? serialize( $attributes ) : $hashSource;
		$scopeSuffix = substr( hash( 'sha1', $source ), 0, 8 );
		$scope       = '.ap-block-' . $scopeSuffix;

		$markup = $resolver->resolve( $scope, $bag );

		if ( ! $markup['hasAnimations'] ) {
			return null;
		}

		$accumulator->push(
			$scope,
			$markup['css'],
			$markup['noscriptCss'],
			$markup['hasEntrance'],
		);

		$classes = $markup['classes'];
		$classes[] = ltrim( $scope, '.' );

		return [
			'classes'    => $classes,
			'dataString' => $resolver->dataString( $markup['data'] ),
		];
	}

	/**
	 * Block-level alignment → `align{value}` class. For the left /
	 * center / right values we ALSO emit `has-text-align-{value}` so
	 * text blocks (paragraph, heading) that key their styling off
	 * Gutenberg's text-alignment class are covered too — WP core
	 * emits both and lets the consuming stylesheet pick the
	 * semantically relevant one based on block type.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * Coerce an arbitrary attribute value to a trimmed string. `null`,
	 * arrays, and non-scalar types collapse to `''` so callers can
	 * treat the result as "value is unset".
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
	 * Slugify the user-supplied palette slug so a custom slug like
	 * `Primary Accent` lands as `primary-accent` in the class list.
	 * Mirrors WP core's behavior for class generation.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
