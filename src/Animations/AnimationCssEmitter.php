<?php

/**
 * Animation CSS emitter — block animations (#489).
 *
 * Server-side helper that turns a block's `animations` attribute bag
 * into the scoped CSS rules plus the markup hints the renderers need:
 *
 *  - Per-block CSS scoped to `.ap-block-<uid>` covering entrance,
 *    continuous, hover-transition + preset classes, and per-breakpoint
 *    overrides (including "disabled at this breakpoint" → reset).
 *  - A `<noscript>` rule that reveals the block in its final state when
 *    JS is unavailable.
 *  - The `data-ap-anim-*` attributes the runtime reads to know when to
 *    swap an entrance block out of its hidden pre-state.
 *  - The CSS class list to add to the block's wrapper.
 *
 * The shape the emitter accepts (and the editor persists into
 * `attributes.artisanpackAnimations`) is:
 *
 *     [
 *         'entrance' => [
 *             'name'      => 'fade-in-up' | null,
 *             'duration'  => 600,
 *             'delay'     => 100,
 *             'easing'    => 'ease-out',
 *             'threshold' => 0.2,
 *             'once'      => true,
 *         ],
 *         'hover' => [
 *             'name'     => 'lift' | null,
 *             'duration' => 200,
 *             'delay'    => 0,
 *             'easing'   => 'ease-out',
 *         ],
 *         'continuous' => [
 *             'name'     => 'pulse' | null,
 *             'duration' => 2000,
 *             'easing'   => 'ease-in-out',
 *             'count'    => 'infinite' | int,
 *         ],
 *         'reducedMotion' => 'respect' | 'allow',
 *     ]
 *
 * The `entrance` and `continuous` `name` fields may also be a
 * responsive-aware shape, e.g. `[ 'base' => 'fade-in', 'md' => null ]`
 * — the resolver walks them per-breakpoint.
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

class AnimationCssEmitter
{
	public const PRE_CLASS  = 'ap-anim-pre';
	public const PLAY_CLASS = 'ap-anim-play';
	public const ROOT_CLASS = 'ap-anim';

	/**
	 * Hover presets — composable CSS fragments the emitter snaps onto
	 * the `:hover` rule when an editor picks one. The transition curve
	 * controls timing; the preset controls the transform / shadow.
	 *
	 * @var array<string, array{transform?: string, box-shadow?: string, filter?: string}>
	 */
	protected const HOVER_PRESETS = [
		'lift'  => [ 'transform' => 'translateY(-3px)', 'box-shadow' => '0 8px 24px rgba(0,0,0,0.12)' ],
		'press' => [ 'transform' => 'translateY(1px) scale(0.99)' ],
		'glow'  => [ 'box-shadow' => '0 0 0 4px rgba(99,102,241,0.35)' ],
	];

	public function __construct(
		protected AnimationRegistry $registry,
		protected KeyframeRegistry $keyframes,
		protected BreakpointRegistry $breakpoints,
		protected AnimationAttributeResolver $resolver,
	) {}

	/**
	 * Emits the animation CSS for a block scope.
	 *
	 * @since 1.1.0
	 *
	 * @param  string                $scope       e.g. `.ap-block-abc123`.
	 *                                            Must include the leading `.`.
	 * @param  array<string, mixed>  $attributes  The block's `animations` bag.
	 *
	 * @return string Empty string when nothing should be emitted.
	 */
	public function emit( string $scope, array $attributes ): string
	{
		if ( '' === trim( $scope ) || [] === $attributes ) {
			return '';
		}

		$entrance   = $attributes['entrance'] ?? [];
		$continuous = $attributes['continuous'] ?? [];

		$css = '';

		// Entrance + continuous compose by sharing the `animation`
		// property — the play-class rule emits a comma-joined shorthand
		// when both families resolve, so the entrance doesn't clobber
		// the continuous loop the moment `.ap-anim-play` is added.
		$css .= $this->emitEntrance( $scope, $entrance, $continuous );
		$css .= $this->emitHover( $scope, $attributes['hover'] ?? [] );
		$css .= $this->emitContinuous( $scope, $continuous );

		if ( $this->respectsReducedMotion( $attributes ) ) {
			$css .= $this->emitReducedMotionGuard( $scope );
		}

		$css .= $this->emitNoscriptFallback( $scope, $attributes );

		return trim( $css );
	}

	/**
	 * Returns the wrapper class list the renderer should attach to a
	 * block when it has any animations configured. The runtime swaps
	 * `PRE_CLASS` for `PLAY_CLASS` when the block enters the viewport.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array<int, string>
	 */
	public function wrapperClasses( array $attributes ): array
	{
		$classes = [];

		if ( $this->hasAny( $attributes ) ) {
			$classes[] = self::ROOT_CLASS;
		}

		if ( $this->hasEntranceAnywhere( $attributes ) ) {
			$classes[] = self::PRE_CLASS;
		}

		return $classes;
	}

	/**
	 * Returns the `data-*` attribute map the renderer adds to a block's
	 * wrapper. The runtime keys off these to know which entrance
	 * animation to play, when to play it, and whether the host opted out
	 * of the global `prefers-reduced-motion` respect.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array<string, string>
	 */
	public function dataAttributes( array $attributes ): array
	{
		$data = [];

		$entrance = $attributes['entrance'] ?? [];
		$base     = $this->resolver->resolve( $entrance['name'] ?? null, BreakpointRegistry::BASE_KEY );
		// Fall back to the first responsive-only entrance value so
		// `{ md: 'fade-in' }` configs still surface the data attr the
		// runtime needs to know the block is an entrance candidate.
		$effective = is_string( $base ) && '' !== $base
			? $base
			: $this->firstConfiguredEntrance( $entrance['name'] ?? null );

		if ( is_string( $effective ) && '' !== $effective ) {
			$data['data-ap-anim-entrance'] = $effective;

			$threshold = $entrance['threshold'] ?? null;
			if ( is_numeric( $threshold ) ) {
				$data['data-ap-anim-threshold'] = (string) (float) $threshold;
			}

			$once = $entrance['once'] ?? true;
			if ( false === $once ) {
				$data['data-ap-anim-once'] = 'false';
			}
		}

		$reduced = $attributes['reducedMotion'] ?? 'respect';
		if ( 'allow' === $reduced ) {
			$data['data-ap-anim-reduced'] = 'allow';
		}

		return $data;
	}

	/**
	 * Reports whether any family has a configured animation. Used by
	 * the renderer to decide whether to attach the runtime chunk.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 */
	public function hasAny( array $attributes ): bool
	{
		return $this->hasEntranceAnywhere( $attributes )
			|| $this->hasHover( $attributes['hover'] ?? [] )
			|| $this->hasContinuousAnywhere( $attributes );
	}

	/**
	 * Reports whether any entrance animation is configured at any
	 * breakpoint. The runtime only needs to load on pages where this is
	 * true.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 */
	public function hasEntrance( array $attributes ): bool
	{
		return $this->hasEntranceAnywhere( $attributes );
	}

	/**
	 * @param  array<string, mixed>  $entrance
	 */
	protected function emitEntrance( string $scope, array $entrance, array $continuous = [] ): string
	{
		if ( ! $this->hasEntranceAnywhere( [ 'entrance' => $entrance ] ) ) {
			return '';
		}

		$base   = $this->resolver->resolve( $entrance['name'] ?? null, BreakpointRegistry::BASE_KEY );
		$effect = is_string( $base ) && $this->registry->has( AnimationRegistry::FAMILY_ENTRANCE, $base )
			? $base
			: $this->firstConfiguredEntrance( $entrance['name'] ?? null );

		$css = '';

		if ( null !== $effect ) {
			// Pre-state always emitted when ANY entrance breakpoint
			// configures a valid name — covers responsive-only entrance
			// configs (e.g. `{ md: 'fade-in' }`) so the block hides
			// before the runtime swaps the play class.
			$css .= $this->preStateRule( $scope );
		}

		if ( is_string( $base ) && $this->registry->has( AnimationRegistry::FAMILY_ENTRANCE, $base ) ) {
			$definition  = $this->registry->get( AnimationRegistry::FAMILY_ENTRANCE, $base );
			$entranceCss = $this->animationShorthand(
				$definition['keyframe'],
				$this->intOr( $entrance['duration'] ?? null, (int) $definition['duration'] ),
				$this->easingOr( $entrance['easing'] ?? null, (string) $definition['easing'] ),
				$this->intOr( $entrance['delay'] ?? null, 0 ),
				'both',
			);

			$continuousCss = $this->resolvedContinuousShorthand( $continuous );

			$animation = null !== $continuousCss
				? $entranceCss . ', ' . $continuousCss
				: $entranceCss;

			$css .= sprintf(
				'%s.%s { animation: %s; }',
				$scope,
				self::PLAY_CLASS,
				$animation,
			);
		}

		$css .= $this->emitEntranceBreakpointOverrides( $scope, $entrance, $continuous );

		return $css;
	}

	/**
	 * Returns the first responsive-named entrance value that resolves to
	 * a registered animation, or `null`. Used to drive pre-state CSS +
	 * `data-ap-anim-entrance` even when only a non-`base` breakpoint
	 * configures an entrance.
	 */
	protected function firstConfiguredEntrance( $name ): ?string
	{
		if ( is_string( $name ) && $this->registry->has( AnimationRegistry::FAMILY_ENTRANCE, $name ) ) {
			return $name;
		}

		if ( ! is_array( $name ) ) {
			return null;
		}

		foreach ( $name as $value ) {
			if ( is_string( $value ) && $this->registry->has( AnimationRegistry::FAMILY_ENTRANCE, $value ) ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Builds an `animation`-shorthand value for the given parameters.
	 */
	protected function animationShorthand( string $keyframe, int $duration, string $easing, int $delay, string $tail ): string
	{
		return sprintf( '%s %dms %s %dms %s', $keyframe, $duration, $easing, $delay, $tail );
	}

	/**
	 * Returns the continuous animation shorthand (without `animation:`
	 * prefix) when the base breakpoint resolves cleanly. Used by the
	 * entrance emitter to compose entrance + continuous on the play
	 * class so the entrance doesn't override the continuous loop.
	 */
	protected function resolvedContinuousShorthand( array $continuous ): ?string
	{
		if ( [] === $continuous ) {
			return null;
		}

		$base = $this->resolver->resolve( $continuous['name'] ?? null, BreakpointRegistry::BASE_KEY );

		if ( ! is_string( $base ) || ! $this->registry->has( AnimationRegistry::FAMILY_CONTINUOUS, $base ) ) {
			return null;
		}

		$definition = $this->registry->get( AnimationRegistry::FAMILY_CONTINUOUS, $base );

		return sprintf(
			'%s %dms %s 0ms %s',
			$definition['keyframe'],
			$this->intOr( $continuous['duration'] ?? null, (int) $definition['duration'] ),
			$this->easingOr( $continuous['easing'] ?? null, (string) $definition['easing'] ),
			$this->countOr( $continuous['count'] ?? null ),
		);
	}

	/**
	 * @param  array<string, mixed>  $entrance
	 */
	protected function emitEntranceBreakpointOverrides( string $scope, array $entrance, array $continuous = [] ): string
	{
		$name = $entrance['name'] ?? null;
		if ( ! is_array( $name ) ) {
			return '';
		}

		$css = '';

		foreach ( $this->breakpoints->all() as $prefix => $minWidth ) {
			if ( ! array_key_exists( $prefix, $name ) ) {
				continue;
			}

			$value = $name[ $prefix ];

			if ( null === $value ) {
				// Disable at this breakpoint — reset both the pre-state
				// hiding and the animation property so the block reads
				// statically.
				$css .= sprintf(
					'@media (min-width: %dpx) { %s.%s, %s.%s { animation: none; opacity: 1; transform: none; } }',
					$minWidth,
					$scope,
					self::PRE_CLASS,
					$scope,
					self::PLAY_CLASS,
				);
				continue;
			}

			if ( ! is_string( $value ) || ! $this->registry->has( AnimationRegistry::FAMILY_ENTRANCE, $value ) ) {
				continue;
			}

			$definition = $this->registry->get( AnimationRegistry::FAMILY_ENTRANCE, $value );

			$entranceShorthand = $this->animationShorthand(
				$definition['keyframe'],
				$this->intOr( $entrance['duration'] ?? null, (int) $definition['duration'] ),
				$this->easingOr( $entrance['easing'] ?? null, (string) $definition['easing'] ),
				$this->intOr( $entrance['delay'] ?? null, 0 ),
				'both',
			);

			$continuousShorthand = $this->resolvedContinuousShorthand( $continuous );

			$animation = null !== $continuousShorthand
				? $entranceShorthand . ', ' . $continuousShorthand
				: $entranceShorthand;

			$css .= sprintf(
				'@media (min-width: %dpx) { %s.%s { animation: %s; } }',
				$minWidth,
				$scope,
				self::PLAY_CLASS,
				$animation,
			);
		}

		return $css;
	}

	protected function preStateRule( string $scope ): string
	{
		// Pre-state hides the block by zeroing opacity. The runtime
		// removes `.ap-anim-pre` and adds `.ap-anim-play`, which has the
		// real `animation` property. Transform is left to the keyframe
		// 0% stop so we don't fight the choreography.
		return sprintf( '%s.%s { opacity: 0; } ', $scope, self::PRE_CLASS );
	}

	/**
	 * @param  array<string, mixed>  $hover
	 */
	protected function emitHover( string $scope, array $hover ): string
	{
		$name = $this->resolver->resolve( $hover['name'] ?? null, BreakpointRegistry::BASE_KEY );
		if ( ! is_string( $name ) || ! $this->registry->has( AnimationRegistry::FAMILY_HOVER, $name ) ) {
			// Even without a preset, an authored duration/easing should
			// emit a transition curve so the state engine's transitions
			// inherit the chosen timing.
			if ( ! isset( $hover['duration'] ) && ! isset( $hover['easing'] ) ) {
				return '';
			}

			$duration = $this->intOr( $hover['duration'] ?? null, 150 );
			$easing   = $this->easingOr( $hover['easing'] ?? null, 'ease' );

			return sprintf( '%s { transition: all %dms %s; } ', $scope, $duration, $easing );
		}

		$definition = $this->registry->get( AnimationRegistry::FAMILY_HOVER, $name );
		$duration   = $this->intOr( $hover['duration'] ?? null, (int) $definition['duration'] );
		$easing     = $this->easingOr( $hover['easing'] ?? null, (string) $definition['easing'] );
		$preset     = (string) ( $definition['preset'] ?? '' );

		$rule  = sprintf( '%s { transition: all %dms %s; } ', $scope, $duration, $easing );
		$preset_decls = self::HOVER_PRESETS[ $preset ] ?? null;

		if ( null !== $preset_decls ) {
			$decls = [];
			foreach ( $preset_decls as $property => $value ) {
				$decls[] = sprintf( '%s: %s;', $property, $value );
			}

			$rule .= sprintf(
				'@media (hover: hover) { %s:hover { %s } } ',
				$scope,
				implode( ' ', $decls )
			);
		}

		return $rule;
	}

	/**
	 * @param  array<string, mixed>  $continuous
	 */
	protected function emitContinuous( string $scope, array $continuous ): string
	{
		$base = $this->resolver->resolve( $continuous['name'] ?? null, BreakpointRegistry::BASE_KEY );
		$css  = '';

		if ( is_string( $base ) && $this->registry->has( AnimationRegistry::FAMILY_CONTINUOUS, $base ) ) {
			$definition = $this->registry->get( AnimationRegistry::FAMILY_CONTINUOUS, $base );
			$css       .= sprintf(
				'%s { animation: %s %dms %s 0ms %s; } ',
				$scope,
				$definition['keyframe'],
				$this->intOr( $continuous['duration'] ?? null, (int) $definition['duration'] ),
				$this->easingOr( $continuous['easing'] ?? null, (string) $definition['easing'] ),
				$this->countOr( $continuous['count'] ?? null ),
			);
		}

		if ( is_array( $continuous['name'] ?? null ) ) {
			foreach ( $this->breakpoints->all() as $prefix => $minWidth ) {
				if ( ! array_key_exists( $prefix, $continuous['name'] ) ) {
					continue;
				}

				$value = $continuous['name'][ $prefix ];

				if ( null === $value ) {
					$css .= sprintf(
						'@media (min-width: %dpx) { %s { animation: none; } } ',
						$minWidth,
						$scope,
					);
					continue;
				}

				if ( ! is_string( $value ) || ! $this->registry->has( AnimationRegistry::FAMILY_CONTINUOUS, $value ) ) {
					continue;
				}

				$definition = $this->registry->get( AnimationRegistry::FAMILY_CONTINUOUS, $value );

				$css .= sprintf(
					'@media (min-width: %dpx) { %s { animation: %s %dms %s 0ms %s; } } ',
					$minWidth,
					$scope,
					$definition['keyframe'],
					$this->intOr( $continuous['duration'] ?? null, (int) $definition['duration'] ),
					$this->easingOr( $continuous['easing'] ?? null, (string) $definition['easing'] ),
					$this->countOr( $continuous['count'] ?? null ),
				);
			}
		}

		return $css;
	}

	protected function emitReducedMotionGuard( string $scope ): string
	{
		return sprintf(
			'@media (prefers-reduced-motion: reduce) { %s, %s.%s, %s.%s { animation: none !important; transition: none !important; opacity: 1 !important; transform: none !important; } } ',
			$scope,
			$scope,
			self::PRE_CLASS,
			$scope,
			self::PLAY_CLASS,
		);
	}

	/**
	 * @param  array<string, mixed>  $attributes
	 */
	protected function emitNoscriptFallback( string $scope, array $attributes ): string
	{
		if ( ! $this->hasEntranceAnywhere( $attributes ) ) {
			return '';
		}

		// The noscript fallback CSS itself is included unconditionally —
		// the renderer wraps the actual <style> tag in <noscript>. We
		// return the rule that resets the pre-state when JS is missing.
		return '';
	}

	/**
	 * The CSS the renderer should drop inside a `<noscript>` tag — when
	 * JS doesn't run, this reveals the block in its final state.
	 *
	 * @since 1.1.0
	 */
	public function noscriptCss( string $scope ): string
	{
		return sprintf(
			'%s.%s { opacity: 1; transform: none; }',
			$scope,
			self::PRE_CLASS
		);
	}

	protected function respectsReducedMotion( array $attributes ): bool
	{
		$value = $attributes['reducedMotion'] ?? 'respect';

		return 'allow' !== $value;
	}

	/**
	 * @param  array<string, mixed>  $hover
	 */
	protected function hasHover( array $hover ): bool
	{
		$name = $hover['name'] ?? null;
		if ( is_string( $name ) && '' !== $name ) {
			return true;
		}

		return isset( $hover['duration'] ) || isset( $hover['easing'] );
	}

	/**
	 * @param  array<string, mixed>  $attributes
	 */
	protected function hasEntranceAnywhere( array $attributes ): bool
	{
		$name = $attributes['entrance']['name'] ?? null;

		return $this->nameNotEmpty( $name );
	}

	/**
	 * @param  array<string, mixed>  $attributes
	 */
	protected function hasContinuousAnywhere( array $attributes ): bool
	{
		$name = $attributes['continuous']['name'] ?? null;

		return $this->nameNotEmpty( $name );
	}

	protected function nameNotEmpty( $name ): bool
	{
		if ( is_string( $name ) && '' !== $name ) {
			return true;
		}

		if ( is_array( $name ) ) {
			foreach ( $name as $value ) {
				if ( is_string( $value ) && '' !== $value ) {
					return true;
				}
			}
		}

		return false;
	}

	protected function intOr( $value, int $fallback ): int
	{
		if ( is_int( $value ) && $value >= 0 ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			$cast = (int) $value;

			return $cast >= 0 ? $cast : $fallback;
		}

		return $fallback;
	}

	protected function easingOr( $value, string $fallback ): string
	{
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return $fallback;
		}

		// Defence-in-depth: reject anything with characters that could
		// break out of the `animation` shorthand.
		if ( 1 === preg_match( '/[<>{};]/', $trimmed ) ) {
			return $fallback;
		}

		return $trimmed;
	}

	protected function countOr( $value ): string
	{
		if ( is_int( $value ) && $value > 0 ) {
			return (string) $value;
		}

		if ( is_string( $value ) && '' !== trim( $value ) ) {
			$trimmed = trim( $value );
			if ( 'infinite' === $trimmed || ctype_digit( $trimmed ) ) {
				return $trimmed;
			}
		}

		return 'infinite';
	}
}
