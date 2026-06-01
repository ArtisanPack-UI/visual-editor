<?php

/**
 * Responsive value resolver — mobile-first cascade (#487).
 *
 * Given an attribute stored as a discriminated `{base, sm, md, lg, …}`
 * object and an active breakpoint, returns the value the editor or
 * renderer should show.
 *
 * Cascade semantics (mirrors Tailwind's `sm:` / `md:` modifiers):
 *  - `base` is the unprefixed value; it applies everywhere unless a
 *    larger breakpoint overrides it.
 *  - A named breakpoint applies at its min-width and up, until another
 *    explicit override at a larger breakpoint replaces it.
 *  - `null` (or a missing key) means "inherit from the next-smaller
 *    defined slot." `base` is the final fallback.
 *
 * Scalars (plain ints, strings, booleans, etc.) round-trip through
 * `resolve()` unchanged — the editor only promotes scalars into the
 * discriminated form on first per-breakpoint override, so unmodified
 * content keeps working without a migration pass.
 *
 * Orphaned overrides — values stored under a key that is no longer in
 * the registry (e.g. the theme removed `xl`) — are preserved on save
 * but skipped at resolve time. The audit command surfaces them.
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

class ResponsiveValueResolver
{
	public function __construct( protected BreakpointRegistry $registry ) {}

	/**
	 * Resolves the value for a single attribute at the given active
	 * breakpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed   $attribute        Either a scalar (legacy) or a
	 *                                   discriminated object array
	 *                                   `{base: …, sm: …, md: …}`.
	 * @param  string  $activeBreakpoint Slug to resolve for. `base` or any
	 *                                   key from the registry. Unknown
	 *                                   slugs fall back to `base`.
	 *
	 * @return mixed The resolved value, or `null` if no slot at or below
	 *               the active breakpoint is defined.
	 */
	public function resolve( $attribute, string $activeBreakpoint = BreakpointRegistry::BASE_KEY )
	{
		if ( ! $this->isResponsiveAttribute( $attribute ) ) {
			return $attribute;
		}

		$cascade = $this->cascadeKeys( $activeBreakpoint );

		foreach ( $cascade as $key ) {
			if ( ! array_key_exists( $key, $attribute ) ) {
				continue;
			}

			$value = $attribute[ $key ];

			if ( null === $value ) {
				continue;
			}

			return $value;
		}

		return null;
	}

	/**
	 * Returns the resolved value at every registered breakpoint
	 * (including `base`) as a flat map. Useful for renderers that need
	 * to know which breakpoints carry distinct values to keep CSS
	 * emission minimal.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function resolveAll( $attribute ): array
	{
		$results = [];

		foreach ( $this->registry->keysWithBase() as $key ) {
			$results[ $key ] = $this->resolve( $attribute, $key );
		}

		return $results;
	}

	/**
	 * Compresses a discriminated attribute to only the breakpoints
	 * whose resolved value differs from the next-smaller cascaded
	 * value. Used by renderers to avoid emitting redundant
	 * `md:px-4 lg:px-4` when both lg and md would inherit anyway.
	 *
	 * Always includes `base` if it has a non-null value.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>  Sparse map: keys present only when
	 *                               their resolved value differs from
	 *                               the cascade.
	 */
	public function distinctOverrides( $attribute ): array
	{
		if ( ! $this->isResponsiveAttribute( $attribute ) ) {
			$attribute = [ BreakpointRegistry::BASE_KEY => $attribute ];
		}

		$result   = [];
		$previous = null;
		$first    = true;

		foreach ( $this->registry->keysWithBase() as $key ) {
			$value = $this->resolve( $attribute, $key );

			if ( null === $value ) {
				continue;
			}

			if ( $first || $value !== $previous ) {
				$result[ $key ] = $value;
				$previous       = $value;
				$first          = false;
			}
		}

		return $result;
	}

	/**
	 * Returns true when an attribute is shaped like the responsive
	 * discriminated object: an associative array containing at least
	 * `base` OR at least one registered breakpoint key.
	 *
	 * Plain numeric-indexed arrays (which Gutenberg uses for things
	 * like `gallery.ids`) return false — they are not per-breakpoint
	 * objects.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed  $attribute
	 */
	public function isResponsiveAttribute( $attribute ): bool
	{
		if ( ! is_array( $attribute ) || [] === $attribute ) {
			return false;
		}

		// Sequential (list-style) array — not a responsive object.
		if ( array_is_list( $attribute ) ) {
			return false;
		}

		if ( array_key_exists( BreakpointRegistry::BASE_KEY, $attribute ) ) {
			return true;
		}

		foreach ( $this->registry->prefixes() as $key ) {
			if ( array_key_exists( $key, $attribute ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns stored override keys that are NOT present in the active
	 * registry (orphans). The audit command consumes this.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function orphanedKeys( $attribute ): array
	{
		if ( ! is_array( $attribute ) || [] === $attribute || array_is_list( $attribute ) ) {
			return [];
		}

		$valid   = array_flip( $this->registry->keysWithBase() );
		$orphans = [];

		foreach ( array_keys( $attribute ) as $key ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			if ( isset( $valid[ $key ] ) ) {
				continue;
			}

			$orphans[] = $key;
		}

		return $orphans;
	}

	/**
	 * Returns the breakpoint keys to walk for a given active key, in
	 * cascade order (largest defined ≤ active, then walking down to
	 * `base`).
	 *
	 * @return array<int, string>
	 */
	protected function cascadeKeys( string $activeBreakpoint ): array
	{
		$ordered = $this->registry->keysWithBase();

		if ( BreakpointRegistry::BASE_KEY === $activeBreakpoint || ! $this->registry->has( $activeBreakpoint ) ) {
			return [ BreakpointRegistry::BASE_KEY ];
		}

		$activeIndex = array_search( $activeBreakpoint, $ordered, true );

		if ( false === $activeIndex ) {
			return [ BreakpointRegistry::BASE_KEY ];
		}

		// Walk from active down to base.
		$slice = array_slice( $ordered, 0, $activeIndex + 1 );

		return array_reverse( $slice );
	}
}
