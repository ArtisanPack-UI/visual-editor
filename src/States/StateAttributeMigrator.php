<?php

/**
 * State attribute migrator — lazy scalar → stateful promotion (#488).
 *
 * Blocks store state-capable attributes as either:
 *  - a scalar (legacy / unchanged content), or
 *  - a stateful `{ idle, hover, focus, … }` object once any per-state
 *    override is applied.
 *
 * The editor calls {@see promote()} the first time an editor sets a
 * value at a non-`idle` state, lifting the scalar into the
 * discriminated form without rewriting unrelated content. Reads stay
 * backwards-compatible because the resolver accepts both shapes.
 *
 * Demoting is the symmetric operation — if every override is cleared
 * back to inheriting `idle`, {@see demote()} collapses the object
 * back to the scalar so saved JSON stays compact.
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

class StateAttributeMigrator
{
	/**
	 * Promotes a scalar (or untouched mixed value) into the stateful
	 * object form, copying the existing value to `idle` and writing
	 * `$value` at `$state`. The remaining states are left absent
	 * (i.e. inherit through the chain).
	 *
	 * If `$attribute` is already a stateful object, the existing keys
	 * are preserved and the new override slot is merged in.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed   $attribute
	 * @param  string  $state      State slug receiving the override
	 *                             (e.g. `hover`). `idle` writes the base
	 *                             value directly without promotion.
	 * @param  mixed   $value
	 *
	 * @return array<string, mixed>|mixed
	 */
	public function promote( $attribute, string $state, $value )
	{
		if ( StateRegistry::BASE_KEY === $state && ! $this->isStatefulObject( $attribute ) ) {
			return $value;
		}

		$promoted = $this->isStatefulObject( $attribute )
			? $attribute
			: [ StateRegistry::BASE_KEY => $attribute ];

		$promoted[ $state ] = $value;

		return $promoted;
	}

	/**
	 * Collapses a stateful object back to its scalar `idle` value if
	 * no per-state overrides remain set (all are `null` or missing).
	 * Returns the attribute untouched otherwise.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed  $attribute
	 *
	 * @return mixed
	 */
	public function demote( $attribute )
	{
		if ( ! $this->isStatefulObject( $attribute ) ) {
			return $attribute;
		}

		foreach ( $attribute as $key => $value ) {
			if ( StateRegistry::BASE_KEY === $key ) {
				continue;
			}

			if ( null !== $value ) {
				return $attribute;
			}
		}

		return $attribute[ StateRegistry::BASE_KEY ] ?? null;
	}

	/**
	 * Clears a specific state's override, returning the attribute
	 * back to inheriting at that slot. If clearing the last remaining
	 * override, the attribute is demoted back to its scalar form.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed   $attribute
	 * @param  string  $state
	 *
	 * @return mixed
	 */
	public function clear( $attribute, string $state )
	{
		if ( ! $this->isStatefulObject( $attribute ) ) {
			return $attribute;
		}

		if ( StateRegistry::BASE_KEY === $state ) {
			$attribute[ StateRegistry::BASE_KEY ] = null;
		} else {
			unset( $attribute[ $state ] );
		}

		return $this->demote( $attribute );
	}

	/**
	 * @param  mixed  $attribute
	 */
	protected function isStatefulObject( $attribute ): bool
	{
		return is_array( $attribute )
			&& [] !== $attribute
			&& ! array_is_list( $attribute )
			&& array_key_exists( StateRegistry::BASE_KEY, $attribute );
	}
}
