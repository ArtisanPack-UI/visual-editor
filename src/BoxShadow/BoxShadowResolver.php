<?php

/**
 * Box shadow attribute resolver (#607).
 *
 * Walks a block's attribute tree and produces the normalized record
 * {@see BoxShadowEmitter::emit} consumes.
 *
 * Composition with the rest of the editor:
 * - Idle value lives at `style.shadow` (a structured subtree).
 * - Per-state overrides ride the standard `attributes.states` bag keyed
 *   `style.shadow` (or its `shadow` shorthand for symmetry).
 * - Per-breakpoint overrides ride `attributes.responsive` keyed
 *   `style.shadow`.
 *
 * A `preset` slug short-circuits the structured fields — when set,
 * the emitter renders `box-shadow: var(--wp--preset--shadow--{slug})`.
 * The `gradient` field accepts both slugs (expanded to
 * `var(--wp--preset--gradient--{slug})`) and raw CSS gradient values.
 *
 * Architecture deliberately parallels {@see GradientBorderResolver}
 * — see that file's docblock for the cascade rationale.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\BoxShadow;

class BoxShadowResolver
{
	/**
	 * Path keys the resolver recognizes for shadow overrides in the
	 * `attributes.states` and `attributes.responsive` bags.
	 *
	 * @var array<int, string>
	 */
	protected const SHADOW_PATHS = [
		'style.shadow',
		'shadow',
	];

	/**
	 * Resolve a full block attribute tree into the normalized payload
	 * the emitter consumes.
	 *
	 * Returns `null` when no shadow configuration is present at any
	 * cascade level — callers should treat `null` as a signal to skip
	 * {@see BoxShadowEmitter} entirely.
	 *
	 * @since 1.2.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array{
	 *     idle: array<string, mixed>|null,
	 *     states: array<string, array<string, mixed>>,
	 *     breakpoints: array<string, array<string, mixed>>,
	 * }|null
	 */
	public static function resolve( array $attributes ): ?array
	{
		$shadow = $attributes['style']['shadow'] ?? null;

		$idle        = self::resolveLayer( is_array( $shadow ) ? $shadow : null );
		$states      = self::collectStateOverrides( $attributes['states'] ?? null );
		$breakpoints = self::collectBreakpointOverrides( $attributes['responsive'] ?? null );

		if ( null === $idle && [] === $states && [] === $breakpoints ) {
			return null;
		}

		return [
			'idle'        => $idle,
			'states'      => $states,
			'breakpoints' => $breakpoints,
		];
	}

	/**
	 * Pluck every shadow preset slug AND gradient slug referenced
	 * anywhere in a block's attribute tree. Used by the editor's
	 * token-warning surface to flag references whose theme token was
	 * removed or renamed.
	 *
	 * @since 1.2.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array{ shadows: array<int, string>, gradients: array<int, string> }
	 */
	public static function referencedSlugs( array $attributes ): array
	{
		$shadows   = [];
		$gradients = [];

		$shadow = $attributes['style']['shadow'] ?? null;
		if ( is_array( $shadow ) ) {
			self::collectSubtreeSlugs( $shadows, $gradients, $shadow );
		}

		foreach ( [ $attributes['states'] ?? null, $attributes['responsive'] ?? null ] as $bag ) {
			if ( ! is_array( $bag ) ) {
				continue;
			}

			foreach ( self::SHADOW_PATHS as $path ) {
				if ( ! isset( $bag[ $path ] ) || ! is_array( $bag[ $path ] ) ) {
					continue;
				}

				foreach ( $bag[ $path ] as $value ) {
					if ( is_array( $value ) ) {
						self::collectSubtreeSlugs( $shadows, $gradients, $value );
					}
				}
			}
		}

		return [
			'shadows'   => array_values( array_unique( $shadows ) ),
			'gradients' => array_values( array_unique( $gradients ) ),
		];
	}

	/**
	 * Resolve a single shadow subtree into a normalized layer. Returns
	 * `null` for empty/missing subtrees so callers can short-circuit
	 * emission.
	 *
	 * @param  array<string, mixed>|null  $subtree
	 *
	 * @return array{
	 *     offsetX: string,
	 *     offsetY: string,
	 *     blur: string,
	 *     spread: string,
	 *     color: string|null,
	 *     gradient: string|null,
	 *     inset: bool,
	 *     preset: string|null,
	 * }|null
	 */
	protected static function resolveLayer( ?array $subtree ): ?array
	{
		if ( null === $subtree ) {
			return null;
		}

		$preset = self::expandPreset( $subtree['preset'] ?? null );

		$offsetX  = self::coerceString( $subtree['offsetX'] ?? null );
		$offsetY  = self::coerceString( $subtree['offsetY'] ?? null );
		$blur     = self::coerceString( $subtree['blur'] ?? null );
		$spread   = self::coerceString( $subtree['spread'] ?? null );
		$color    = self::coerceString( $subtree['color'] ?? null );
		$gradient = self::expandGradient( $subtree['gradient'] ?? null );
		$inset    = true === ( $subtree['inset'] ?? false );

		$hasStructured =
			null !== $offsetX
			|| null !== $offsetY
			|| null !== $blur
			|| null !== $spread
			|| null !== $color
			|| null !== $gradient
			|| true === ( $subtree['inset'] ?? false );

		if ( null === $preset && ! $hasStructured ) {
			return null;
		}

		// Defaults are `0px`, not `0`. Safari (and the CSS spec) require
		// a unit inside `calc()` — `calc(-1 * 0)` is invalid because
		// `0` is a `<number>`, not a `<length>`. Mirrors the TS resolver.
		return [
			'offsetX'  => $offsetX ?? '0px',
			'offsetY'  => $offsetY ?? '0px',
			'blur'     => $blur ?? '0px',
			'spread'   => $spread ?? '0px',
			'color'    => $color,
			'gradient' => $gradient,
			'inset'    => $inset,
			'preset'   => $preset,
		];
	}

	/**
	 * Read every shadow override out of an `attributes.states` bag,
	 * resolving each into a structured layer. Canonical `style.shadow`
	 * wins over the `shadow` shorthand on conflict.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected static function collectStateOverrides( mixed $statesBag ): array
	{
		if ( ! is_array( $statesBag ) ) {
			return [];
		}

		$out = [];

		foreach ( array_reverse( self::SHADOW_PATHS ) as $path ) {
			$overrides = $statesBag[ $path ] ?? null;

			if ( ! is_array( $overrides ) ) {
				continue;
			}

			foreach ( $overrides as $stateKey => $value ) {
				if ( ! is_string( $stateKey ) || '' === $stateKey ) {
					continue;
				}

				$layer = self::resolveLayer( is_array( $value ) ? $value : null );

				if ( null === $layer ) {
					continue;
				}

				$out[ $stateKey ] = $layer;
			}
		}

		return $out;
	}

	/**
	 * Read every shadow override out of an `attributes.responsive`
	 * bag, resolving each into a structured layer.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected static function collectBreakpointOverrides( mixed $responsiveBag ): array
	{
		if ( ! is_array( $responsiveBag ) ) {
			return [];
		}

		$out = [];

		foreach ( array_reverse( self::SHADOW_PATHS ) as $path ) {
			$overrides = $responsiveBag[ $path ] ?? null;

			if ( ! is_array( $overrides ) ) {
				continue;
			}

			foreach ( $overrides as $bp => $value ) {
				if ( ! is_string( $bp ) || '' === $bp ) {
					continue;
				}

				$layer = self::resolveLayer( is_array( $value ) ? $value : null );

				if ( null === $layer ) {
					continue;
				}

				$out[ $bp ] = $layer;
			}
		}

		return $out;
	}

	/**
	 * Expand a gradient value: slugs map to
	 * `var(--wp--preset--gradient--{slug})`, raw CSS passes through,
	 * everything else returns `null`.
	 */
	protected static function expandGradient( mixed $value ): ?string
	{
		if ( ! is_string( $value ) ) {
			return null;
		}

		$trimmed = trim( $value );

		if ( '' === $trimmed ) {
			return null;
		}

		if ( 1 === preg_match( '/^[a-z0-9][a-z0-9_-]*$/i', $trimmed ) ) {
			return sprintf( 'var(--wp--preset--gradient--%s)', $trimmed );
		}

		return $trimmed;
	}

	/**
	 * Validate + return a shadow preset slug, or `null` if the value
	 * isn't slug-shaped.
	 */
	protected static function expandPreset( mixed $value ): ?string
	{
		if ( ! is_string( $value ) ) {
			return null;
		}

		$trimmed = trim( $value );

		if ( '' === $trimmed ) {
			return null;
		}

		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]*$/i', $trimmed ) ) {
			return null;
		}

		return $trimmed;
	}

	/**
	 * Push the raw slug into either the shadows or gradients list
	 * depending on which subtree field it came from.
	 *
	 * @param  array<int, string>  $shadows
	 * @param  array<int, string>  $gradients
	 * @param  array<string, mixed>  $subtree
	 */
	protected static function collectSubtreeSlugs( array &$shadows, array &$gradients, array $subtree ): void
	{
		$preset = self::expandPreset( $subtree['preset'] ?? null );
		if ( null !== $preset ) {
			$shadows[] = $preset;
		}

		$gradient = $subtree['gradient'] ?? null;
		if ( is_string( $gradient ) ) {
			$trimmed = trim( $gradient );
			if ( '' !== $trimmed && 1 === preg_match( '/^[a-z0-9][a-z0-9_-]*$/i', $trimmed ) ) {
				$gradients[] = $trimmed;
			}
		}
	}

	/**
	 * Coerce an attribute to a non-empty trimmed string or `null`.
	 */
	protected static function coerceString( mixed $value ): ?string
	{
		if ( ! is_string( $value ) ) {
			return null;
		}

		$trimmed = trim( $value );

		return '' === $trimmed ? null : $trimmed;
	}
}
