<?php

/**
 * Box shadow CSS emitter (#607).
 *
 * Turns a block's box-shadow attribute payload into the scoped CSS
 * the renderer ships with the published page. Three emission modes,
 * dispatched per resolved layer:
 *
 *   1. **Preset** (`layer.preset` set) — `box-shadow: var(--wp--preset--shadow--{slug})`.
 *      Inset is honored via `var(...) inset` (themes ship the value
 *      WITHOUT the `inset` keyword).
 *
 *   2. **Solid** (`layer.gradient` null) — stock `box-shadow:
 *      [inset] X Y blur spread color`. Color defaults to `currentColor`.
 *
 *   3. **Gradient** (`layer.gradient` set) — `::before` (outer) or
 *      `::after` (inset) pseudo-element with `background: <gradient>`,
 *      `filter: blur(<blur>)`, `transform: translate(<X>, <Y>)`, and
 *      for inset, a `mask-composite: exclude` ring mask to clip the
 *      fill to the inside of the wrapper.
 *
 * Architecture deliberately parallels {@see GradientBorderEmitter}.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\BoxShadow;

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\States\StateRegistry;

class BoxShadowEmitter
{
	/**
	 * Default transition emitted on the wrapper when any non-idle
	 * state or breakpoint defines a shadow override.
	 */
	public const DEFAULT_TRANSITION = 'box-shadow 200ms ease, background 200ms ease, opacity 200ms ease, transform 200ms ease';

	public function __construct(
		protected StateRegistry $states,
		protected BreakpointRegistry $breakpoints,
	) {}

	/**
	 * Emit the scoped CSS for a single block's box-shadow payload.
	 *
	 * Returns an empty string when there's no idle layer and no
	 * non-idle overrides.
	 *
	 * @since 1.2.0
	 *
	 * @param  string  $scope    CSS scope selector, including leading
	 *                           `.` (e.g. `.ve-bs-abc123`).
	 * @param  array{
	 *     idle?: array<string, mixed>|null,
	 *     states?: array<string, array<string, mixed>>,
	 *     breakpoints?: array<string, array<string, mixed>>,
	 * }       $payload
	 */
	public function emit( string $scope, array $payload ): string
	{
		$scope = trim( $scope );

		if ( '' === $scope ) {
			return '';
		}

		$idle        = $this->normalizeLayer( $payload['idle'] ?? null );
		$states      = $this->normalizeLayerMap( $payload['states'] ?? [] );
		$breakpoints = $this->normalizeLayerMap( $payload['breakpoints'] ?? [] );

		$hasNonIdle = [] !== $states || [] !== $breakpoints;

		if ( null === $idle && ! $hasNonIdle ) {
			return '';
		}

		$usesGradient = ( null !== $idle && self::isGradient( $idle ) )
			|| self::anyGradient( $states )
			|| self::anyGradient( $breakpoints );

		$rules = [];

		if ( $usesGradient ) {
			// `isolation: isolate` creates a new stacking context on the
			// wrapper. Without it, the gradient `::before` (which uses
			// `z-index: -1` to slip behind the wrapper's own children)
			// can get hidden behind a parent's background, because
			// z-index: -1 in the parent's stacking context puts the
			// pseudo behind that parent too. Mirrors the TS emitter.
			$rules[] = sprintf( '%s{position:relative;isolation:isolate}', $scope );
		}

		if ( null !== $idle ) {
			$rule = $this->ruleForLayer( $scope, $idle );

			if ( '' !== $rule ) {
				$rules[] = $rule;
			}
		}

		if ( $hasNonIdle ) {
			$rules[] = sprintf( '%s{transition:%s}', $scope, self::DEFAULT_TRANSITION );
		}

		$hoverParts    = [];
		$nonHoverParts = [];

		foreach ( $states as $stateKey => $layer ) {
			$definition = $this->states->get( $stateKey );
			if ( null === $definition ) {
				continue;
			}

			$selector = self::selectorFor( $scope, $definition['selector'] ?? '' );
			if ( '' === $selector ) {
				continue;
			}

			$rule = $this->stateRuleFor( $selector, $layer );

			if ( '' === $rule ) {
				continue;
			}

			if ( ! empty( $definition['hoverMediaWrap'] ) ) {
				$hoverParts[] = $rule;
				continue;
			}

			$nonHoverParts[] = $rule;
		}

		if ( [] !== $hoverParts ) {
			$rules[] = sprintf( '@media (hover: hover){%s}', implode( '', $hoverParts ) );
		}

		foreach ( $nonHoverParts as $rule ) {
			$rules[] = $rule;
		}

		foreach ( $breakpoints as $bp => $layer ) {
			$minWidth = $this->breakpoints->get( $bp );

			if ( null === $minWidth ) {
				continue;
			}

			$rule = $this->ruleForLayer( $scope, $layer );

			if ( '' === $rule ) {
				continue;
			}

			$rules[] = sprintf( '@media (min-width:%dpx){%s}', $minWidth, $rule );
		}

		return implode( '', $rules );
	}

	/**
	 * Build the CSS rule for a layer in idle/breakpoint context where
	 * the scope itself is the selector.
	 *
	 * @param  array<string, mixed>  $layer
	 */
	protected function ruleForLayer( string $scope, array $layer ): string
	{
		if ( self::isGradient( $layer ) ) {
			return sprintf(
				'%s%s{%s}',
				$scope,
				self::pseudoForLayer( $layer ),
				self::gradientPseudoDeclarations( $layer )
			);
		}

		$value = self::layerBoxShadowValue( $layer );
		if ( '' === $value ) {
			return '';
		}

		return sprintf( '%s{box-shadow:%s}', $scope, $value );
	}

	/**
	 * Build the CSS rule for a layer in per-state context where the
	 * selector already has the state suffix applied.
	 *
	 * @param  array<string, mixed>  $layer
	 */
	protected function stateRuleFor( string $selector, array $layer ): string
	{
		if ( self::isGradient( $layer ) ) {
			return sprintf(
				'%s{%s}',
				self::appendPseudoToList( $selector, self::pseudoForLayer( $layer ) ),
				self::gradientPseudoDeclarations( $layer )
			);
		}

		$value = self::layerBoxShadowValue( $layer );
		if ( '' === $value ) {
			return '';
		}

		return sprintf( '%s{box-shadow:%s}', $selector, $value );
	}

	/**
	 * Compose the box-shadow value for a non-gradient (preset or
	 * solid) layer. Returns empty string when no value is producible.
	 *
	 * @param  array<string, mixed>  $layer
	 */
	protected static function layerBoxShadowValue( array $layer ): string
	{
		if ( null !== ( $layer['preset'] ?? null ) ) {
			$slug = self::sanitizeSlug( (string) $layer['preset'] );

			if ( '' === $slug ) {
				return '';
			}

			$value = sprintf( 'var(--wp--preset--shadow--%s)', $slug );

			return true === ( $layer['inset'] ?? false ) ? $value . ' inset' : $value;
		}

		$parts = [
			self::sanitizeCss( (string) ( $layer['offsetX'] ?? '0px' ) ),
			self::sanitizeCss( (string) ( $layer['offsetY'] ?? '0px' ) ),
			self::sanitizeCss( (string) ( $layer['blur'] ?? '0px' ) ),
			self::sanitizeCss( (string) ( $layer['spread'] ?? '0px' ) ),
			self::sanitizeCss( (string) ( $layer['color'] ?? 'currentColor' ) ),
		];

		$value = implode( ' ', $parts );

		return true === ( $layer['inset'] ?? false ) ? 'inset ' . $value : $value;
	}

	/**
	 * Compose the `::before`/`::after` declarations for a gradient
	 * shadow layer. Outer uses negative inset + filter blur + z-index
	 * -1; inset uses a mask-composite ring with a padding ring.
	 *
	 * @param  array<string, mixed>  $layer
	 */
	protected static function gradientPseudoDeclarations( array $layer ): string
	{
		$offsetX = self::sanitizeCss( (string) ( $layer['offsetX'] ?? '0px' ) );
		$offsetY = self::sanitizeCss( (string) ( $layer['offsetY'] ?? '0px' ) );
		$blur    = self::sanitizeCss( (string) ( $layer['blur'] ?? '0px' ) );
		$spread  = self::sanitizeCss( (string) ( $layer['spread'] ?? '0px' ) );
		$fill    = self::sanitizeGradient( (string) ( $layer['gradient'] ?? 'transparent' ) );

		if ( true === ( $layer['inset'] ?? false ) ) {
			// Explicit top/left/width/height (rather than `inset: 0`)
			// to make the absolutely-positioned pseudo's size
			// unambiguous across containing-block edge cases. See TS
			// emitter docblock for the failure mode this guards.
			return sprintf(
				'content:"";position:absolute;top:0;left:0;width:100%%;height:100%%;'
				. 'border-radius:inherit;'
				. 'padding:%1$s;'
				. 'background:%2$s;'
				. 'filter:blur(%3$s);'
				. 'transform:translate(calc(-1 * %4$s),calc(-1 * %5$s));'
				. '-webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);'
				. '-webkit-mask-composite:xor;'
				. 'mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);'
				. 'mask-composite:exclude;'
				. 'pointer-events:none',
				$spread,
				$fill,
				$blur,
				$offsetX,
				$offsetY
			);
		}

		// Outer gradient — explicit top/left/width/height instead of
		// `inset: calc(-1 * spread)` for unambiguous sizing.
		return sprintf(
			'content:"";position:absolute;'
			. 'top:calc(-1 * %1$s);left:calc(-1 * %1$s);'
			. 'width:calc(100%% + 2 * %1$s);height:calc(100%% + 2 * %1$s);'
			. 'border-radius:inherit;'
			. 'background:%2$s;'
			. 'filter:blur(%3$s);'
			. 'transform:translate(%4$s,%5$s);'
			. 'z-index:-1;'
			. 'pointer-events:none',
			$spread,
			$fill,
			$blur,
			$offsetX,
			$offsetY
		);
	}

	/**
	 * Normalize an incoming layer record — defends against partial /
	 * malformed payloads from non-trusted resolvers.
	 *
	 * @return array<string, mixed>|null
	 */
	protected function normalizeLayer( mixed $layer ): ?array
	{
		if ( ! is_array( $layer ) ) {
			return null;
		}

		$preset = $layer['preset'] ?? null;
		$preset = is_string( $preset ) && '' !== trim( $preset ) ? trim( $preset ) : null;

		$gradient = $layer['gradient'] ?? null;
		$gradient = is_string( $gradient ) && '' !== trim( $gradient ) ? trim( $gradient ) : null;

		$hasStructured =
			self::nonEmptyString( $layer['offsetX'] ?? null )
			|| self::nonEmptyString( $layer['offsetY'] ?? null )
			|| self::nonEmptyString( $layer['blur'] ?? null )
			|| self::nonEmptyString( $layer['spread'] ?? null )
			|| self::nonEmptyString( $layer['color'] ?? null )
			|| null !== $gradient
			|| true === ( $layer['inset'] ?? false );

		if ( null === $preset && ! $hasStructured ) {
			return null;
		}

		return [
			'offsetX'  => self::coerceCss( $layer['offsetX'] ?? null, '0px' ),
			'offsetY'  => self::coerceCss( $layer['offsetY'] ?? null, '0px' ),
			'blur'     => self::coerceCss( $layer['blur'] ?? null, '0px' ),
			'spread'   => self::coerceCss( $layer['spread'] ?? null, '0px' ),
			'color'    => self::nonEmptyString( $layer['color'] ?? null ) ? trim( (string) $layer['color'] ) : null,
			'gradient' => $gradient,
			'inset'    => true === ( $layer['inset'] ?? false ),
			'preset'   => $preset,
		];
	}

	/**
	 * @param  mixed  $raw
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function normalizeLayerMap( mixed $raw ): array
	{
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out = [];

		foreach ( $raw as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key ) {
				continue;
			}

			$layer = $this->normalizeLayer( $value );

			if ( null === $layer ) {
				continue;
			}

			$out[ $key ] = $layer;
		}

		return $out;
	}

	/**
	 * @param  array<string, mixed>  $layer
	 */
	protected static function isGradient( array $layer ): bool
	{
		return null === ( $layer['preset'] ?? null ) && null !== ( $layer['gradient'] ?? null );
	}

	/**
	 * @param  array<string, array<string, mixed>>  $layers
	 */
	protected static function anyGradient( array $layers ): bool
	{
		foreach ( $layers as $layer ) {
			if ( self::isGradient( $layer ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param  array<string, mixed>  $layer
	 */
	protected static function pseudoForLayer( array $layer ): string
	{
		return true === ( $layer['inset'] ?? false ) ? '::after' : '::before';
	}

	/**
	 * Resolve the literal selector for a state definition, replacing
	 * `&` with the block scope.
	 */
	protected static function selectorFor( string $scope, string $selector ): string
	{
		if ( '' === $selector ) {
			return '';
		}

		$pieces = array_map( 'trim', explode( ',', $selector ) );
		$mapped = [];

		foreach ( $pieces as $piece ) {
			if ( '' === $piece ) {
				continue;
			}

			$mapped[] = str_contains( $piece, '&' )
				? str_replace( '&', $scope, $piece )
				: $scope . $piece;
		}

		return implode( ', ', $mapped );
	}

	/**
	 * Append a pseudo-element suffix to EVERY selector in a comma-
	 * separated list. Mirrors {@see GradientBorderEmitter::appendPseudoToList}.
	 */
	protected static function appendPseudoToList( string $selector, string $pseudo ): string
	{
		$pieces = array_map( 'trim', explode( ',', $selector ) );
		$mapped = [];

		foreach ( $pieces as $piece ) {
			if ( '' === $piece ) {
				continue;
			}

			$mapped[] = $piece . $pseudo;
		}

		return implode( ', ', $mapped );
	}

	protected static function sanitizeCss( string $value ): string
	{
		return (string) preg_replace( '/[^a-zA-Z0-9_+\-*\/.,()%#\s]/', '', $value );
	}

	protected static function sanitizeGradient( string $value ): string
	{
		return (string) preg_replace( '/[^a-zA-Z0-9_+\-*\/.,()%#:\s]/', '', $value );
	}

	protected static function sanitizeSlug( string $value ): string
	{
		return (string) preg_replace( '/[^a-z0-9_-]/i', '', $value );
	}

	protected static function coerceCss( mixed $value, string $fallback ): string
	{
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$trimmed = trim( $value );

		return '' === $trimmed ? $fallback : $trimmed;
	}

	protected static function nonEmptyString( mixed $value ): bool
	{
		return is_string( $value ) && '' !== trim( $value );
	}
}
