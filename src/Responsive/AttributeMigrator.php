<?php

/**
 * Attribute migrator — lazy scalar → discriminated promotion (#487).
 *
 * Blocks store responsive-capable attributes as either:
 *  - a scalar (legacy / unchanged content), or
 *  - a discriminated `{base, sm, md, …}` object once any per-breakpoint
 *    override is applied.
 *
 * The editor calls {@see promote()} the first time an editor sets a
 * value at a non-`base` breakpoint, lifting the scalar into the
 * discriminated form without rewriting any unrelated content. Reads
 * stay backwards-compatible because the resolver accepts both shapes.
 *
 * Demoting is the symmetric operation — if every override is cleared
 * back to inheriting the base, {@see demote()} collapses the object
 * back to its scalar so saved JSON stays compact.
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

class AttributeMigrator
{
	/**
	 * Promotes a scalar (or untouched mixed value) into the
	 * discriminated object form, copying the existing value to `base`
	 * and writing `$value` at `$breakpoint`. The remaining breakpoints
	 * are left absent (i.e. inherit).
	 *
	 * If `$attribute` is already a discriminated object, the existing
	 * keys are preserved and the new override slot is merged in.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed   $attribute
	 * @param  string  $breakpoint  The breakpoint slug receiving the
	 *                              override (e.g. `md`). `base` writes
	 *                              the base value directly without
	 *                              promotion.
	 * @param  mixed   $value
	 *
	 * @return array<string, mixed>|mixed
	 */
	public function promote( $attribute, string $breakpoint, $value )
	{
		if ( BreakpointRegistry::BASE_KEY === $breakpoint && ! $this->isResponsiveObject( $attribute ) ) {
			return $value;
		}

		$promoted = $this->isResponsiveObject( $attribute )
			? $attribute
			: [ BreakpointRegistry::BASE_KEY => $attribute ];

		$promoted[ $breakpoint ] = $value;

		return $promoted;
	}

	/**
	 * Collapses a discriminated object back to its scalar `base` value
	 * if no per-breakpoint overrides remain set (all are `null` or
	 * missing). Returns the attribute untouched otherwise.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed  $attribute
	 *
	 * @return mixed
	 */
	public function demote( $attribute )
	{
		if ( ! $this->isResponsiveObject( $attribute ) ) {
			return $attribute;
		}

		foreach ( $attribute as $key => $value ) {
			if ( BreakpointRegistry::BASE_KEY === $key ) {
				continue;
			}

			if ( null !== $value ) {
				return $attribute;
			}
		}

		return $attribute[ BreakpointRegistry::BASE_KEY ] ?? null;
	}

	/**
	 * Clears a specific breakpoint's override, returning the attribute
	 * back to inheriting the cascade at that slot. If clearing the
	 * last remaining override, the attribute is demoted back to its
	 * scalar form.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed   $attribute
	 * @param  string  $breakpoint
	 *
	 * @return mixed
	 */
	public function clear( $attribute, string $breakpoint )
	{
		if ( ! $this->isResponsiveObject( $attribute ) ) {
			return $attribute;
		}

		if ( BreakpointRegistry::BASE_KEY === $breakpoint ) {
			$attribute[ BreakpointRegistry::BASE_KEY ] = null;
		} else {
			unset( $attribute[ $breakpoint ] );
		}

		return $this->demote( $attribute );
	}

	/**
	 * @param  mixed  $attribute
	 */
	protected function isResponsiveObject( $attribute ): bool
	{
		return is_array( $attribute )
			&& [] !== $attribute
			&& ! array_is_list( $attribute )
			&& array_key_exists( BreakpointRegistry::BASE_KEY, $attribute );
	}
}
