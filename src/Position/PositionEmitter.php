<?php

/**
 * CSS position emitter (#640).
 *
 * PHP mirror of `resources/js/visual-editor/positioning/emitter.ts` —
 * turns a resolved payload into the scoped CSS the Blade renderer
 * flushes at the top of the page output.
 *
 * Contracts (per #643):
 *
 *  - `position: static` emits nothing — orphan offsets / z-index stay
 *    in attributes but produce no CSS while static.
 *  - `unit: 'auto'` renders as `top: auto` etc.
 *  - Breakpoints emit inside `@media (min-width:<n>px)` blocks using
 *    the request-scoped breakpoint registry.
 *  - Callers pass in the already-merged per-breakpoint layer map (see
 *    {@see PositionResolver::mergedBreakpointLayers}) so the emitter
 *    only walks each breakpoint once.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Position;

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;

class PositionEmitter
{
	public function __construct(
		protected BreakpointRegistry $breakpoints,
	) {}

	/**
	 * Emit scoped CSS. Returns an empty string when nothing meaningful
	 * renders.
	 *
	 * @since 1.4.0
	 *
	 * @param  string  $scope    The selector prefix (e.g. `.ve-pos-abc`).
	 * @param  array{base: array<string, mixed>|null, breakpoints: array<string, array<string, mixed>>}  $payload
	 */
	public function emit( string $scope, array $payload ): string
	{
		$trimmedScope = trim( $scope );

		if ( '' === $trimmedScope ) {
			return '';
		}

		$rules = [];

		$baseDecls = self::layerDeclarations( $payload['base'] ?? null );
		if ( '' !== $baseDecls ) {
			$rules[] = $trimmedScope . '{' . $baseDecls . '}';
		}

		// Legacy min-width keys layer upward from base; desktop-first
		// device keys (#820) layer downward from base. Both run in
		// emission order so the later, narrower rule wins.
		$legacyKeys  = $this->breakpoints->legacyPrefixes();
		$deviceKeys  = $this->breakpoints->devicePrefixes();
		$orderedKeys = array_merge( $legacyKeys, $deviceKeys );
		$merged      = PositionResolver::mergedBreakpointLayers( $payload, $legacyKeys )
			+ PositionResolver::mergedBreakpointLayers( $payload, $deviceKeys );

		foreach ( $orderedKeys as $key ) {
			$layer = $merged[ $key ] ?? null;

			if ( null === $layer ) {
				continue;
			}

			$decls = self::layerDeclarations( $layer );
			if ( '' === $decls ) {
				continue;
			}

			$query = $this->breakpoints->mediaQuery( $key );
			if ( null === $query ) {
				continue;
			}

			$rules[] = '@media ' . $query . '{' . $trimmedScope . '{' . $decls . '}}';
		}

		return implode( '', $rules );
	}

	/**
	 * @param  array<string, mixed>|null  $layer
	 */
	public static function layerDeclarations( ?array $layer ): string
	{
		if ( null === $layer ) {
			return '';
		}

		$value = $layer['value'] ?? null;

		if ( null === $value || 'static' === $value ) {
			return '';
		}

		// See `layerDeclarations` in the JS emitter for the `!important`
		// rationale — same story on the frontend: theme resets and the
		// occasional utility framework rule can outrank a single scope
		// class, and the user's panel pick is an explicit override.
		$parts = [ 'position:' . $value . ' !important' ];

		$offsets = $layer['offsets'] ?? [];
		foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
			$offset = $offsets[ $side ] ?? null;
			if ( null === $offset || ! is_array( $offset ) ) {
				continue;
			}
			$parts[] = $side . ':' . self::formatOffset( $offset ) . ' !important';
		}

		if ( isset( $layer['zIndex'] ) && null !== $layer['zIndex'] ) {
			$parts[] = 'z-index:' . (int) $layer['zIndex'] . ' !important';
		}

		return implode( ';', $parts );
	}

	/**
	 * @param  array{value: float|int, unit: string}  $offset
	 */
	protected static function formatOffset( array $offset ): string
	{
		if ( 'auto' === $offset['unit'] ) {
			return 'auto';
		}

		$value = $offset['value'];
		// Cast integers back to no-decimal form; keep floats as PHP
		// formats them (which drops trailing zeros already).
		return ( is_int( $value ) ? (string) $value : (string) $value ) . $offset['unit'];
	}
}
