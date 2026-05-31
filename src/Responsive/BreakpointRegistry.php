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
	 * Tailwind v4 defaults, used when nothing higher in the stack
	 * overrides them.
	 *
	 * @var array<string, string>
	 */
	public const DEFAULTS = [
		'sm'  => '640px',
		'md'  => '768px',
		'lg'  => '1024px',
		'xl'  => '1280px',
		'2xl' => '1536px',
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
	 * @var array<string, int>  Keys are breakpoint slugs; values are the
	 *                          min-width in pixels.
	 */
	protected array $breakpoints;

	/**
	 * @param  array<string, int|string>  $raw  Pre-resolved breakpoints.
	 *                                          Pass the output of
	 *                                          {@see fromLayers()}
	 *                                          unless you're constructing
	 *                                          a one-off registry in a test.
	 */
	public function __construct( array $raw = [] )
	{
		$resolved          = $this->validate( $raw );
		asort( $resolved );
		$this->breakpoints = $resolved;
	}

	/**
	 * Builds a registry from the application's merged config + an
	 * optional `theme.json`-derived overrides array. Use this from the
	 * service provider; tests can also construct directly.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, int|string>|null  $configOverrides  Defaults to
	 *                                                           `config('artisanpack.visual-editor.breakpoints')`.
	 * @param  array<string, int|string>       $themeOverrides   `settings.custom.artisanpack.breakpoints`
	 *                                                           from the active `theme.json`.
	 */
	public static function fromLayers( ?array $configOverrides = null, array $themeOverrides = [] ): self
	{
		$config = $configOverrides ?? ( function_exists( 'config' )
			? (array) config( 'artisanpack.visual-editor.breakpoints', [] )
			: [] );

		$merged = array_replace( self::DEFAULTS, $config, $themeOverrides );

		$cleaned = array_filter( $merged, static fn ( $value ) => null !== $value && '' !== $value );

		return new self( $cleaned );
	}

	/**
	 * Returns every registered breakpoint as `[key => min-width-px]`,
	 * ascending by pixel value. The implicit `base` slot is NOT
	 * included — callers that need it can prepend `'base' => 0` to the
	 * result.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, int>
	 */
	public function all(): array
	{
		return $this->breakpoints;
	}

	/**
	 * Returns the min-width (in px) for a single breakpoint, or `null`
	 * if the key isn't registered. The implicit `base` slot returns
	 * `0`.
	 *
	 * @since 1.0.0
	 */
	public function get( string $key ): ?int
	{
		if ( self::BASE_KEY === $key ) {
			return 0;
		}

		return $this->breakpoints[ $key ] ?? null;
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
	 * Validates a raw breakpoint map. Accepts integer pixel values and
	 * CSS-length strings ending in `px`. Rejects empty keys, the
	 * reserved `base` key, non-positive widths, duplicate widths, and
	 * anything that doesn't parse to a positive integer pixel value.
	 *
	 * Throws on the first failure with a descriptive message so theme
	 * authors get actionable feedback when their `theme.json` is bad.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, int|string>  $raw
	 *
	 * @return array<string, int>  Cleaned `[key => px-int]`.
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

			$pixels = $this->parsePixels( $value, $key );

			if ( in_array( $pixels, $seen, true ) ) {
				throw new InvalidArgumentException( sprintf(
					'Breakpoint key "%s" has the same min-width (%dpx) as another breakpoint.',
					$key,
					$pixels
				) );
			}

			$cleaned[ $key ] = $pixels;
			$seen[]          = $pixels;
		}

		return $cleaned;
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
}
