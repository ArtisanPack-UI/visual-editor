<?php

/**
 * CSS position attribute resolver (#640).
 *
 * PHP mirror of `resources/js/visual-editor/positioning/resolver.ts` —
 * walks a block's attribute tree and produces the normalized payload
 * {@see PositionEmitter::emit} consumes.
 *
 * The idle layer lives at `attributes.style.position`; per-breakpoint
 * overrides ride `attributes.responsive['style.position']` following
 * the routing pattern from #487. Legacy Gutenberg sticky (a bare
 * string at `style.position`) is widened to `{ 'value' => 'sticky' }`.
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

class PositionResolver
{
	protected const POSITION_VALUES = [ 'static', 'relative', 'absolute', 'fixed', 'sticky' ];
	protected const OFFSET_UNITS    = [ 'px', '%', 'rem', 'em', 'vh', 'vw', 'auto' ];
	protected const OFFSET_SIDES    = [ 'top', 'right', 'bottom', 'left' ];

	/**
	 * Resolve a full block attribute tree into the normalized payload
	 * the emitter consumes. Returns `null` when no position
	 * configuration exists at any cascade level.
	 *
	 * @since 1.4.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array{base: array<string, mixed>|null, breakpoints: array<string, array<string, mixed>>}|null
	 */
	public static function resolve( array $attributes ): ?array
	{
		$base        = self::resolveLayer( self::coerceSubtree( $attributes['style']['position'] ?? null ) );
		$breakpoints = self::readBreakpointOverrides( $attributes );

		if ( null === $base && [] === $breakpoints ) {
			return null;
		}

		return [
			'base'        => $base,
			'breakpoints' => $breakpoints,
		];
	}

	/**
	 * Coerce the raw slot into a structured subtree. A plain string is
	 * treated as the `value` field alone — Gutenberg's native sticky
	 * shape.
	 *
	 * @since 1.4.0
	 *
	 * @return array<string, mixed>|null
	 */
	public static function coerceSubtree( mixed $raw ): ?array
	{
		if ( null === $raw ) {
			return null;
		}

		if ( is_string( $raw ) ) {
			$value = self::normalizeValue( $raw );
			return null === $value ? null : [ 'value' => $value ];
		}

		if ( ! is_array( $raw ) ) {
			return null;
		}

		return $raw;
	}

	/**
	 * @param  array<string, mixed>|null  $subtree
	 *
	 * @return array{value: string|null, offsets: array<string, array<string, mixed>|null>, zIndex: int|null}|null
	 */
	protected static function resolveLayer( ?array $subtree ): ?array
	{
		if ( null === $subtree ) {
			return null;
		}

		$value   = self::normalizeValue( $subtree['value'] ?? null );
		$offsets = self::normalizeOffsets( $subtree['offsets'] ?? null );
		$zIndex  = self::normalizeZIndex( $subtree['zIndex'] ?? null );

		$hasAny =
			null !== $value
			|| null !== $offsets['top']
			|| null !== $offsets['right']
			|| null !== $offsets['bottom']
			|| null !== $offsets['left']
			|| null !== $zIndex;

		if ( ! $hasAny ) {
			return null;
		}

		return [
			'value'   => $value,
			'offsets' => $offsets,
			'zIndex'  => $zIndex,
		];
	}

	protected static function normalizeValue( mixed $raw ): ?string
	{
		if ( ! is_string( $raw ) ) {
			return null;
		}

		$trimmed = strtolower( trim( $raw ) );

		return in_array( $trimmed, self::POSITION_VALUES, true ) ? $trimmed : null;
	}

	/**
	 * @return array<string, array<string, mixed>|null>
	 */
	protected static function normalizeOffsets( mixed $raw ): array
	{
		$out = [
			'top'    => null,
			'right'  => null,
			'bottom' => null,
			'left'   => null,
		];

		if ( ! is_array( $raw ) ) {
			return $out;
		}

		foreach ( self::OFFSET_SIDES as $side ) {
			$out[ $side ] = self::normalizeOffset( $raw[ $side ] ?? null );
		}

		return $out;
	}

	/**
	 * @return array{value: float|int, unit: string}|null
	 */
	protected static function normalizeOffset( mixed $raw ): ?array
	{
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$unit = isset( $raw['unit'] ) && is_string( $raw['unit'] )
			? strtolower( trim( $raw['unit'] ) )
			: null;

		if ( null === $unit || ! in_array( $unit, self::OFFSET_UNITS, true ) ) {
			return null;
		}

		if ( 'auto' === $unit ) {
			return [ 'value' => 0, 'unit' => 'auto' ];
		}

		$value = $raw['value'] ?? null;

		if ( is_string( $value ) ) {
			$trimmed = trim( $value );
			if ( '' === $trimmed || ! is_numeric( $trimmed ) ) {
				return null;
			}
			$value = 0 + $trimmed;
		}

		if ( ! is_int( $value ) && ! is_float( $value ) ) {
			return null;
		}

		if ( ! is_finite( (float) $value ) ) {
			return null;
		}

		return [ 'value' => $value, 'unit' => $unit ];
	}

	protected static function normalizeZIndex( mixed $raw ): ?int
	{
		if ( is_int( $raw ) ) {
			return $raw;
		}

		if ( is_float( $raw ) && is_finite( $raw ) ) {
			return (int) $raw;
		}

		if ( is_string( $raw ) ) {
			$trimmed = trim( $raw );
			if ( '' === $trimmed ) {
				return null;
			}
			// Reject scientific notation before Number-coercing. PHP's
			// `is_numeric` accepts `'1e100'` and `0 + '1e100'` overflows
			// silently to `PHP_INT_MAX`, while the JS side filters it
			// via `Number.isFinite`. Matching the JS filter keeps preview
			// (editor) and server render byte-identical for the same
			// input.
			if ( 1 === preg_match( '/[eE]/', $trimmed ) ) {
				return null;
			}
			if ( ! is_numeric( $trimmed ) ) {
				return null;
			}
			$value = 0 + $trimmed;
			if ( ! is_finite( (float) $value ) ) {
				return null;
			}
			return (int) $value;
		}

		return null;
	}

	/**
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected static function readBreakpointOverrides( array $attributes ): array
	{
		$responsive = $attributes['responsive'] ?? null;

		if ( ! is_array( $responsive ) ) {
			return [];
		}

		$bag = $responsive['style.position'] ?? null;

		if ( ! is_array( $bag ) ) {
			return [];
		}

		$out = [];

		foreach ( $bag as $key => $raw ) {
			if ( ! is_string( $key ) || '' === $key || 'base' === $key ) {
				continue;
			}

			$layer = self::resolveLayer( self::coerceSubtree( $raw ) );

			if ( null === $layer ) {
				continue;
			}

			$out[ $key ] = $layer;
		}

		return $out;
	}

	/**
	 * Merge each defined breakpoint layer on top of every smaller layer
	 * (base included). Produces the fully-resolved per-breakpoint layer
	 * map the emitter consumes for media-query bodies.
	 *
	 * @since 1.4.0
	 *
	 * @param  array{base: array<string, mixed>|null, breakpoints: array<string, array<string, mixed>>}  $payload
	 * @param  array<int, string>  $orderedBreakpointKeys  Registry keys, ascending, no `base`.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function mergedBreakpointLayers( array $payload, array $orderedBreakpointKeys ): array
	{
		$out     = [];
		$running = $payload['base'] ?? null;

		foreach ( $orderedBreakpointKeys as $key ) {
			$overlay = $payload['breakpoints'][ $key ] ?? null;

			if ( null === $overlay ) {
				continue;
			}

			$running     = self::mergeLayers( $running, $overlay );
			$out[ $key ] = $running;
		}

		return $out;
	}

	/**
	 * Fold an overlay layer on top of a base layer — overlay wins per
	 * field, base fills any nulls. Single source of truth for the
	 * merge fallthrough logic; the JS mirror lives at
	 * `resources/js/visual-editor/positioning/resolver.ts::mergeLayers`.
	 *
	 * @since 1.4.0
	 *
	 * @param  array<string, mixed>|null  $base
	 * @param  array<string, mixed>|null  $overlay
	 *
	 * @return array<string, mixed>|null
	 */
	public static function mergeLayers( ?array $base, ?array $overlay ): ?array
	{
		if ( null === $overlay ) {
			return $base;
		}

		if ( null === $base ) {
			return $overlay;
		}

		return [
			'value'   => $overlay['value'] ?? $base['value'],
			'offsets' => [
				'top'    => $overlay['offsets']['top']    ?? $base['offsets']['top'],
				'right'  => $overlay['offsets']['right']  ?? $base['offsets']['right'],
				'bottom' => $overlay['offsets']['bottom'] ?? $base['offsets']['bottom'],
				'left'   => $overlay['offsets']['left']   ?? $base['offsets']['left'],
			],
			'zIndex'  => $overlay['zIndex'] ?? $base['zIndex'],
		];
	}
}
