<?php

/**
 * Animation attribute resolver — block animations (#489).
 *
 * Walks a responsive-aware animation attribute and returns the effective
 * value for a given breakpoint, following the mobile-first cascade. The
 * shape mirrors {@see \ArtisanPackUI\VisualEditor\Responsive\ResponsiveValueResolver}:
 *
 *     [
 *         'base' => 'fade-in',
 *         'md'   => null,        // disables under `md`
 *     ]
 *
 * A scalar value is treated as the `base`-only shape.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Animations;

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;

class AnimationAttributeResolver
{
	public function __construct( protected BreakpointRegistry $breakpoints ) {}

	/**
	 * Resolves the effective animation value for `$breakpoint`. Walks
	 * from the requested breakpoint down through the registered ordering
	 * (and finally `base`) returning the first non-`null` entry. An
	 * explicit `null` at the requested breakpoint short-circuits to
	 * "disabled" so authors can opt out of an animation per-breakpoint.
	 *
	 * @since 1.1.0
	 *
	 * @param  mixed   $value
	 */
	public function resolve( $value, string $breakpoint = BreakpointRegistry::BASE_KEY ): mixed
	{
		// A scalar value is the "base shape" and applies across every
		// breakpoint — matches `ResponsiveValueResolver::resolve()` and
		// the documented behaviour in `types.ts`.
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$order = $this->breakpoints->keysWithBase();

		// Build the cascade from the requested breakpoint down to base.
		$index = array_search( $breakpoint, $order, true );
		if ( false === $index ) {
			return $value[ BreakpointRegistry::BASE_KEY ] ?? null;
		}

		// Walk from $index → 0 inclusive.
		for ( $i = $index; $i >= 0; $i-- ) {
			$key = $order[ $i ];
			if ( ! array_key_exists( $key, $value ) ) {
				continue;
			}

			// Explicit null at the EXACT requested breakpoint disables
			// the animation. A null further down the cascade is treated
			// as "not set at this breakpoint" and skipped.
			if ( null === $value[ $key ] ) {
				if ( $i === $index ) {
					return null;
				}
				continue;
			}

			return $value[ $key ];
		}

		return null;
	}

	/**
	 * Returns the resolved values for every registered breakpoint plus
	 * `base`, in cascade order. Useful for the renderer when it needs to
	 * emit one CSS rule per breakpoint scope.
	 *
	 * @since 1.1.0
	 *
	 * @param  mixed  $value
	 *
	 * @return array<string, mixed>
	 */
	public function resolveAll( $value ): array
	{
		$resolved = [];

		foreach ( $this->breakpoints->keysWithBase() as $key ) {
			$resolved[ $key ] = $this->resolve( $value, $key );
		}

		return $resolved;
	}
}
