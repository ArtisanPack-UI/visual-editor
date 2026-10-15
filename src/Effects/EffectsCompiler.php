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
 *  - `textClip`         bool — clips the background to the text (gradient text)
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
	 * Compile a block's effects, or null when it sets none.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $attributes  Raw block attributes.
	 *
	 * @return array{class: string, css: string}|null
	 */
	public static function compile( array $attributes ): ?array
	{
		$effects = $attributes[ self::ATTRIBUTE ] ?? null;

		if ( ! is_array( $effects ) ) {
			return null;
		}

		$declarations = self::declarations( $effects );
		$customCss    = CustomCssSanitizer::sanitize( $effects['customCss'] ?? null ) ?? '';

		if ( [] === $declarations && '' === $customCss ) {
			return null;
		}

		$body  = implode( ';', $declarations );
		$class = self::SCOPE_PREFIX . substr( sha1( $body . "\n" . $customCss ), 0, 12 );
		$inner = implode( ';', array_filter( [ $body, $customCss ], static fn ( string $part ): bool => '' !== $part ) );
		$css   = '.' . $class . '{' . $inner . '}';

		if ( null !== self::integer( $effects['zIndex'] ?? null, -99999, 99999 ) ) {
			$css .= ':where(.' . $class . '){position:relative}';
		}

		return [
			'class' => $class,
			'css'   => $css,
		];
	}

	/**
	 * The CSS declarations for an effects payload, in a fixed order.
	 *
	 * @since 1.15.0
	 *
	 * @param  array<string, mixed>  $effects
	 *
	 * @return array<int, string>
	 */
	public static function declarations( array $effects ): array
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

		if ( true === ( $effects['textClip'] ?? null ) ) {
			$declarations[] = '-webkit-background-clip:text';
			$declarations[] = 'background-clip:text';
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
