<?php

/**
 * Breakpoint registry — responsive design tools (#487).
 *
 * Resolves the editor's active set of breakpoints by merging the host
 * theme's declarations into the application's `config()` overrides and
 * finally the package's Tailwind v4 defaults. Highest layer wins on key
 * collision:
 *
 *   1. theme.json → `settings.custom.artisanpack.breakpoints`
 *   2. application config → `artisanpack.visual-editor.breakpoints`
 *   3. package defaults  → Tailwind v4 min-width tokens
 *
 * Breakpoints are merged by key via `array_replace()` —  a theme that
 * ships a new `3xl` key adds it; omitting a key keeps the lower
 * layer's value (defaults win unless explicitly overridden). To
 * REMOVE a key, set it to `null` or `''` — `fromLayers()` filters
 * those out after the merge. The resulting registry is sorted
 * ascending by the resolved pixel value so callers can iterate it
 * mobile-first without re-sorting.
 *
 * The `base` slot is implicit (always present, no min-width) and is
 * NEVER stored in the registry itself — the registry only describes
 * the named, prefixed breakpoints.
 *
 * #617 extends each entry with an optional `label` (viewport switcher
 * display string) and `previewWidthPx` (canvas iframe width the
 * switcher previews at). Both are accepted alongside the historical
 * scalar form (`'sm' => '640px'`) — a scalar entry hydrates to
 * `{ minWidthPx, previewWidthPx: minWidthPx, label: key }` on load,
 * so no migration is required.
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
	 * Tailwind v4 defaults, extended with #617 preview widths and
	 * device-friendly labels. Preview widths for `sm`/`md`/`lg` map
	 * to real device viewports (iPhone / iPad portrait / desktop) —
	 * they intentionally diverge from `minWidthPx` for `sm` because
	 * Tailwind's mobile breakpoint kicks in at 640px but the actual
	 * device is a 375-wide phone.
	 *
	 * @var array<string, array{minWidthPx: int, previewWidthPx: int, label: string}>
	 */
	public const DEFAULTS = [
		'sm'  => [ 'minWidthPx' => 640,  'previewWidthPx' => 375,  'label' => 'Mobile' ],
		'md'  => [ 'minWidthPx' => 768,  'previewWidthPx' => 768,  'label' => 'Tablet' ],
		'lg'  => [ 'minWidthPx' => 1024, 'previewWidthPx' => 1440, 'label' => 'Desktop' ],
		// `xl+` / `2xl+` preserve the pre-#617 cascade signal
		// (`this size and up`) for the two breakpoints without a
		// device-friendly name. `sm` / `md` / `lg` get real device
		// labels above; `xl` / `2xl` fall back to a decorated key so
		// hosts don't lose the mobile-first affordance on upgrade.
		'xl'  => [ 'minWidthPx' => 1280, 'previewWidthPx' => 1280, 'label' => 'xl+' ],
		'2xl' => [ 'minWidthPx' => 1536, 'previewWidthPx' => 1536, 'label' => '2xl+' ],
	];

	/**
	 * Implicit base slot — always present alongside the registered
	 * breakpoints. Stored separately because it has no min-width and
	 * cannot be overridden.
	 */
	public const BASE_KEY = 'base';

	/**
	 * Resolved, validated, ascending-sorted registry.
	 *
	 * @var array<string, array{minWidthPx: int, previewWidthPx: int, label: string}>
	 */
	protected array $breakpoints;

	/**
	 * @param  array<string, int|string|array<string, mixed>>  $raw  Pre-resolved breakpoints.
	 *                                                               Pass the output of
	 *                                                               {@see fromLayers()}
	 *                                                               unless you're constructing
	 *                                                               a one-off registry in a test.
	 */
	public function __construct( array $raw = [] )
	{
		$resolved = $this->validate( $raw );
		uasort( $resolved, static fn ( array $a, array $b ) => $a['minWidthPx'] <=> $b['minWidthPx'] );
		$this->breakpoints = $resolved;
	}

	/**
	 * Builds a registry from the application's merged config + an
	 * optional `theme.json`-derived overrides array. Use this from the
	 * service provider; tests can also construct directly.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, int|string|array<string, mixed>>|null  $configOverrides  Defaults to
	 *                                                                                `config('artisanpack.visual-editor.breakpoints')`.
	 * @param  array<string, int|string|array<string, mixed>>       $themeOverrides   `settings.custom.artisanpack.breakpoints`
	 *                                                                                from the active `theme.json`.
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
	 * Returns every registered breakpoint as `[key => min-width-px]`,
	 * ascending by pixel value. The implicit `base` slot is NOT
	 * included — callers that need it can prepend `'base' => 0` to the
	 * result. Preserved as `array<string, int>` for callers built
	 * before #617 (Tailwind class emission, media query building);
	 * use {@see entries()} for the extended `{ minWidthPx,
	 * previewWidthPx, label }` shape.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, int>
	 */
	public function all(): array
	{
		$out = [];

		foreach ( $this->breakpoints as $key => $spec ) {
			$out[ $key ] = $spec['minWidthPx'];
		}

		return $out;
	}

	/**
	 * Returns every registered breakpoint as `[key => spec]`, ascending
	 * by pixel value. Each spec is a `{ minWidthPx, previewWidthPx,
	 * label }` array (#617). The implicit `base` slot is NOT included —
	 * callers that need it can prepend their own.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{minWidthPx: int, previewWidthPx: int, label: string}>
	 */
	public function entries(): array
	{
		return $this->breakpoints;
	}

	/**
	 * Returns the min-width (in px) for a single breakpoint, or `null`
	 * if the key isn't registered. The implicit `base` slot returns
	 * `0`. Preserved on the read-side for callers still on the pre-#617
	 * signature (Tailwind class emission, media query building).
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
	 * Returns the canvas preview width (in px) for a single breakpoint,
	 * or `null` if the key isn't registered (#617). The implicit `base`
	 * slot returns `0` (no width constraint applied).
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
	 * Returns just the breakpoint slugs (no widths) in ascending order
	 * — convenient for emitting Tailwind class prefixes (`sm:`, `md:`,
	 * `lg:`).
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
	 * Returns the slugs with `base` prepended — the full ordered list
	 * the value resolver walks when cascading.
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
	 * Checks membership without the `null`-vs-`0` ambiguity of
	 * `get()`.
	 *
	 * @since 1.0.0
	 */
	public function has( string $key ): bool
	{
		return self::BASE_KEY === $key || array_key_exists( $key, $this->breakpoints );
	}

	/**
	 * Serializes the registry for the client bootstrap — mirrors the
	 * TypeScript `Breakpoint[]` shape the JS registry expects. Returns
	 * an ascending-sorted list of `{ key, minWidthPx, previewWidthPx,
	 * label }` objects (#617).
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{key: string, minWidthPx: int, previewWidthPx: int, label: string}>
	 */
	public function toArray(): array
	{
		$out = [];

		foreach ( $this->breakpoints as $key => $spec ) {
			$out[] = [
				'key'            => $key,
				'minWidthPx'     => $spec['minWidthPx'],
				'previewWidthPx' => $spec['previewWidthPx'],
				'label'          => $spec['label'],
			];
		}

		return $out;
	}

	/**
	 * Validates a raw breakpoint map. Accepts:
	 *   - integer pixel values (`640`)
	 *   - CSS-length strings ending in `px` (`'640px'`)
	 *   - object form `[ 'minWidthPx' => 640, 'previewWidthPx' => 375, 'label' => 'Mobile' ]`
	 *
	 * Rejects empty keys, the reserved `base` key, non-positive widths,
	 * duplicate min-widths, invalid label types, and anything that
	 * doesn't parse to a positive integer pixel value.
	 *
	 * Throws on the first failure with a descriptive message so theme
	 * authors get actionable feedback when their `theme.json` is bad.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, int|string|array<string, mixed>>  $raw
	 *
	 * @return array<string, array{minWidthPx: int, previewWidthPx: int, label: string}>
	 */
	public function validate( array $raw ): array
	{
		$cleaned = [];
		$seen    = [];

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

			if ( in_array( $spec['minWidthPx'], $seen, true ) ) {
				throw new InvalidArgumentException( sprintf(
					'Breakpoint key "%s" has the same min-width (%dpx) as another breakpoint.',
					$key,
					$spec['minWidthPx']
				) );
			}

			$cleaned[ $key ] = $spec;
			$seen[]          = $spec['minWidthPx'];
		}

		return $cleaned;
	}

	/**
	 * Merges layered breakpoint arrays by key.
	 *
	 * Each layer's entry is normalised to an object-shape fragment
	 * BEFORE merging, so partial overrides survive intact through the
	 * layer stack:
	 *
	 *   - Scalar entry (`'lg' => 1100`)              → `[ 'minWidthPx' => 1100 ]`
	 *   - Full object                                 → same array, unchanged
	 *   - Partial object (`[ 'label' => 'iPhone' ]`) → same array, unchanged
	 *
	 * Once every layer's entry is a fragment, `array_replace` merges
	 * fields shallowly: a higher layer's `minWidthPx` wins, but any
	 * field it omits keeps whatever the lower layer set. This is what
	 * lets `'lg' => 1100` (scalar) sit on top of the DEFAULTS' `lg`
	 * object without wiping the `Desktop` label or the `1440px` preview
	 * width — the scalar contributes only `minWidthPx`. It also fixes
	 * the reverse: a partial object `[ 'label' => 'Big' ]` layered on
	 * top of a scalar `1024` no longer throws `missing minWidthPx`,
	 * because the scalar already normalised to `[ 'minWidthPx' => 1024 ]`
	 * and its field carries through.
	 *
	 * `null` and `''` values still remove any prior entry at that key.
	 *
	 * @param  array<string, int|string|array<string, mixed>>  ...$layers  Lowest priority first.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected static function mergeByKey( array ...$layers ): array
	{
		$merged = [];

		foreach ( $layers as $layer ) {
			foreach ( $layer as $key => $value ) {
				if ( null === $value || '' === $value ) {
					// Explicit removal — drop any prior value.
					unset( $merged[ $key ] );
					continue;
				}

				$fragment = is_array( $value )
					? $value
					: [ 'minWidthPx' => $value ];

				$merged[ $key ] = isset( $merged[ $key ] ) && is_array( $merged[ $key ] )
					? array_replace( $merged[ $key ], $fragment )
					: $fragment;
			}
		}

		return $merged;
	}

	/**
	 * Normalizes a single raw entry (scalar or object form) into the
	 * canonical `{ minWidthPx, previewWidthPx, label }` shape. Scalar
	 * entries fall back to `previewWidthPx = minWidthPx` and
	 * `label = key`; object entries fill missing fields from those
	 * same defaults.
	 *
	 * @param  int|string|array<string, mixed>  $value
	 * @param  string                           $key    Used for error messages only.
	 *
	 * @return array{minWidthPx: int, previewWidthPx: int, label: string}
	 */
	protected function normalizeEntry( $value, string $key ): array
	{
		if ( is_array( $value ) ) {
			return $this->normalizeObjectEntry( $value, $key );
		}

		$pixels = $this->parsePixels( $value, $key );

		return [
			'minWidthPx'     => $pixels,
			'previewWidthPx' => $pixels,
			'label'          => $key,
		];
	}

	/**
	 * Normalizes an object-form entry. Requires `minWidthPx`; fills
	 * `previewWidthPx` from `minWidthPx` and `label` from the key when
	 * omitted so authors can supply partial objects.
	 *
	 * @param  array<string, mixed>  $entry
	 *
	 * @return array{minWidthPx: int, previewWidthPx: int, label: string}
	 */
	protected function normalizeObjectEntry( array $entry, string $key ): array
	{
		if ( ! array_key_exists( 'minWidthPx', $entry ) ) {
			throw new InvalidArgumentException( sprintf(
				'Breakpoint "%s" is missing the required `minWidthPx` field.',
				$key
			) );
		}

		$minWidthPx = $this->parsePixels( $entry['minWidthPx'], $key );

		$previewWidthPx = $minWidthPx;
		if ( array_key_exists( 'previewWidthPx', $entry ) && null !== $entry['previewWidthPx'] ) {
			$previewWidthPx = $this->parsePreviewPixels( $entry['previewWidthPx'], $key );
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
