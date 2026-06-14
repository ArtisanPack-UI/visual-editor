<?php

/**
 * Keyframe registry — block animations (#489).
 *
 * Owns the `@keyframes` CSS that the entrance + continuous families
 * reference. Built-ins are baked in. Theme authors can register custom
 * keyframes via `theme.json` → `settings.custom.artisanpack.keyframes`
 * or via the Site Editor → Styles → Animations UI (which persists into
 * the Global Styles JSON).
 *
 * Custom keyframes are stored as an array of stops:
 *
 *     [
 *         'name' => 'confetti',
 *         'stops' => [
 *             [ 'at' => '0%',   'transform' => 'translateY(0)' ],
 *             [ 'at' => '50%',  'transform' => 'translateY(-12px) rotate(10deg)' ],
 *             [ 'at' => '100%', 'transform' => 'translateY(0)' ],
 *         ],
 *     ]
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

class KeyframeRegistry
{
	/**
	 * The list of CSS properties an editor-authored stop is allowed to
	 * touch. Restricting the set keeps the persisted JSON to a known
	 * shape and avoids surprises like `position` or `display` jumping
	 * during an animation.
	 *
	 * @var array<int, string>
	 */
	public const ALLOWED_STOP_PROPERTIES = [
		'transform',
		'opacity',
		'filter',
		'color',
		'background-color',
		'box-shadow',
	];

	/**
	 * Built-in `@keyframes` definitions. Names match the `keyframe`
	 * values referenced by {@see AnimationRegistry::DEFAULTS}.
	 *
	 * @var array<string, string>
	 */
	public const BUILT_INS = [
		'apFadeIn'       => '0% { opacity: 0; } 100% { opacity: 1; }',
		'apFadeInUp'     => '0% { opacity: 0; transform: translateY(16px); } 100% { opacity: 1; transform: translateY(0); }',
		'apFadeInDown'   => '0% { opacity: 0; transform: translateY(-16px); } 100% { opacity: 1; transform: translateY(0); }',
		'apFadeInLeft'   => '0% { opacity: 0; transform: translateX(-16px); } 100% { opacity: 1; transform: translateX(0); }',
		'apFadeInRight'  => '0% { opacity: 0; transform: translateX(16px); } 100% { opacity: 1; transform: translateX(0); }',
		'apZoomIn'       => '0% { opacity: 0; transform: scale(0.92); } 100% { opacity: 1; transform: scale(1); }',
		'apZoomOut'      => '0% { opacity: 0; transform: scale(1.08); } 100% { opacity: 1; transform: scale(1); }',
		'apSlideInUp'    => '0% { transform: translateY(32px); } 100% { transform: translateY(0); }',
		'apSlideInDown'  => '0% { transform: translateY(-32px); } 100% { transform: translateY(0); }',
		'apSlideInLeft'  => '0% { transform: translateX(-32px); } 100% { transform: translateX(0); }',
		'apSlideInRight' => '0% { transform: translateX(32px); } 100% { transform: translateX(0); }',
		'apFlipX'        => '0% { transform: rotateX(90deg); opacity: 0; } 100% { transform: rotateX(0); opacity: 1; }',
		'apFlipY'        => '0% { transform: rotateY(90deg); opacity: 0; } 100% { transform: rotateY(0); opacity: 1; }',
		'apRotateIn'     => '0% { transform: rotate(-12deg) scale(0.95); opacity: 0; } 100% { transform: rotate(0) scale(1); opacity: 1; }',
		'apPulse'        => '0%, 100% { transform: scale(1); } 50% { transform: scale(1.04); }',
		'apBounce'       => '0%, 100% { transform: translateY(0); } 50% { transform: translateY(-12px); }',
		'apSpin'         => '0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); }',
		'apPing'         => '0% { transform: scale(1); opacity: 1; } 75%, 100% { transform: scale(2); opacity: 0; }',
		'apWiggle'       => '0%, 100% { transform: rotate(-2deg); } 50% { transform: rotate(2deg); }',
		'apFloat'        => '0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); }',
	];

	/**
	 * Custom keyframes by sanitised name.
	 *
	 * @var array<string, array<int, array<string, string>>>
	 */
	protected array $custom = [];

	/**
	 * @param  array<int, array<string, mixed>>  $rawCustom
	 */
	public function __construct( array $rawCustom = [] )
	{
		foreach ( $rawCustom as $entry ) {
			$normalised = $this->validateOne( $entry );
			$this->custom[ $normalised['name'] ] = $normalised['stops'];
		}
	}

	/**
	 * Builds the registry from theme.json + Global Styles JSON entries.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, array<string, mixed>>  $themeKeyframes
	 * @param  array<int, array<string, mixed>>  $globalStylesKeyframes
	 */
	public static function fromLayers( array $themeKeyframes = [], array $globalStylesKeyframes = [] ): self
	{
		// `fromLayers()` is wired into a Laravel scoped binding, so a
		// throw here would crash every request that resolves the
		// registry (e.g. via `<x-ve-blocks>`). Skip + log invalid
		// entries instead. The strict-throw behaviour stays on the
		// direct constructor path for tests and programmatic use.
		$registry = new self();

		// Validate as we iterate, in layer order. Global Styles wins on
		// name collision only when its entry is VALID — an invalid
		// global-styles entry must not overwrite a valid theme entry,
		// otherwise the theme keyframe vanishes entirely.
		foreach ( [ $themeKeyframes, $globalStylesKeyframes ] as $layer ) {
			foreach ( $layer as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}

				try {
					$normalised = $registry->validateOne( $entry );
					$registry->custom[ $normalised['name'] ] = $normalised['stops'];
				} catch ( InvalidArgumentException $e ) {
					if ( function_exists( 'logger' ) ) {
						logger()->warning( '[block-animations] Skipping invalid custom keyframe: ' . $e->getMessage() );
					}
				}
			}
		}

		return $registry;
	}

	/**
	 * Returns the list of registered keyframe names (built-ins + custom).
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public function names(): array
	{
		return array_values( array_unique( array_merge(
			array_keys( self::BUILT_INS ),
			array_keys( $this->custom )
		) ) );
	}

	/**
	 * Returns just the custom keyframe names.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public function customNames(): array
	{
		return array_keys( $this->custom );
	}

	/**
	 * Returns the stops for a custom keyframe, or `null` if it's not
	 * registered (or refers to a built-in).
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array<string, string>>|null
	 */
	public function stops( string $name ): ?array
	{
		return $this->custom[ $name ] ?? null;
	}

	/**
	 * Reports whether the registry knows the name as a built-in.
	 *
	 * @since 1.1.0
	 */
	public function isBuiltIn( string $name ): bool
	{
		return array_key_exists( $name, self::BUILT_INS );
	}

	/**
	 * Reports whether the registry knows the name at all.
	 *
	 * @since 1.1.0
	 */
	public function has( string $name ): bool
	{
		return $this->isBuiltIn( $name ) || array_key_exists( $name, $this->custom );
	}

	/**
	 * Emits the CSS for every registered `@keyframes` block — built-ins
	 * first, then custom. Suitable for inclusion in a `<style>` tag.
	 *
	 * @since 1.1.0
	 */
	public function emitCss(): string
	{
		$out = '';

		foreach ( self::BUILT_INS as $name => $body ) {
			$out .= sprintf( '@keyframes %s { %s } ', $name, $body );
		}

		foreach ( $this->custom as $name => $stops ) {
			$out .= sprintf( '@keyframes %s { %s } ', $name, $this->renderStops( $stops ) );
		}

		return rtrim( $out );
	}

	/**
	 * Emits CSS for one custom keyframe by name.
	 *
	 * @since 1.1.0
	 */
	public function emitOne( string $name ): string
	{
		if ( $this->isBuiltIn( $name ) ) {
			return sprintf( '@keyframes %s { %s }', $name, self::BUILT_INS[ $name ] );
		}

		$stops = $this->custom[ $name ] ?? null;
		if ( null === $stops ) {
			return '';
		}

		return sprintf( '@keyframes %s { %s }', $name, $this->renderStops( $stops ) );
	}

	/**
	 * @param  array<int, array<string, string>>  $stops
	 */
	protected function renderStops( array $stops ): string
	{
		$parts = [];

		foreach ( $stops as $stop ) {
			$at = $stop['at'] ?? '';
			if ( '' === $at ) {
				continue;
			}

			$declarations = [];
			foreach ( $stop as $property => $value ) {
				if ( 'at' === $property ) {
					continue;
				}
				$declarations[] = sprintf( '%s: %s;', $property, $value );
			}

			if ( [] === $declarations ) {
				continue;
			}

			$parts[] = sprintf( '%s { %s }', $at, implode( ' ', $declarations ) );
		}

		return implode( ' ', $parts );
	}

	/**
	 * @param  array<string, mixed>  $entry
	 *
	 * @return array{name: string, stops: array<int, array<string, string>>}
	 */
	protected function validateOne( array $entry ): array
	{
		$name = $entry['name'] ?? '';
		if ( ! is_string( $name ) || '' === trim( $name ) ) {
			throw new InvalidArgumentException( 'Custom keyframe must declare a non-empty `name`.' );
		}

		if ( 1 !== preg_match( '/^[a-z][a-z0-9_-]*$/i', $name ) ) {
			throw new InvalidArgumentException( sprintf(
				'Custom keyframe name "%s" must start with a letter and contain only letters, numbers, hyphens, and underscores.',
				$name
			) );
		}

		// Case-insensitive collision check mirrors the client-side
		// CustomKeyframeEditor so a `apfadein` config can't end-run the
		// reservation that the editor UI enforces on `apFadeIn`.
		$nameLower = strtolower( $name );
		foreach ( array_keys( self::BUILT_INS ) as $builtIn ) {
			if ( strtolower( $builtIn ) === $nameLower ) {
				throw new InvalidArgumentException( sprintf(
					'Custom keyframe name "%s" collides with a built-in. Built-in names are reserved.',
					$name
				) );
			}
		}

		$stops = $entry['stops'] ?? [];
		if ( ! is_array( $stops ) || count( $stops ) < 2 ) {
			throw new InvalidArgumentException( sprintf(
				'Custom keyframe "%s" must declare at least two `stops`.',
				$name
			) );
		}

		$normalisedStops = [];

		foreach ( $stops as $stop ) {
			if ( ! is_array( $stop ) ) {
				throw new InvalidArgumentException( sprintf(
					'Custom keyframe "%s" has a non-array stop.',
					$name
				) );
			}

			$at = $stop['at'] ?? '';
			if ( ! is_string( $at ) || 1 !== preg_match( '/^(0|[1-9]\d?|100)(%)?$/', trim( $at ) ) ) {
				throw new InvalidArgumentException( sprintf(
					'Custom keyframe "%s" stop has invalid `at` value "%s". Expected a percentage 0%%–100%%.',
					$name,
					(string) $at
				) );
			}

			$normalised = [ 'at' => str_ends_with( trim( $at ), '%' ) ? trim( $at ) : trim( $at ) . '%' ];

			foreach ( $stop as $property => $value ) {
				if ( 'at' === $property ) {
					continue;
				}

				if ( ! in_array( $property, self::ALLOWED_STOP_PROPERTIES, true ) ) {
					throw new InvalidArgumentException( sprintf(
						'Custom keyframe "%s" stop has unsupported property "%s". Allowed: %s.',
						$name,
						(string) $property,
						implode( ', ', self::ALLOWED_STOP_PROPERTIES )
					) );
				}

				if ( ! is_string( $value ) || '' === trim( $value ) ) {
					throw new InvalidArgumentException( sprintf(
						'Custom keyframe "%s" stop property "%s" must be a non-empty string.',
						$name,
						$property
					) );
				}

				// Reject anything that smells like an attempt to escape
				// the CSS value context. Animations never need angle
				// brackets, braces, or semicolons inside a value.
				if ( 1 === preg_match( '/[<>{};]/', $value ) ) {
					throw new InvalidArgumentException( sprintf(
						'Custom keyframe "%s" stop property "%s" contains disallowed characters.',
						$name,
						$property
					) );
				}

				$normalised[ $property ] = trim( $value );
			}

			$normalisedStops[] = $normalised;
		}

		return [ 'name' => $name, 'stops' => $normalisedStops ];
	}
}
