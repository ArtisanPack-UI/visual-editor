<?php

/**
 * Gradient border CSS emitter (#490).
 *
 * Turns a block's gradient-border attribute payload into the scoped CSS
 * the renderer ships with the published page. One emission strategy
 * handles all three gradient kinds (linear / radial / conic) and all
 * border-radius values cleanly: a `::before` pseudo-element rendered
 * over the block, with a `mask-composite: exclude` cut-out that leaves
 * only the border ring visible.
 *
 *     .scope { position: relative; }
 *     .scope::before {
 *         content: '';
 *         position: absolute;
 *         inset: 0;
 *         padding: <border-width>;
 *         border-radius: inherit;
 *         background: <gradient>;
 *         -webkit-mask: linear-gradient(#000 0 0) content-box,
 *                       linear-gradient(#000 0 0);
 *         -webkit-mask-composite: xor;
 *         mask: linear-gradient(#000 0 0) content-box,
 *               linear-gradient(#000 0 0);
 *         mask-composite: exclude;
 *         pointer-events: none;
 *     }
 *
 * The single strategy was a deliberate call. `border-image` is simpler
 * for non-rounded linear gradients but breaks at every `border-radius`
 * — the gradient renders square and corners fringe. The mask trick is
 * a touch heavier (one extra paint layer) but produces pixel-correct
 * output across the matrix of gradient kind × radius × side widths.
 *
 * State / breakpoint composition: the emitter consumes a normalized
 * payload already cascaded by {@see GradientBorderResolver}. Per-state
 * and per-breakpoint rules are emitted as additional `::before`
 * declarations under `:hover::before` / `@media (min-width:…) {…}`.
 * Hover rules are wrapped in `@media (hover: hover)` so touch devices
 * don't sticky-state on tap.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\GradientBorder;

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\States\StateRegistry;

class GradientBorderEmitter
{
	/**
	 * Default transition emitted on the wrapper when any non-idle state
	 * sets a gradient override. Gradients themselves don't smoothly
	 * interpolate in current browsers, but the surrounding properties
	 * (opacity, border-width changes, etc.) do — and the rule sets the
	 * editor up to layer in opacity-cross-fade strategies in a v2
	 * without changing the public CSS contract.
	 */
	public const DEFAULT_TRANSITION = 'background 200ms ease, opacity 200ms ease';

	public function __construct(
		protected StateRegistry $states,
		protected BreakpointRegistry $breakpoints,
	) {}

	/**
	 * Emit the scoped CSS for a single block's gradient border payload.
	 *
	 * `$payload` is the normalized record from
	 * {@see GradientBorderResolver::resolve} — already preset-expanded,
	 * with `null` sentinels removed and inheritance cascades applied.
	 *
	 *     [
	 *         'idle'   => 'linear-gradient(135deg, red, blue)',
	 *         'states' => [
	 *             'hover' => 'linear-gradient(135deg, blue, red)',
	 *         ],
	 *         'breakpoints' => [
	 *             'md' => 'linear-gradient(180deg, red, blue)',
	 *         ],
	 *         'width'  => '2px',
	 *         'radius' => '8px',
	 *     ]
	 *
	 * Returns an empty string when there's no idle value and no
	 * non-idle overrides — callers should treat empty as a signal to
	 * skip the `<style>` push entirely.
	 *
	 * @since 1.1.0
	 *
	 * @param  string  $scope    CSS scope selector, including the leading
	 *                           `.` (e.g. `.ve-gb-abc123`).
	 * @param  array{
	 *     idle?: string|null,
	 *     states?: array<string, string|null>,
	 *     breakpoints?: array<string, string|null>,
	 *     width?: string|null,
	 *     radius?: string|null,
	 * }       $payload
	 */
	public function emit( string $scope, array $payload ): string
	{
		$scope = trim( $scope );

		if ( '' === $scope ) {
			return '';
		}

		$idle        = self::stringOrNull( $payload['idle'] ?? null );
		$states      = self::stringMap( $payload['states'] ?? [] );
		$breakpoints = self::stringMap( $payload['breakpoints'] ?? [] );
		$width       = self::stringOrNull( $payload['width'] ?? null ) ?? '1px';

		if ( null === $idle && [] === $states && [] === $breakpoints ) {
			return '';
		}

		$rules = [];

		// Position the wrapper so the absolutely-positioned ::before
		// pseudo aligns to the block box. Authors who want an explicit
		// non-static position can override via host CSS — this is the
		// minimum required for the mask layer to render at all.
		//
		// `border-color: transparent` suppresses any stale
		// `style.border.color` value left over from before the block
		// was switched to a gradient border (or written by a host that
		// still has the native color picker enabled). Without it the
		// native `BlockSupports::applyBorder` would paint a solid edge
		// underneath our gradient `::before` and the user would see
		// two stacked borders. `!important` matches the inline
		// `style="border-color:…"` specificity the supports compiler
		// emits.
		$rules[] = sprintf( '%s{position:relative;border-color:transparent !important}', $scope );

		// Idle ::before rule. Always emitted when *any* gradient value
		// exists at any state/breakpoint so the cascade has a base to
		// override — falling back to `transparent` keeps the cut-out
		// layer in the DOM without painting anything visible.
		$idleGradient = $idle ?? 'transparent';
		$rules[]      = sprintf(
			'%s::before{%s}',
			$scope,
			self::baseBeforeDeclarations( $idleGradient, $width, $payload['radius'] ?? null )
		);

		$hasNonIdle = [] !== $states || [] !== $breakpoints;

		if ( $hasNonIdle ) {
			$rules[] = sprintf(
				'%s::before{transition:%s}',
				$scope,
				self::DEFAULT_TRANSITION
			);
		}

		// Per-state ::before rules. The state registry's
		// inheritance-aware traversal lives in `StateValueResolver`;
		// here we only emit the keys the resolver already trimmed.
		$hoverParts    = [];
		$nonHoverParts = [];

		foreach ( $states as $stateKey => $gradient ) {
			$definition = $this->states->get( $stateKey );
			if ( null === $definition ) {
				continue;
			}

			$selector = self::selectorFor( $scope, $definition['selector'] ?? '' );
			if ( '' === $selector ) {
				continue;
			}

			$rule = sprintf( '%s::before{background:%s}', $selector, $gradient );

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

		// Per-breakpoint ::before rules. Mobile-first cascade — the
		// registry's `get()` returns `null` for keys it doesn't know,
		// which silently drops the override (same behavior as
		// `compileResponsive` for spacing/border properties).
		foreach ( $breakpoints as $breakpointKey => $gradient ) {
			$minWidth = $this->breakpoints->get( $breakpointKey );

			if ( null === $minWidth ) {
				continue;
			}

			$rules[] = sprintf(
				'@media (min-width:%dpx){%s::before{background:%s}}',
				$minWidth,
				$scope,
				$gradient
			);
		}

		return implode( '', $rules );
	}

	/**
	 * Compose the `::before` declarations shared by every idle rule —
	 * the cut-out mask, the gradient layer, sizing, and pointer
	 * fall-through.
	 *
	 * @since 1.1.0
	 */
	protected static function baseBeforeDeclarations( string $gradient, string $width, mixed $radius ): string
	{
		$radiusDecl = self::radiusDeclaration( $radius );
		$safeWidth  = self::sanitizeCss( $width );

		// `inset: calc(-1 * <width>)` extends the pseudo OUTWARD by the
		// border-width so its outer edge aligns with the wrapper's
		// border-box (not the padding-box, which is the default for
		// `inset: 0` on an absolutely-positioned child). Combined with
		// `padding: <width>`, the mask cut-out leaves a `<width>`-wide
		// ring sitting exactly where the wrapper's native border would
		// render. Without the negative inset the ring sits one
		// border-width INSIDE the visible block edge, which reads as
		// "the gradient is floating inside the div" — see #490 follow-up.
		return sprintf(
			'content:"";position:absolute;inset:calc(-1 * %1$s);padding:%1$s;%2$sbackground:%3$s;'
			. '-webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);'
			. '-webkit-mask-composite:xor;'
			. 'mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);'
			. 'mask-composite:exclude;pointer-events:none',
			$safeWidth,
			$radiusDecl,
			self::sanitizeGradient( $gradient )
		);
	}

	/**
	 * Resolve the literal selector for a state definition, replacing the
	 * `&` placeholder with the block scope. Mirrors
	 * {@see StateCssEmitter::selectorFor}.
	 *
	 * @since 1.1.0
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
	 * Emit the `border-radius` declaration when one is configured.
	 * Returns an empty string when no radius applies — the wrapper's
	 * own `border-radius` (or the lack of one) is inherited by the
	 * pseudo via `border-radius: inherit` only when no explicit value
	 * is supplied here.
	 *
	 * Accepts either a scalar (uniform corner) or a per-corner object
	 * matching Gutenberg's `{topLeft, topRight, bottomLeft, bottomRight}`
	 * shape.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>|string|null  $radius
	 */
	protected static function radiusDeclaration( mixed $radius ): string
	{
		if ( is_string( $radius ) && '' !== trim( $radius ) ) {
			return 'border-radius:' . self::sanitizeCss( $radius ) . ';';
		}

		if ( is_array( $radius ) ) {
			$pieces = [];

			$corners = [
				'topLeft'     => 'top-left',
				'topRight'    => 'top-right',
				'bottomLeft'  => 'bottom-left',
				'bottomRight' => 'bottom-right',
			];

			foreach ( $corners as $key => $cssKey ) {
				$value = $radius[ $key ] ?? null;

				if ( ! is_string( $value ) || '' === trim( $value ) ) {
					continue;
				}

				$pieces[] = sprintf( 'border-%s-radius:%s', $cssKey, self::sanitizeCss( $value ) );
			}

			if ( [] !== $pieces ) {
				return implode( ';', $pieces ) . ';';
			}
		}

		// `inherit` lets the pseudo follow the host wrapper's
		// `border-radius` when the editor authored one through the
		// standard border panel.
		return 'border-radius:inherit;';
	}

	/**
	 * Whitelist the characters allowed in a generic CSS value (width,
	 * radius, calc()). Mirrors {@see BlockSupports::sanitizeCssValue}
	 * so a corrupted block tree can't break out of the `<style>`
	 * context.
	 *
	 * @since 1.1.0
	 */
	protected static function sanitizeCss( string $value ): string
	{
		return (string) preg_replace( '/[^a-zA-Z0-9_+\-*\/.,()%#\s]/', '', $value );
	}

	/**
	 * Slightly looser whitelist for gradient functions — same as
	 * `sanitizeCss` plus `:` (for `--var()` references that include
	 * `var(--wp--preset--gradient--slug)`) and `&` is still disallowed.
	 * Quote characters are dropped because gradient color stops never
	 * need them and they'd let an attacker break out of the
	 * `background: …` declaration.
	 *
	 * @since 1.1.0
	 */
	protected static function sanitizeGradient( string $value ): string
	{
		return (string) preg_replace( '/[^a-zA-Z0-9_+\-*\/.,()%#:\s]/', '', $value );
	}

	/**
	 * Coerce an attribute to a non-empty trimmed string, or `null`.
	 *
	 * @since 1.1.0
	 */
	protected static function stringOrNull( mixed $value ): ?string
	{
		if ( ! is_string( $value ) ) {
			return null;
		}

		$trimmed = trim( $value );

		return '' === $trimmed ? null : $trimmed;
	}

	/**
	 * Coerce a `[ stateKey => value ]` map into a `[ stateKey => string ]`
	 * map, dropping any non-string / empty entries.
	 *
	 * @param  array<mixed, mixed>  $raw
	 *
	 * @return array<string, string>
	 *
	 * @since 1.1.0
	 */
	protected static function stringMap( mixed $raw ): array
	{
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out = [];

		foreach ( $raw as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key ) {
				continue;
			}

			$resolved = self::stringOrNull( $value );

			if ( null === $resolved ) {
				continue;
			}

			$out[ $key ] = $resolved;
		}

		return $out;
	}

}
