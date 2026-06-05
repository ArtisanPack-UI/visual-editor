<?php

/**
 * State CSS emitter (#488).
 *
 * Server-side helper that turns a per-block bag of stateful attribute
 * values into the scoped CSS rules the renderer ships with the
 * published page. Produces standard `:hover` / `:focus-visible` etc.
 * selectors against a unique class scope (`.ap-block-<uid>`) so the
 * page has no JS runtime requirement for state styling.
 *
 * Hover rules are automatically wrapped in `@media (hover: hover)` so
 * touch devices don't sticky-state on tap.
 *
 * Inheritance pruning is delegated to {@see StateValueResolver::distinctOverrides()}
 * — only states whose resolved value differs from their inheritance
 * parent emit a rule.
 *
 * Where a value maps to a Tailwind token (e.g. `bg-accent-700` would
 * be emitted as the Tailwind class `hover:bg-accent-700` by the
 * editor), the emitter still emits the equivalent CSS rule so server-
 * side renderers without the Tailwind JIT keep working. Hosts that
 * use the editor's class-string emission can ignore this entirely.
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

class StateCssEmitter
{
	/**
	 * Default `transition: ...` value emitted on the idle slot when
	 * any non-idle state is set and no explicit transition has been
	 * authored. Keeps interactive feedback feeling smooth out of the
	 * box.
	 */
	public const DEFAULT_TRANSITION = 'all 150ms ease';

	public function __construct(
		protected StateRegistry $registry,
		protected StateValueResolver $resolver,
	) {}

	/**
	 * Emits a CSS string for a single block scope.
	 *
	 * `$attributes` is a flat map of CSS property → stateful value:
	 *
	 *     [
	 *         'background-color' => [ 'idle' => 'red', 'hover' => 'blue' ],
	 *         'transform'        => [ 'idle' => null,  'hover' => 'scale(1.02)' ],
	 *     ]
	 *
	 * Plain scalar values are also accepted and emitted as the `idle`
	 * value only.
	 *
	 * @since 1.0.0
	 *
	 * @param  string                                              $scope       CSS scope, e.g. `.ap-block-abc123`.
	 *                                                                          Must already include the leading `.`.
	 * @param  array<string, mixed>                                $attributes  property => attribute value.
	 *
	 * @return string The emitted CSS — empty string when nothing is
	 *                worth emitting.
	 */
	public function emit( string $scope, array $attributes ): string
	{
		if ( '' === trim( $scope ) ) {
			return '';
		}

		$buckets = $this->bucketByState( $attributes );

		if ( [] === $buckets ) {
			return '';
		}

		$idleRule  = $this->renderIdleRule( $scope, $buckets[ StateRegistry::BASE_KEY ] ?? [], $buckets );
		$stateCss  = '';
		$hoverCss  = '';

		foreach ( $this->registry->keys() as $state ) {
			if ( StateRegistry::BASE_KEY === $state ) {
				continue;
			}

			$declarations = $buckets[ $state ] ?? [];
			if ( [] === $declarations ) {
				continue;
			}

			$definition = $this->registry->get( $state );
			$selector   = $this->selectorFor( $scope, $definition );

			if ( '' === $selector ) {
				continue;
			}

			$rule = sprintf( "%s { %s }", $selector, $this->joinDeclarations( $declarations ) );

			if ( ! empty( $definition['hoverMediaWrap'] ) ) {
				$hoverCss .= ( '' === $hoverCss ? '' : ' ' ) . $rule;
				continue;
			}

			$stateCss .= ( '' === $stateCss ? '' : ' ' ) . $rule;
		}

		$out = $idleRule;

		if ( '' !== $hoverCss ) {
			$out .= ( '' === $out ? '' : ' ' ) . sprintf( '@media (hover: hover) { %s }', $hoverCss );
		}

		if ( '' !== $stateCss ) {
			$out .= ( '' === $out ? '' : ' ' ) . $stateCss;
		}

		return $out;
	}

	/**
	 * Builds the idle-rule string, injecting a default transition
	 * when the editor never set one and there is at least one
	 * non-idle override.
	 *
	 * @param  array<string, string>                $idleDeclarations
	 * @param  array<string, array<string, string>>  $allBuckets
	 */
	protected function renderIdleRule( string $scope, array $idleDeclarations, array $allBuckets ): string
	{
		$hasNonIdleOverride = false;
		foreach ( $allBuckets as $stateKey => $declarations ) {
			if ( StateRegistry::BASE_KEY === $stateKey ) {
				continue;
			}

			if ( [] !== $declarations ) {
				$hasNonIdleOverride = true;
				break;
			}
		}

		if ( $hasNonIdleOverride && ! array_key_exists( 'transition', $idleDeclarations ) ) {
			$idleDeclarations['transition'] = self::DEFAULT_TRANSITION;
		}

		if ( [] === $idleDeclarations ) {
			return '';
		}

		return sprintf( '%s { %s }', $scope, $this->joinDeclarations( $idleDeclarations ) );
	}

	/**
	 * Converts the property-keyed attribute bag into a state-keyed
	 * bucket of CSS declarations after running each attribute through
	 * the resolver's `distinctOverrides()`.
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array<string, array<string, string>>
	 */
	protected function bucketByState( array $attributes ): array
	{
		$buckets = [];

		foreach ( $attributes as $property => $value ) {
			if ( ! is_string( $property ) || '' === trim( $property ) ) {
				continue;
			}

			$overrides = $this->resolver->distinctOverrides( $value );

			foreach ( $overrides as $state => $resolved ) {
				// Guard against non-scalar overrides — a malformed
				// `attributes.states` bag could shape its leaves as
				// arrays/objects, and casting those to string produces
				// PHP notices and "Array" literals in the emitted CSS.
				// Booleans pass `is_scalar()` but cast to `''`/`1`, which
				// would emit invalid CSS like `background-color: 1;` —
				// drop them too.
				if ( null === $resolved || ! is_scalar( $resolved ) || is_bool( $resolved ) ) {
					continue;
				}

				$value = is_string( $resolved ) ? $resolved : (string) $resolved;
				if ( '' === $value ) {
					continue;
				}

				$buckets[ $state ][ $property ] = $value;
			}
		}

		return $buckets;
	}

	/**
	 * Resolves the literal selector for a state definition against
	 * the block scope. Replaces `&` with the scope so theme authors
	 * can compose selectors like `&[aria-current="page"]`.
	 *
	 * @param  array{selector: string}  $definition
	 */
	protected function selectorFor( string $scope, array $definition ): string
	{
		$selector = $definition['selector'] ?? '';
		if ( '' === $selector ) {
			return '';
		}

		// Allow comma-separated selector lists.
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
	 * Properties that should NOT carry `!important` — `transition` is
	 * the only one in the v1.0 set; an `!important` transition can't
	 * be cancelled by host CSS, which is rarely what an author wants.
	 *
	 * @var array<int, string>
	 */
	protected const NEVER_IMPORTANT = [ 'transition' ];

	/**
	 * @param  array<string, string>  $declarations
	 */
	protected function joinDeclarations( array $declarations ): string
	{
		$parts = [];

		foreach ( $declarations as $property => $value ) {
			$important = in_array( $property, self::NEVER_IMPORTANT, true ) ? '' : ' !important';
			$parts[]   = sprintf( '%s: %s%s;', $property, $value, $important );
		}

		return implode( ' ', $parts );
	}
}
