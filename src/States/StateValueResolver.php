<?php

/**
 * State value resolver — inheritance-chain cascade (#488).
 *
 * Given an attribute stored as a stateful object
 * `{ idle: …, hover: …, focus-visible: null }` and an active state,
 * returns the value the editor or renderer should show.
 *
 * Cascade semantics:
 *  - `idle` is the base; it applies anywhere a more specific state
 *    has no explicit override.
 *  - A named state applies in its CSS selector context only.
 *  - `null` (or a missing key) means "inherit from the next link in
 *    the inheritance chain." `idle` is the final fallback.
 *
 * Scalars (plain ints, strings, …) round-trip through `resolve()`
 * unchanged — the editor only promotes scalars into the stateful
 * form on first per-state override, so unmodified content keeps
 * working without a migration pass.
 *
 * Orphaned overrides — values stored under a key that is no longer
 * registered — are preserved on save but skipped at resolve time.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\States;

class StateValueResolver
{
	public function __construct( protected StateRegistry $registry ) {}

	/**
	 * Resolves the value for a single attribute at the given active
	 * state.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed   $attribute    Either a scalar (legacy) or a stateful
	 *                               object array
	 *                               `{ idle: …, hover: …, focus: … }`.
	 * @param  string  $activeState  State slug to resolve for. `idle` or
	 *                               any key in the registry. Unknown
	 *                               slugs fall back to `idle`.
	 *
	 * @return mixed The resolved value, or `null` if no slot in the
	 *               inheritance chain is defined.
	 */
	public function resolve( $attribute, string $activeState = StateRegistry::BASE_KEY )
	{
		if ( ! $this->isStatefulAttribute( $attribute ) ) {
			return $attribute;
		}

		foreach ( $this->registry->inheritanceChain( $activeState ) as $key ) {
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
	 * Returns the resolved value at every registered state as a flat
	 * map keyed by state slug. Used by renderers that need to know
	 * which states carry distinct values for minimal CSS emission.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function resolveAll( $attribute ): array
	{
		$results = [];

		foreach ( $this->registry->keys() as $key ) {
			$results[ $key ] = $this->resolve( $attribute, $key );
		}

		return $results;
	}

	/**
	 * Compresses a stateful attribute down to only the states whose
	 * resolved value differs from the value cascaded through the
	 * inheritance chain. Used by renderers to avoid emitting
	 * `&:hover { background: red; } &:active { background: red; }`
	 * when `active` would inherit from `hover` anyway.
	 *
	 * Always includes `idle` if it has a non-null value.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>  Sparse map keyed by state slug.
	 */
	public function distinctOverrides( $attribute ): array
	{
		if ( ! $this->isStatefulAttribute( $attribute ) ) {
			$attribute = [ StateRegistry::BASE_KEY => $attribute ];
		}

		$result = [];

		foreach ( $this->registry->keys() as $key ) {
			$value = $this->resolve( $attribute, $key );

			if ( null === $value ) {
				continue;
			}

			if ( StateRegistry::BASE_KEY === $key ) {
				$result[ $key ] = $value;
				continue;
			}

			$definition = $this->registry->get( $key );
			$parent     = $definition['inheritsFrom'] ?? StateRegistry::BASE_KEY;
			$parentVal  = $this->resolve( $attribute, $parent );

			if ( $value !== $parentVal ) {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Returns true when an attribute is shaped like the stateful
	 * discriminated object: an associative array containing at least
	 * `idle` OR at least one registered state key.
	 *
	 * Plain numeric-indexed arrays (which Gutenberg uses for things
	 * like `gallery.ids`) return false — they are not per-state objects.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed  $attribute
	 */
	public function isStatefulAttribute( $attribute ): bool
	{
		if ( ! is_array( $attribute ) || [] === $attribute ) {
			return false;
		}

		if ( array_is_list( $attribute ) ) {
			return false;
		}

		if ( array_key_exists( StateRegistry::BASE_KEY, $attribute ) ) {
			return true;
		}

		foreach ( $this->registry->keys() as $key ) {
			if ( StateRegistry::BASE_KEY === $key ) {
				continue;
			}

			if ( array_key_exists( $key, $attribute ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns stored override keys that are NOT registered in the
	 * active state registry (orphans). The audit command (future)
	 * will consume this.
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

		$valid   = array_flip( $this->registry->keys() );
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
}
