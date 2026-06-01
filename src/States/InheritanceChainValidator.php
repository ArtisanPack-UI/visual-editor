<?php

/**
 * Inheritance-chain validator (#488).
 *
 * Standalone cycle-detector for the {@see StateRegistry}'s
 * `inheritsFrom` graph. Pulled out of the registry so the same
 * logic can run on a candidate map BEFORE we accept it (CI lint of a
 * `theme.json`, for example).
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

use InvalidArgumentException;

class InheritanceChainValidator
{
	/**
	 * Asserts that every state in the map terminates at `idle` and
	 * forms no cycles. Throws on the first offending key.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, array{inheritsFrom?: ?string}>  $states
	 */
	public function assertAcyclic( array $states ): void
	{
		foreach ( array_keys( $states ) as $key ) {
			$this->walk( $states, $key );
		}
	}

	/**
	 * Walks a single chain, tripping on cycle / unterminated chain.
	 *
	 * @param  array<string, array{inheritsFrom?: ?string}>  $states
	 */
	protected function walk( array $states, string $start ): void
	{
		if ( StateRegistry::BASE_KEY === $start ) {
			return;
		}

		$seen    = [];
		$current = $start;

		while ( null !== $current ) {
			if ( isset( $seen[ $current ] ) ) {
				throw new InvalidArgumentException( sprintf(
					'State "%s" has a circular inheritance chain: %s.',
					$start,
					implode( ' → ', array_keys( $seen ) ) . ' → ' . $current
				) );
			}

			$seen[ $current ] = true;

			if ( StateRegistry::BASE_KEY === $current ) {
				return;
			}

			if ( ! array_key_exists( $current, $states ) ) {
				throw new InvalidArgumentException( sprintf(
					'State "%s" inherits from "%s", which is not registered.',
					$start,
					$current
				) );
			}

			$current = $states[ $current ]['inheritsFrom'] ?? null;
		}

		throw new InvalidArgumentException( sprintf(
			'State "%s" chain does not terminate at "%s".',
			$start,
			StateRegistry::BASE_KEY
		) );
	}
}
