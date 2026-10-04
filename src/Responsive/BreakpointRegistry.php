<?php

/**
 * Breakpoint registry — responsive design tools (#487, #820).
 *
 * Resolves the editor's active set of breakpoints by merging the
 * application's `config()` overrides (and an optional theme layer)
 * over the package defaults. Highest layer wins on key collision:
 *
 *   1. theme overrides   → `settings.custom.artisanpack.breakpoints`
 *   2. application config → `artisanpack.visual-editor.breakpoints`
 *   3. package defaults
 *
 * Since #820 the model is desktop-first. `base` is the desktop design
 * ("All sizes"); `tablet` and `mobile` are overrides that apply at that
 * size and below, emitted as `@media (max-width:Npx)`. A smaller device
 * inherits from the next larger one, so a Tablet value also applies on
 * phones unless Mobile overrides it.
 *
 * The pre-#820 mobile-first keys (`sm`, `md`, `lg`, `xl`, `2xl`) stay
 * registered as *legacy* `min-width` entries so content saved with them
 * renders exactly as before. They are no longer offered in the viewport
 * switcher. An entry is legacy when it declares `minWidthPx`, and
 * desktop-first when it declares `maxWidthPx`; it can't declare both.
 *
 * Emission order (see {@see prefixes()}) is legacy entries ascending by
 * min-width, then desktop-first entries descending by max-width, so the
 * smaller-device overrides come last and win the cascade.
 *
 * Breakpoints are merged by key. To REMOVE a key, set it to `null` or
 * `''`. The `base` slot is implicit and never stored in the registry.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Responsive;

use InvalidArgumentException;

class BreakpointRegistry
{
	/**
	 * Package defaults. `tablet` / `mobile` are the desktop-first device
	 * overrides (#820); each preview width sits inside its range so the
	 * editor preview matches the front end. The legacy mobile-first
	 * entries keep their pre-#820 values and labels.
	 *
	 * @var array<string, array{minWidthPx?: int, maxWidthPx?: int, previewWidthPx: int, label: string}>
	 */
	public const DEFAULTS = [
		'tablet' => [ 'maxWidthPx' => 1023, 'previewWidthPx' => 768,  'label' => 'Tablet' ],
		'mobile' => [ 'maxWidthPx' => 767,  'previewWidthPx' => 375,  'label' => 'Mobile' ],
		'sm'     => [ 'minWidthPx' => 640,  'previewWidthPx' => 375,  'label' => 'Mobile' ],
		'md'     => [ 'minWidthPx' => 768,  'previewWidthPx' => 768,  'label' => 'Tablet' ],
		'lg'     => [ 'minWidthPx' => 1024, 'previewWidthPx' => 1440, 'label' => 'Desktop' ],
		'xl'     => [ 'minWidthPx' => 1280, 'previewWidthPx' => 1280, 'label' => 'xl+' ],
		'2xl'    => [ 'minWidthPx' => 1536, 'previewWidthPx' => 1536, 'label' => '2xl+' ],
	];

	/**
	 * Implicit base slot — the desktop design, applied at every width
	 * unless an override matches.
	 */
	public const BASE_KEY = 'base';

	/**
	 * Resolved, validated registry in emission order.
	 *
	 * @var array<string, array{minWidthPx: int|null, maxWidthPx: int|null, previewWidthPx: int, label: string}>
	 */
	protected array $breakpoints;

	/**
	 * @param  array<string, int|string|array<string, mixed>>  $raw  Pre-resolved breakpoints.
	 */
	public function __construct( array $raw = [] )
	{
		$resolved = $this->validate( $raw );

		$legacy  = array_filter( $resolved, static fn ( array $spec ): bool => null !== $spec['minWidthPx'] );
		$devices = array_filter( $resolved, static fn ( array $spec ): bool => null !== $spec['maxWidthPx'] );

		uasort( $legacy, static fn ( array $a, array $b ): int => $a['minWidthPx'] <=> $b['minWidthPx'] );
		uasort( $devices, static fn ( array $a, array $b ): int => $b['maxWidthPx'] <=> $a['maxWidthPx'] );

		$this->breakpoints = $legacy + $devices;
	}

	/**
	 * Builds a registry from the application's merged config + an
	 * optional theme-derived overrides array.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, int|string|array<string, mixed>>|null  $configOverrides  Defaults to
	 *                                                                                `config('artisanpack.visual-editor.breakpoints')`.
	 * @param  array<string, int|string|array<string, mixed>>       $themeOverrides   Theme-level overrides.
	 */
	public static function fromLayers( ?array $configOverrides = null, array $themeOverrides = [] ): self
	{
		$config = $configOverrides ?? ( function_exists( 'config' )
			? (array) config( 'artisanpack.visual-editor.breakpoints', [] )
			: [] );

		$merged = self::mergeByKey( self::DEFAULTS, $config, $themeOverrides );

		$cleaned = array_filter( $merged, static fn ( $value ) => null !== $value && '' !== $value );

		return new self( $cleaned );
	}

	/**
	 * Returns the legacy mobile-first breakpoints as
	 * `[key => min-width-px]`, ascending. Desktop-first entries are not
	 * included: they have no min-width. Screen-size visibility still
	 * builds its ranges from this list.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, int>
	 */
	public function all(): array
	{
		$out = [];

		foreach ( $this->breakpoints as $key => $spec ) {
			if ( null !== $spec['minWidthPx'] ) {
				$out[ $key ] = $spec['minWidthPx'];
			}
		}

		return $out;
	}

	/**
	 * Returns every registered breakpoint as `[key => spec]` in emission
	 * order. Each spec carries `minWidthPx` (legacy) or `maxWidthPx`
	 * (desktop-first), the other being `null`.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{minWidthPx: int|null, maxWidthPx: int|null, previewWidthPx: int, label: string}>
	 */
	public function entries(): array
	{
		return $this->breakpoints;
	}

	/**
	 * Returns the min-width (in px) of a legacy breakpoint, `0` for
	 * `base`, and `null` for a desktop-first or unknown key. Use
	 * {@see has()} to test membership and {@see mediaQuery()} to build
	 * a query.
	 *
	 * @since 1.0.0
	 */
	public function get( string $key ): ?int
	{
		if ( self::BASE_KEY === $key ) {
			return 0;
		}

		return $this->breakpoints[ $key ]['minWidthPx'] ?? null;
	}

	/**
	 * Returns the max-width (in px) of a desktop-first breakpoint, or
	 * `null` for `base`, a legacy key, or an unknown key (#820).
	 *
	 * @since 1.12.1
	 */
	public function maxWidth( string $key ): ?int
	{
		return $this->breakpoints[ $key ]['maxWidthPx'] ?? null;
	}

	/**
	 * Whether the key is a legacy mobile-first (`min-width`) entry.
	 *
	 * @since 1.12.1
	 */
	public function isLegacy( string $key ): bool
	{
		return null !== ( $this->breakpoints[ $key ]['minWidthPx'] ?? null );
	}

	/**
	 * The media feature for a breakpoint: `(min-width:640px)` for a
	 * legacy key, `(max-width:767px)` for a desktop-first one, `null`
	 * for `base` or an unknown key. Pass `$spaced` for the
	 * `(min-width: 640px)` form some emitters use. Every emitter builds
	 * its query through this so the two families stay consistent (#820).
	 *
	 * @since 1.12.1
	 */
	public function mediaQuery( string $key, bool $spaced = false ): ?string
	{
		$spec = $this->breakpoints[ $key ] ?? null;

		if ( null === $spec ) {
			return null;
		}

		$separator = $spaced ? ': ' : ':';

		return null !== $spec['minWidthPx']
			? '(min-width' . $separator . $spec['minWidthPx'] . 'px)'
			: '(max-width' . $separator . $spec['maxWidthPx'] . 'px)';
	}

	/**
	 * Returns the canvas preview width (in px) for a single breakpoint,
	 * or `null` if the key isn't registered (#617). `base` returns `0`.
	 *
	 * @since 1.0.0
	 */
	public function previewWidth( string $key ): ?int
	{
		if ( self::BASE_KEY === $key ) {
			return 0;
		}

		return $this->breakpoints[ $key ]['previewWidthPx'] ?? null;
	}

	/**
	 * Returns the display label for a breakpoint, or `null` if the key
	 * isn't registered (#617).
	 *
	 * @since 1.0.0
	 */
	public function label( string $key ): ?string
	{
		return $this->breakpoints[ $key ]['label'] ?? null;
	}

	/**
	 * Every breakpoint key in emission order: legacy entries ascending
	 * by min-width, then desktop-first entries descending by max-width.
	 * Emitting rules in this order lets later (smaller) overrides win.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function prefixes(): array
	{
		return array_keys( $this->breakpoints );
	}

	/**
	 * Desktop-first device keys, largest first — what the viewport
	 * switcher offers after `base` (#820).
	 *
	 * @since 1.12.1
	 *
	 * @return array<int, string>
	 */
	public function devicePrefixes(): array
	{
		return array_values( array_filter( $this->prefixes(), fn ( string $key ): bool => ! $this->isLegacy( $key ) ) );
	}

	/**
	 * Legacy mobile-first keys, ascending (#820).
	 *
	 * @since 1.12.1
	 *
	 * @return array<int, string>
	 */
	public function legacyPrefixes(): array
	{
		return array_values( array_filter( $this->prefixes(), fn ( string $key ): bool => $this->isLegacy( $key ) ) );
	}

	/**
	 * Returns the slugs with `base` prepended, in emission order.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function keysWithBase(): array
	{
		return array_merge( [ self::BASE_KEY ], $this->prefixes() );
	}

	/**
	 * The keys whose values apply at the active breakpoint, highest
	 * precedence first, ending with `base` (#820).
	 *
	 * - `base` or an unknown key → `['base']`.
	 * - A legacy key → itself and the smaller legacy keys, as before.
	 * - A desktop-first key → itself, then the larger device keys
	 *   (Mobile inherits from Tablet), then — unless `$includeLegacy`
	 *   is false — the legacy keys whose min-width rule also matches at
	 *   this key's preview width, so the editor preview matches what the
	 *   front end shows for content that still carries legacy keys.
	 *
	 * @since 1.12.1
	 *
	 * @return array<int, string>
	 */
	public function cascade( string $active, bool $includeLegacy = true ): array
	{
		if ( self::BASE_KEY === $active || ! isset( $this->breakpoints[ $active ] ) ) {
			return [ self::BASE_KEY ];
		}

		$spec = $this->breakpoints[ $active ];

		if ( null !== $spec['minWidthPx'] ) {
			$keys = array_filter(
				$this->legacyPrefixes(),
				fn ( string $key ): bool => $this->breakpoints[ $key ]['minWidthPx'] <= $spec['minWidthPx'],
			);

			return array_merge( array_reverse( array_values( $keys ) ), [ self::BASE_KEY ] );
		}

		$devices = array_filter(
			$this->devicePrefixes(),
			fn ( string $key ): bool => $this->breakpoints[ $key ]['maxWidthPx'] >= $spec['maxWidthPx'],
		);

		$legacy = $includeLegacy
			? array_filter(
				$this->legacyPrefixes(),
				fn ( string $key ): bool => $this->breakpoints[ $key ]['minWidthPx'] <= $spec['previewWidthPx'],
			)
			: [];

		return array_merge(
			array_reverse( array_values( $devices ) ),
			array_reverse( array_values( $legacy ) ),
			[ self::BASE_KEY ],
		);
	}

	/**
	 * Checks membership without the `null`-vs-`0` ambiguity of `get()`.
	 *
	 * @since 1.0.0
	 */
	public function has( string $key ): bool
	{
		return self::BASE_KEY === $key || array_key_exists( $key, $this->breakpoints );
	}

	/**
	 * Serializes the registry for the client bootstrap in emission
	 * order. Each entry carries either `minWidthPx` (legacy) or
	 * `maxWidthPx` (desktop-first).
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, int|string>>
	 */
	public function toArray(): array
	{
		$out = [];

		foreach ( $this->breakpoints as $key => $spec ) {
			$entry = [ 'key' => $key ];

			if ( null !== $spec['minWidthPx'] ) {
				$entry['minWidthPx'] = $spec['minWidthPx'];
			} else {
				$entry['maxWidthPx'] = $spec['maxWidthPx'];
			}

			$entry['previewWidthPx'] = $spec['previewWidthPx'];
			$entry['label']          = $spec['label'];

			$out[] = $entry;
		}

		return $out;
	}

	/**
	 * Validates a raw breakpoint map. Accepts:
	 *   - integer pixel values (`640`) and `Npx` strings — legacy min-width
	 *   - `[ 'minWidthPx' => 640, … ]` — legacy min-width
	 *   - `[ 'maxWidthPx' => 767, 'previewWidthPx' => 375, 'label' => 'Mobile' ]` — desktop-first
	 *
	 * Rejects empty keys, the reserved `base` key, non-positive widths,
	 * entries with both or neither width, duplicate widths within a
	 * family, a desktop-first preview width above its max-width, and
	 * invalid labels.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, int|string|array<string, mixed>>  $raw
	 *
	 * @return array<string, array{minWidthPx: int|null, maxWidthPx: int|null, previewWidthPx: int, label: string}>
	 */
	public function validate( array $raw ): array
	{
		$cleaned = [];
		$seenMin = [];
		$seenMax = [];

		foreach ( $raw as $key => $value ) {
			if ( ! is_string( $key ) || '' === trim( $key ) ) {
				throw new InvalidArgumentException( 'Breakpoint key must be a non-empty string.' );
			}

			if ( self::BASE_KEY === $key ) {
				throw new InvalidArgumentException( sprintf(
					'Breakpoint key "%s" is reserved for the implicit base slot.',
					self::BASE_KEY
				) );
			}

			if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]*$/i', $key ) ) {
				throw new InvalidArgumentException( sprintf(
					'Breakpoint key "%s" must contain only letters, numbers, hyphens, and underscores.',
					$key
				) );
			}

			$spec = $this->normalizeEntry( $value, $key );

			if ( null !== $spec['minWidthPx'] ) {
				if ( in_array( $spec['minWidthPx'], $seenMin, true ) ) {
					throw new InvalidArgumentException( sprintf(
						'Breakpoint key "%s" has the same min-width (%dpx) as another breakpoint.',
						$key,
						$spec['minWidthPx']
					) );
				}

				$seenMin[] = $spec['minWidthPx'];
			} else {
				if ( in_array( $spec['maxWidthPx'], $seenMax, true ) ) {
					throw new InvalidArgumentException( sprintf(
						'Breakpoint key "%s" has the same max-width (%dpx) as another breakpoint.',
						$key,
						$spec['maxWidthPx']
					) );
				}

				$seenMax[] = $spec['maxWidthPx'];
			}

			$cleaned[ $key ] = $spec;
		}

		return $cleaned;
	}

	/**
	 * Merges breakpoint layers by key. A scalar keeps the width family
	 * of the entry it overrides (so `'mobile' => '600px'` sets the
	 * mobile max-width); an array fragment that names one width drops
	 * the other, so a layer can switch an entry's family.
	 *
	 * @param  array<string, mixed>  ...$layers
	 *
	 * @return array<string, mixed>
	 */
	protected static function mergeByKey( array ...$layers ): array
	{
		$merged = [];

		foreach ( $layers as $layer ) {
			foreach ( $layer as $key => $value ) {
				if ( null === $value || '' === $value ) {
					unset( $merged[ $key ] );
					continue;
				}

				$prior = isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ? $merged[ $key ] : null;

				if ( ! is_array( $value ) ) {
					$value = null !== $prior && array_key_exists( 'maxWidthPx', $prior )
						? [ 'maxWidthPx' => $value ]
						: [ 'minWidthPx' => $value ];
				}

				if ( null !== $prior ) {
					if ( array_key_exists( 'minWidthPx', $value ) ) {
						unset( $prior['maxWidthPx'] );
					}

					if ( array_key_exists( 'maxWidthPx', $value ) ) {
						unset( $prior['minWidthPx'] );
					}
				}

				$entry = null !== $prior ? array_replace( $prior, $value ) : $value;

				// A layer that narrows a device without naming a preview
				// width (`'mobile' => '600px'`) inherits the old preview
				// (375 / 768). Clamp it into the new range instead of
				// failing validation on every request.
				if (
					null !== $prior
					&& array_key_exists( 'maxWidthPx', $value )
					&& ! array_key_exists( 'previewWidthPx', $value )
					&& isset( $entry['previewWidthPx'] )
				) {
					$max     = self::pixelValue( $entry['maxWidthPx'] );
					$preview = self::pixelValue( $entry['previewWidthPx'] );

					if ( null !== $max && null !== $preview && $preview > $max ) {
						$entry['previewWidthPx'] = $max;
					}
				}

				$merged[ $key ] = $entry;
			}
		}

		return $merged;
	}

	/**
	 * Loose pixel parse for merge-time clamping; validation reports bad
	 * values later.
	 *
	 * @since 1.12.1
	 *
	 * @param  mixed  $value
	 */
	protected static function pixelValue( $value ): ?int
	{
		if ( is_int( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) && 1 === preg_match( '/^\s*(\d+)(px)?\s*$/i', $value, $matches ) ) {
			return (int) $matches[1];
		}

		return null;
	}

	/**
	 * @param  mixed   $value
	 *
	 * @return array{minWidthPx: int|null, maxWidthPx: int|null, previewWidthPx: int, label: string}
	 */
	protected function normalizeEntry( $value, string $key ): array
	{
		if ( is_array( $value ) ) {
			return $this->normalizeObjectEntry( $value, $key );
		}

		$pixels = $this->parsePixels( $value, $key );

		return [
			'minWidthPx'     => $pixels,
			'maxWidthPx'     => null,
			'previewWidthPx' => $pixels,
			'label'          => $key,
		];
	}

	/**
	 * @param  array<string, mixed>  $entry
	 *
	 * @return array{minWidthPx: int|null, maxWidthPx: int|null, previewWidthPx: int, label: string}
	 */
	protected function normalizeObjectEntry( array $entry, string $key ): array
	{
		$hasMin = array_key_exists( 'minWidthPx', $entry ) && null !== $entry['minWidthPx'];
		$hasMax = array_key_exists( 'maxWidthPx', $entry ) && null !== $entry['maxWidthPx'];

		if ( $hasMin && $hasMax ) {
			throw new InvalidArgumentException( sprintf(
				'Breakpoint "%s" declares both `minWidthPx` and `maxWidthPx`; use one.',
				$key
			) );
		}

		if ( ! $hasMin && ! $hasMax ) {
			throw new InvalidArgumentException( sprintf(
				'Breakpoint "%s" is missing the required `maxWidthPx` (or legacy `minWidthPx`) field.',
				$key
			) );
		}

		$minWidthPx = $hasMin ? $this->parsePixels( $entry['minWidthPx'], $key ) : null;
		$maxWidthPx = $hasMax ? $this->parsePixels( $entry['maxWidthPx'], $key ) : null;

		$previewWidthPx = $minWidthPx ?? $maxWidthPx;
		if ( array_key_exists( 'previewWidthPx', $entry ) && null !== $entry['previewWidthPx'] ) {
			$previewWidthPx = $this->parsePreviewPixels( $entry['previewWidthPx'], $key );
		}

		if ( null !== $maxWidthPx && $previewWidthPx > $maxWidthPx ) {
			throw new InvalidArgumentException( sprintf(
				'Breakpoint "%s" `previewWidthPx` (%dpx) must not exceed its `maxWidthPx` (%dpx), or the editor preview would not match the front end.',
				$key,
				$previewWidthPx,
				$maxWidthPx
			) );
		}

		$label = $key;
		if ( array_key_exists( 'label', $entry ) && null !== $entry['label'] ) {
			if ( ! is_string( $entry['label'] ) ) {
				throw new InvalidArgumentException( sprintf(
					'Breakpoint "%s" label must be a string.',
					$key
				) );
			}

			$trimmed = trim( $entry['label'] );
			if ( '' === $trimmed ) {
				throw new InvalidArgumentException( sprintf(
					'Breakpoint "%s" label must not be empty.',
					$key
				) );
			}

			$label = $trimmed;
		}

		return [
			'minWidthPx'     => $minWidthPx,
			'maxWidthPx'     => $maxWidthPx,
			'previewWidthPx' => $previewWidthPx,
			'label'          => $label,
		];
	}

	/**
	 * @param  mixed   $value
	 * @param  string  $key   Used for the error message only.
	 */
	protected function parsePixels( $value, string $key ): int
	{
		if ( is_int( $value ) ) {
			$pixels = $value;
		} elseif ( is_string( $value ) ) {
			$trimmed = trim( $value );

			if ( 1 !== preg_match( '/^(\d+)(px)?$/i', $trimmed, $matches ) ) {
				throw new InvalidArgumentException( sprintf(
					'Breakpoint "%s" has invalid value "%s". Expected an integer or a `Npx` string.',
					$key,
					$value
				) );
			}

			$pixels = (int) $matches[1];
		} else {
			throw new InvalidArgumentException( sprintf(
				'Breakpoint "%s" must be an integer or a `Npx` string.',
				$key
			) );
		}

		if ( $pixels <= 0 ) {
			throw new InvalidArgumentException( sprintf(
				'Breakpoint "%s" must be a positive pixel value, got %d.',
				$key,
				$pixels
			) );
		}

		return $pixels;
	}

	/**
	 * Like {@see parsePixels()} but for the `previewWidthPx` field —
	 * error message names the field explicitly so authors know which
	 * property tripped validation.
	 *
	 * @param  mixed   $value
	 * @param  string  $key
	 */
	protected function parsePreviewPixels( $value, string $key ): int
	{
		if ( is_int( $value ) ) {
			$pixels = $value;
		} elseif ( is_string( $value ) ) {
			$trimmed = trim( $value );

			if ( 1 !== preg_match( '/^(\d+)(px)?$/i', $trimmed, $matches ) ) {
				throw new InvalidArgumentException( sprintf(
					'Breakpoint "%s" `previewWidthPx` has invalid value "%s". Expected an integer or a `Npx` string.',
					$key,
					is_scalar( $value ) ? (string) $value : gettype( $value )
				) );
			}

			$pixels = (int) $matches[1];
		} else {
			throw new InvalidArgumentException( sprintf(
				'Breakpoint "%s" `previewWidthPx` must be an integer or a `Npx` string.',
				$key
			) );
		}

		if ( $pixels <= 0 ) {
			throw new InvalidArgumentException( sprintf(
				'Breakpoint "%s" `previewWidthPx` must be a positive pixel value, got %d.',
				$key,
				$pixels
			) );
		}

		return $pixels;
	}
}
