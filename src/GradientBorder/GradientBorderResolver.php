<?php

/**
 * Gradient border attribute resolver (#490).
 *
 * Walks a block's attribute tree and produces the normalized record
 * {@see GradientBorderEmitter::emit} consumes.
 *
 * Composition with the rest of the editor:
 * - Idle value lives at `style.border.gradient` (string slug or raw CSS).
 * - Per-state overrides ride the standard `attributes.states` bag keyed
 *   `style.border.gradient` (or its `border.gradient` shorthand the
 *   inspector writes for child blocks of `style.*`).
 * - Per-breakpoint overrides ride `attributes.responsive` keyed
 *   `style.border.gradient`.
 *
 * This deliberate piggybacking means the gradient value composes with
 * the existing state/responsive HOCs end-to-end — the editor's picker
 * just writes to `style.border.gradient` and the routing falls out
 * naturally. The cost is one extra read here per block at compile time;
 * the alternative (a sibling `gradientStates` bag) duplicated the bag
 * shape and required two parallel inspector controls.
 *
 * Slug values (matched against `^[a-z0-9][a-z0-9_-]*$`) are expanded
 * into `var(--wp--preset--gradient--{slug})` so the emitted CSS picks
 * up the runtime token. Raw values pass through.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\GradientBorder;

class GradientBorderResolver
{
	/**
	 * Path keys the resolver recognizes for gradient overrides in the
	 * `attributes.states` and `attributes.responsive` bags. The inspector
	 * panel always writes the canonical `style.border.gradient` form; the
	 * shorthand `border.gradient` is accepted on read for symmetry with
	 * the generic state emitter which strips the leading `style.`.
	 *
	 * @var array<int, string>
	 */
	protected const GRADIENT_PATHS = [
		'style.border.gradient',
		'border.gradient',
	];

	/**
	 * Resolve a full block attribute tree into the normalized payload
	 * the emitter consumes.
	 *
	 * Returns `null` when no gradient configuration is present at any
	 * cascade level — callers should treat `null` as a signal to skip
	 * {@see GradientBorderEmitter} entirely.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes  The full block attribute
	 *                                            payload.
	 *
	 * @return array{
	 *     idle: ?string,
	 *     states: array<string, string>,
	 *     breakpoints: array<string, string>,
	 *     width: ?string,
	 *     radius: array<string, mixed>|string|null,
	 * }|null
	 */
	public static function resolve( array $attributes ): ?array
	{
		$border = $attributes['style']['border'] ?? null;
		$border = is_array( $border ) ? $border : [];

		$idle        = self::expandGradient( $border['gradient'] ?? null );
		$states      = self::collectStateOverrides( $attributes['states'] ?? null );
		$breakpoints = self::collectBreakpointOverrides( $attributes['responsive'] ?? null );

		if ( null === $idle && [] === $states && [] === $breakpoints ) {
			return null;
		}

		return [
			'idle'        => $idle,
			'states'      => $states,
			'breakpoints' => $breakpoints,
			'width'       => self::coerceString( $border['width'] ?? null ),
			'radius'      => $border['radius'] ?? null,
		];
	}

	/**
	 * Pluck every gradient slug referenced anywhere in a block's
	 * attribute tree (idle + per-state + per-breakpoint). Used by the
	 * editor's token-warning surface to flag references whose theme
	 * token was removed or renamed.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array<int, string>
	 */
	public static function referencedSlugs( array $attributes ): array
	{
		$slugs = [];

		$border = $attributes['style']['border'] ?? null;
		if ( is_array( $border ) ) {
			self::collectSlug( $slugs, $border['gradient'] ?? null );
		}

		$states = $attributes['states'] ?? null;
		if ( is_array( $states ) ) {
			foreach ( self::GRADIENT_PATHS as $path ) {
				if ( ! isset( $states[ $path ] ) || ! is_array( $states[ $path ] ) ) {
					continue;
				}

				foreach ( $states[ $path ] as $value ) {
					self::collectSlug( $slugs, $value );
				}
			}
		}

		$responsive = $attributes['responsive'] ?? null;
		if ( is_array( $responsive ) ) {
			foreach ( self::GRADIENT_PATHS as $path ) {
				if ( ! isset( $responsive[ $path ] ) || ! is_array( $responsive[ $path ] ) ) {
					continue;
				}

				foreach ( $responsive[ $path ] as $value ) {
					self::collectSlug( $slugs, $value );
				}
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Read every gradient override out of an `attributes.states` bag,
	 * expanding slugs and dropping null/empty entries. When the same
	 * state has overrides at both path keys (`style.border.gradient`
	 * and `border.gradient`), the canonical `style.border.gradient`
	 * wins — matching the read precedence WP core's `withStateAttributes`
	 * uses elsewhere.
	 *
	 * @param  mixed  $statesBag
	 *
	 * @return array<string, string>
	 */
	protected static function collectStateOverrides( mixed $statesBag ): array
	{
		if ( ! is_array( $statesBag ) ) {
			return [];
		}

		$out = [];

		// Iterate paths in reverse-precedence order so the canonical
		// path overwrites the shorthand. PHP array assignment is "last
		// write wins" so the loop order is the cascade order.
		foreach ( array_reverse( self::GRADIENT_PATHS ) as $path ) {
			$overrides = $statesBag[ $path ] ?? null;

			if ( ! is_array( $overrides ) ) {
				continue;
			}

			foreach ( $overrides as $stateKey => $value ) {
				if ( ! is_string( $stateKey ) || '' === $stateKey ) {
					continue;
				}

				$expanded = self::expandGradient( $value );

				if ( null === $expanded ) {
					continue;
				}

				$out[ $stateKey ] = $expanded;
			}
		}

		return $out;
	}

	/**
	 * Read every gradient override out of an `attributes.responsive`
	 * bag, expanding slugs and dropping null/empty entries.
	 *
	 * @param  mixed  $responsiveBag
	 *
	 * @return array<string, string>
	 */
	protected static function collectBreakpointOverrides( mixed $responsiveBag ): array
	{
		if ( ! is_array( $responsiveBag ) ) {
			return [];
		}

		$out = [];

		foreach ( array_reverse( self::GRADIENT_PATHS ) as $path ) {
			$overrides = $responsiveBag[ $path ] ?? null;

			if ( ! is_array( $overrides ) ) {
				continue;
			}

			foreach ( $overrides as $bp => $value ) {
				if ( ! is_string( $bp ) || '' === $bp ) {
					continue;
				}

				$expanded = self::expandGradient( $value );

				if ( null === $expanded ) {
					continue;
				}

				$out[ $bp ] = $expanded;
			}
		}

		return $out;
	}

	/**
	 * Expand a single raw value into a CSS gradient. Slugs map to
	 * `var(--wp--preset--gradient--{slug})`; non-slug strings (already
	 * raw CSS) pass through; everything else returns `null`.
	 *
	 * @since 1.1.0
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
	 * Push the raw slug into `$slugs` if the value is a slug-shaped
	 * string. Raw CSS values are ignored — only token references can
	 * become stale.
	 *
	 * @param  array<int, string>  &$slugs
	 */
	protected static function collectSlug( array &$slugs, mixed $value ): void
	{
		if ( ! is_string( $value ) ) {
			return;
		}

		$trimmed = trim( $value );

		if ( '' === $trimmed ) {
			return;
		}

		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]*$/i', $trimmed ) ) {
			return;
		}

		$slugs[] = $trimmed;
	}

	/**
	 * Coerce an arbitrary attribute to a non-empty trimmed string or
	 * `null`.
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
