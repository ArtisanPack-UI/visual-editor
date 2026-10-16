<?php

/**
 * Block-level effects compiler (#881).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Effects;

/**
 * Turns a block's `artisanpackEffects` attribute into a hashed scope class
 * and the CSS behind it:
 *
 *     .ve-fx-<hash>{opacity:0.8;-webkit-backdrop-filter:blur(12px);…;<custom css>}
 *     :where(.ve-fx-<hash>){position:relative}
 *
 * Shape (every key optional; unknown or invalid values are dropped):
 *
 *  - `opacity`          int 0–100 (percent)
 *  - `overflowX` / `overflowY`  `visible|hidden|clip|scroll|auto`
 *  - `backdropBlur`     int 0–200 (px), `backdropSaturate` int 0–500 (%)
 *  - `mixBlendMode`     one of {@see self::BLEND_MODES}
 *  - `isolation`        bool — `isolation: isolate`
 *  - `zIndex`           int; a block left `position: static` can't stack,
 *                       so a zero-specificity `position: relative` comes
 *                       with it — any theme or Positioning rule wins over it
 *  - `clipShape`        one of {@see self::CLIP_SHAPES}
 *  - `textClip`         bool — clips the background to the text (gradient
 *                       text). Emitted only for a block that paints a
 *                       gradient or background image
 *                       ({@see self::hasBackgroundFill()}), since the text
 *                       would otherwise be invisible, and paired with a
 *                       `forced-colors` fallback in the text color
 *  - `customCss`        string, see {@see CustomCssSanitizer}
 *
 * The hash covers the compiled declarations and Custom CSS, so identical
 * blocks share one rule. The React and Vue renderers
 * (`support/effects.ts`) and the editor canvas
 * (`resources/js/visual-editor/effects/compiler.ts`) mirror this file.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.15.0
 */
class EffectsCompiler
{
	/**
	 * Block attribute the panel writes.
	 *
	 * @since 1.15.0
	 */
	public const ATTRIBUTE = 'artisanpackEffects';

	/**
	 * Scope class prefix.
	 *
	 * @since 1.15.0
	 */
	public const SCOPE_PREFIX = 've-fx-';

	/**
	 * The text-clip declarations, kept `!important` and printed in their own
	 * doubled-class rule by {@see self::compile()} (#904).
	 *
	 * @since 1.15.2
	 *
	 * @var list<string>
	 */
	public const TEXT_CLIP_DECLARATIONS = [ '-webkit-background-clip:text!important', 'background-clip:text!important' ];

	/**
	 * Accepted `overflow-x` / `overflow-y` keywords.
	 *
	 * @since 1.15.0
	 *
	 * @var array<int, string>
	 */
	public const OVERFLOW_VALUES = [ 'visible', 'hidden', 'clip', 'scroll', 'auto' ];

	/**
	 * Accepted `mix-blend-mode` keywords.
	 *
	 * @since 1.15.0
	 *
	 * @var array<int, string>
	 */
	public const BLEND_MODES = [
		'normal',
		'multiply',
		'screen',
		'overlay',
		'darken',
		'lighten',
		'color-dodge',
		'color-burn',
		'hard-light',
		'soft-light',
		'difference',
		'exclusion',
		'hue',
		'saturation',
		'color',
		'luminosity',
	];

	/**
	 * Clip shapes, keyed by the stored slug.
	 *
	 * @since 1.15.0
	 *
	 * @var array<string, string>
	 */
	public const CLIP_SHAPES = [
		'circle'   => 'circle(50% at 50% 50%)',
		'ellipse'  => 'ellipse(50% 50% at 50% 50%)',
		'triangle' => 'polygon(50% 0%, 100% 100%, 0% 100%)',
		'diamond'  => 'polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%)',
		'hexagon'  => 'polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%)',
		'slant'    => 'polygon(0% 0%, 100% 0%, 100% 85%, 0% 100%)',
	];

	/**
	 * Config key that lets Custom CSS load resources (F-11).
	 *
	 * @since 1.15.0
	 */
	public const ALLOW_URLS_CONFIG = 'artisanpack.visual-editor.effects.custom_css_allow_urls';

	/**
	 * Compile a block's effects, or null when it sets none.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $attributes          Raw block attributes.
	 * @param  bool|null             $hasBackgroundFill   Whether the block paints a gradient
	 *                                                    or background image for text clip to
	 *                                                    show through. Null reads it from the
	 *                                                    attributes ({@see self::hasBackgroundFill()}).
	 * @param  bool|null             $allowCustomCssUrls  Whether Custom CSS may load resources.
	 *                                                    Null reads config
	 *                                                    `effects.custom_css_allow_urls`.
	 *
	 * @return array{class: string, css: string}|null
	 */
	public static function compile( array $attributes, ?bool $hasBackgroundFill = null, ?bool $allowCustomCssUrls = null ): ?array
	{
		$effects = $attributes[ self::ATTRIBUTE ] ?? null;

		if ( ! is_array( $effects ) ) {
			return null;
		}

		$allowCustomCssUrls ??= (bool) config( self::ALLOW_URLS_CONFIG, true );

		$declarations = self::declarations( $effects, $hasBackgroundFill ?? self::hasBackgroundFill( $attributes ) );
		$customCss    = CustomCssSanitizer::sanitize( $effects['customCss'] ?? null, $allowCustomCssUrls ) ?? '';

		if ( [] === $declarations && '' === $customCss ) {
			return null;
		}

		$body  = implode( ';', $declarations );
		$class = self::SCOPE_PREFIX . substr( sha1( $body . "\n" . $customCss ), 0, 12 );
		// The text clip gets its own rule at doubled-class specificity
		// (0,2,0): the block's gradient arrives as an `!important`
		// `background` shorthand (`.has-{slug}-gradient-background`, or
		// inline), which resets `background-clip`, and the editor canvas
		// loads the global styles after this rule, so `!important` alone
		// loses on source order there (#904).
		$hasTextClip = in_array( self::TEXT_CLIP_DECLARATIONS[1], $declarations, true );
		$rest        = array_values( array_diff( $declarations, self::TEXT_CLIP_DECLARATIONS ) );
		$inner       = implode( ';', array_filter( [ implode( ';', $rest ), $customCss ], static fn ( string $part ): bool => '' !== $part ) );
		$css         = '' !== $inner ? '.' . $class . '{' . $inner . '}' : '';

		if ( $hasTextClip ) {
			$css .= '.' . $class . '.' . $class . '{' . implode( ';', self::TEXT_CLIP_DECLARATIONS ) . '}';
		}

		if ( null !== self::integer( $effects['zIndex'] ?? null, -99999, 99999 ) ) {
			$css .= ':where(.' . $class . '){position:relative}';
		}

		// Forced-colors mode drops background images and gradients, so
		// clipped text would be invisible: paint it in the text color.
		if ( $hasTextClip ) {
			$css .= '@media (forced-colors:active){.' . $class . '.' . $class . '{-webkit-text-fill-color:currentColor;'
				. '-webkit-background-clip:border-box!important;background-clip:border-box!important}}';
		}

		return [
			'class' => $class,
			'css'   => $css,
		];
	}

	/**
	 * Whether a block paints a gradient or a background image — what text
	 * clip needs to show through. Reads the `gradient` preset,
	 * `style.color.gradient` and `style.background.backgroundImage`.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $attributes  Raw block attributes.
	 *
	 * @return bool
	 */
	public static function hasBackgroundFill( array $attributes ): bool
	{
		$style = self::arrayValue( $attributes['style'] ?? null );
		$image = self::arrayValue( $style['background'] ?? null )['backgroundImage'] ?? null;
		$url   = is_string( $image ) ? $image : ( self::arrayValue( $image )['url'] ?? null );

		return self::filled( $attributes['gradient'] ?? null )
			|| self::filled( self::arrayValue( $style['color'] ?? null )['gradient'] ?? null )
			|| self::filled( $url );
	}

	/**
	 * The CSS declarations for an effects payload, in a fixed order.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $effects  The `artisanpackEffects` payload.
	 * @param  bool                  $hasFill  Whether the block paints a gradient or
	 *                                         background image; text clip is dropped
	 *                                         without one.
	 *
	 * @return array<int, string>
	 */
	public static function declarations( array $effects, bool $hasFill = false ): array
	{
		$declarations = [];

		$opacity = self::integer( $effects['opacity'] ?? null, 0, 100 );

		if ( null !== $opacity ) {
			$declarations[] = 'opacity:' . self::number( $opacity / 100 );
		}

		foreach ( [ 'overflowX' => 'overflow-x', 'overflowY' => 'overflow-y' ] as $key => $property ) {
			$value = self::keyword( $effects[ $key ] ?? null, self::OVERFLOW_VALUES );

			if ( null !== $value ) {
				$declarations[] = $property . ':' . $value;
			}
		}

		$filters  = [];
		$blur     = self::integer( $effects['backdropBlur'] ?? null, 0, 200 );
		$saturate = self::integer( $effects['backdropSaturate'] ?? null, 0, 500 );

		if ( null !== $blur ) {
			$filters[] = 'blur(' . $blur . 'px)';
		}

		if ( null !== $saturate ) {
			$filters[] = 'saturate(' . $saturate . '%)';
		}

		if ( [] !== $filters ) {
			$declarations[] = '-webkit-backdrop-filter:' . implode( ' ', $filters );
			$declarations[] = 'backdrop-filter:' . implode( ' ', $filters );
		}

		$blend = self::keyword( $effects['mixBlendMode'] ?? null, self::BLEND_MODES );

		if ( null !== $blend ) {
			$declarations[] = 'mix-blend-mode:' . $blend;
		}

		if ( true === ( $effects['isolation'] ?? null ) ) {
			$declarations[] = 'isolation:isolate';
		}

		$zIndex = self::integer( $effects['zIndex'] ?? null, -99999, 99999 );

		if ( null !== $zIndex ) {
			$declarations[] = 'z-index:' . $zIndex;
		}

		$clip = $effects['clipShape'] ?? null;

		if ( is_string( $clip ) && isset( self::CLIP_SHAPES[ $clip ] ) ) {
			$declarations[] = 'clip-path:' . self::CLIP_SHAPES[ $clip ];
		}

		// Without a gradient or image behind it, transparent text would
		// simply vanish, so text clip needs the caller to confirm one.
		// `!important` because the gradient reaches the block as a
		// `background` shorthand (the `.has-{slug}-gradient-background`
		// preset class is itself `!important`, a custom gradient is inline),
		// and the shorthand resets `background-clip` (#904).
		if ( $hasFill && true === ( $effects['textClip'] ?? null ) ) {
			array_push( $declarations, ...self::TEXT_CLIP_DECLARATIONS );
			$declarations[] = '-webkit-text-fill-color:transparent';
		}

		return $declarations;
	}

	/**
	 * An integer in range, or null. Accepts ints, floats and numeric
	 * strings; floats round half away from zero (as the JS twin does).
	 *
	 * @since 1.15.0
	 */
	protected static function integer( mixed $value, int $min, int $max ): ?int
	{
		if ( is_string( $value ) && 1 === preg_match( '/^\s*-?\d+(\.\d+)?\s*$/', $value ) ) {
			$value = (float) $value;
		}

		if ( ! is_int( $value ) && ! is_float( $value ) ) {
			return null;
		}

		if ( ! is_finite( (float) $value ) ) {
			return null;
		}

		$int = (int) round( (float) $value, 0, PHP_ROUND_HALF_UP );

		return max( $min, min( $max, $int ) );
	}

	/**
	 * The value when it's an array, otherwise an empty array.
	 *
	 * @since 1.15.0
	 *
	 * @return array<mixed>
	 */
	protected static function arrayValue( mixed $value ): array
	{
		return is_array( $value ) ? $value : [];
	}

	/**
	 * Whether the value is a non-empty string.
	 *
	 * @since 1.15.0
	 */
	protected static function filled( mixed $value ): bool
	{
		return is_string( $value ) && '' !== $value;
	}

	/**
	 * A keyword from an allowlist, or null.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<int, string>  $allowed
	 */
	protected static function keyword( mixed $value, array $allowed ): ?string
	{
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : null;
	}

	/**
	 * Print a 0–1 ratio without trailing zeros (`0.5`, `1`, `0.07`).
	 *
	 * @since 1.15.0
	 */
	protected static function number( float|int $value ): string
	{
		return rtrim( rtrim( number_format( (float) $value, 2, '.', '' ), '0' ), '.' );
	}
}
