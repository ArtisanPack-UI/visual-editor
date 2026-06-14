<?php

/**
 * Animation registry — block animations (#489).
 *
 * Resolves the editor's active set of animations by merging the host
 * theme's declarations into the application's `config()` overrides and
 * finally the package's built-in defaults. Highest layer wins on key
 * collision:
 *
 *   1. theme.json → `settings.custom.artisanpack.animations`
 *   2. application config → `artisanpack.visual-editor.animations`
 *   3. package defaults  → entrance / hover / continuous built-ins
 *
 * Animations are organized into three families and merged by family + key
 * via `array_replace_recursive()`. To REMOVE a built-in animation, set
 * its key to `null` after the merge.
 *
 * The built-in animation names are reserved — a theme that tries to
 * register a custom `fade-in` keyframe under the entrance family will be
 * rejected at validate-time.
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

use InvalidArgumentException;

class AnimationRegistry
{
	public const FAMILY_ENTRANCE   = 'entrance';
	public const FAMILY_HOVER      = 'hover';
	public const FAMILY_CONTINUOUS = 'continuous';

	/**
	 * Every family the registry recognises. Order is meaningful: the
	 * inspector panel renders sub-panels in this order.
	 *
	 * @var array<int, string>
	 */
	public const FAMILIES = [
		self::FAMILY_ENTRANCE,
		self::FAMILY_HOVER,
		self::FAMILY_CONTINUOUS,
	];

	/**
	 * Built-in animation definitions, by family. Every animation maps to
	 * a CSS `animation-name` (which matches a `@keyframes` block emitted
	 * by {@see KeyframeRegistry}) plus default duration / easing values
	 * the editor pre-fills on first attachment.
	 *
	 * Hover-family entries are special: they don't have a keyframe; they
	 * configure a CSS `transition` curve plus an optional "motion preset"
	 * that composes a transform/box-shadow on the `:hover` state.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	public const DEFAULTS = [
		self::FAMILY_ENTRANCE => [
			'fade-in'        => [ 'label' => 'Fade in',         'keyframe' => 'apFadeIn',       'duration' => 600, 'easing' => 'ease-out' ],
			'fade-in-up'     => [ 'label' => 'Fade in up',      'keyframe' => 'apFadeInUp',     'duration' => 600, 'easing' => 'ease-out' ],
			'fade-in-down'   => [ 'label' => 'Fade in down',    'keyframe' => 'apFadeInDown',   'duration' => 600, 'easing' => 'ease-out' ],
			'fade-in-left'   => [ 'label' => 'Fade in left',    'keyframe' => 'apFadeInLeft',   'duration' => 600, 'easing' => 'ease-out' ],
			'fade-in-right'  => [ 'label' => 'Fade in right',   'keyframe' => 'apFadeInRight',  'duration' => 600, 'easing' => 'ease-out' ],
			'zoom-in'        => [ 'label' => 'Zoom in',         'keyframe' => 'apZoomIn',       'duration' => 500, 'easing' => 'ease-out' ],
			'zoom-out'       => [ 'label' => 'Zoom out',        'keyframe' => 'apZoomOut',      'duration' => 500, 'easing' => 'ease-out' ],
			'slide-in-up'    => [ 'label' => 'Slide in up',     'keyframe' => 'apSlideInUp',    'duration' => 600, 'easing' => 'ease-out' ],
			'slide-in-down'  => [ 'label' => 'Slide in down',   'keyframe' => 'apSlideInDown',  'duration' => 600, 'easing' => 'ease-out' ],
			'slide-in-left'  => [ 'label' => 'Slide in left',   'keyframe' => 'apSlideInLeft',  'duration' => 600, 'easing' => 'ease-out' ],
			'slide-in-right' => [ 'label' => 'Slide in right',  'keyframe' => 'apSlideInRight', 'duration' => 600, 'easing' => 'ease-out' ],
			'flip-x'         => [ 'label' => 'Flip X',          'keyframe' => 'apFlipX',        'duration' => 700, 'easing' => 'ease-out' ],
			'flip-y'         => [ 'label' => 'Flip Y',          'keyframe' => 'apFlipY',        'duration' => 700, 'easing' => 'ease-out' ],
			'rotate-in'      => [ 'label' => 'Rotate in',       'keyframe' => 'apRotateIn',     'duration' => 700, 'easing' => 'ease-out' ],
		],
		self::FAMILY_HOVER => [
			'lift'  => [ 'label' => 'Lift',  'preset' => 'lift',  'duration' => 200, 'easing' => 'ease-out' ],
			'press' => [ 'label' => 'Press', 'preset' => 'press', 'duration' => 120, 'easing' => 'ease-in' ],
			'glow'  => [ 'label' => 'Glow',  'preset' => 'glow',  'duration' => 250, 'easing' => 'ease-in-out' ],
		],
		self::FAMILY_CONTINUOUS => [
			'pulse'  => [ 'label' => 'Pulse',  'keyframe' => 'apPulse',  'duration' => 2000, 'easing' => 'ease-in-out' ],
			'bounce' => [ 'label' => 'Bounce', 'keyframe' => 'apBounce', 'duration' => 1000, 'easing' => 'ease-in-out' ],
			'spin'   => [ 'label' => 'Spin',   'keyframe' => 'apSpin',   'duration' => 2000, 'easing' => 'linear' ],
			'ping'   => [ 'label' => 'Ping',   'keyframe' => 'apPing',   'duration' => 1000, 'easing' => 'cubic-bezier(0, 0, 0.2, 1)' ],
			'wiggle' => [ 'label' => 'Wiggle', 'keyframe' => 'apWiggle', 'duration' => 800,  'easing' => 'ease-in-out' ],
			'float'  => [ 'label' => 'Float',  'keyframe' => 'apFloat',  'duration' => 3000, 'easing' => 'ease-in-out' ],
		],
	];

	/**
	 * Resolved, validated registry.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	protected array $animations;

	/**
	 * @param  array<string, array<string, mixed>>  $raw  Pre-resolved
	 *                                                    family map.
	 */
	public function __construct( array $raw = [] )
	{
		$this->animations = $this->validate( $raw );
	}

	/**
	 * Builds a registry from the application's merged config + an
	 * optional `theme.json`-derived overrides array.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>|null  $configOverrides
	 * @param  array<string, mixed>       $themeOverrides
	 */
	public static function fromLayers( ?array $configOverrides = null, array $themeOverrides = [] ): self
	{
		$config = $configOverrides ?? ( function_exists( 'config' )
			? (array) config( 'artisanpack.visual-editor.animations', [] )
			: [] );

		$merged = array_replace_recursive( self::DEFAULTS, $config, $themeOverrides );

		// Filter `null` entries at the family + animation level so a
		// theme can drop a built-in by setting it to `null`.
		$cleaned = [];
		foreach ( $merged as $family => $items ) {
			if ( ! is_array( $items ) ) {
				continue;
			}
			$cleaned[ $family ] = array_filter( $items, static fn ( $value ) => null !== $value );
		}

		return new self( $cleaned );
	}

	/**
	 * Returns every registered animation grouped by family.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function all(): array
	{
		return $this->animations;
	}

	/**
	 * Returns the animations for a single family, keyed by slug.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function family( string $family ): array
	{
		return $this->animations[ $family ] ?? [];
	}

	/**
	 * Returns one animation definition or `null` if unregistered.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( string $family, string $key ): ?array
	{
		return $this->animations[ $family ][ $key ] ?? null;
	}

	/**
	 * Checks membership.
	 *
	 * @since 1.1.0
	 */
	public function has( string $family, string $key ): bool
	{
		return isset( $this->animations[ $family ][ $key ] );
	}

	/**
	 * Returns the list of family slugs.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public function families(): array
	{
		return array_keys( $this->animations );
	}

	/**
	 * Reserved built-in names — theme authors cannot override these
	 * under a different family without explicitly setting the original
	 * to `null` first.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public function reservedKeys(): array
	{
		$reserved = [];
		foreach ( self::DEFAULTS as $items ) {
			foreach ( array_keys( $items ) as $key ) {
				$reserved[] = $key;
			}
		}

		return $reserved;
	}

	/**
	 * Normalizes a raw animations map. Validates that:
	 *  - every key is a string of `[a-z0-9_-]` (slug shape)
	 *  - every family is one of {@see self::FAMILIES}
	 *  - every entrance / continuous entry has a non-empty `keyframe`
	 *  - every hover entry has a non-empty `preset`
	 *  - every entry has a `label`, a positive integer `duration` (ms)
	 *    and a non-empty `easing` string
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $raw
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function validate( array $raw ): array
	{
		$normalized = [];

		foreach ( self::FAMILIES as $family ) {
			$normalized[ $family ] = [];
		}

		foreach ( $raw as $family => $items ) {
			if ( ! in_array( $family, self::FAMILIES, true ) ) {
				throw new InvalidArgumentException( sprintf(
					'Animation family "%s" is not recognised. Allowed: %s.',
					(string) $family,
					implode( ', ', self::FAMILIES )
				) );
			}

			if ( ! is_array( $items ) ) {
				throw new InvalidArgumentException( sprintf(
					'Animation family "%s" must be an associative array.',
					$family
				) );
			}

			foreach ( $items as $key => $definition ) {
				if ( ! is_string( $key ) || 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]*$/i', $key ) ) {
					throw new InvalidArgumentException( sprintf(
						'Animation key "%s" must contain only letters, numbers, hyphens, and underscores.',
						(string) $key
					) );
				}

				if ( ! is_array( $definition ) ) {
					throw new InvalidArgumentException( sprintf(
						'Animation "%s.%s" must be defined as an associative array.',
						$family,
						$key
					) );
				}

				$label = $definition['label'] ?? '';
				if ( ! is_string( $label ) || '' === trim( $label ) ) {
					throw new InvalidArgumentException( sprintf(
						'Animation "%s.%s" must declare a non-empty `label`.',
						$family,
						$key
					) );
				}

				$duration = $definition['duration'] ?? 0;
				if ( ! is_int( $duration ) || $duration <= 0 ) {
					throw new InvalidArgumentException( sprintf(
						'Animation "%s.%s" must declare a positive integer `duration` (ms).',
						$family,
						$key
					) );
				}

				$easing = $definition['easing'] ?? '';
				if ( ! is_string( $easing ) || '' === trim( $easing ) ) {
					throw new InvalidArgumentException( sprintf(
						'Animation "%s.%s" must declare a non-empty `easing` string.',
						$family,
						$key
					) );
				}

				if ( self::FAMILY_HOVER === $family ) {
					$preset = $definition['preset'] ?? '';
					if ( ! is_string( $preset ) || '' === trim( $preset ) ) {
						throw new InvalidArgumentException( sprintf(
							'Hover animation "%s" must declare a non-empty `preset` slug.',
							$key
						) );
					}
				} else {
					$keyframe = $definition['keyframe'] ?? '';
					if ( ! is_string( $keyframe ) || '' === trim( $keyframe ) ) {
						throw new InvalidArgumentException( sprintf(
							'Animation "%s.%s" must declare a non-empty `keyframe` name.',
							$family,
							$key
						) );
					}
				}

				$normalized[ $family ][ $key ] = array_merge(
					$definition,
					[
						'key'      => $key,
						'family'   => $family,
						'label'    => $label,
						'duration' => $duration,
						'easing'   => $easing,
					]
				);
			}
		}

		return $normalized;
	}
}
