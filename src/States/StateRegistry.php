<?php

/**
 * State registry — state design tools (#488).
 *
 * Resolves the editor's active set of interactive states by merging
 * the host theme's declarations into the application's `config()`
 * overrides and finally the package's built-in defaults. Highest
 * layer wins on key collision:
 *
 *   1. theme.json → `settings.custom.artisanpack.states`
 *   2. application config → `artisanpack.visual-editor.states`
 *   3. package defaults  → idle / hover / focus / focus-visible /
 *                          active / disabled
 *
 * States are merged by key via `array_replace_recursive()` — a theme
 * that adds a new `aria-current` key augments the registry; omitting
 * a key keeps the lower layer's value. To REMOVE a state, set it to
 * `null` after the merge.
 *
 * The `idle` slot is the base of every inheritance chain — it has no
 * pseudo-selector and is the value the renderer emits as the
 * default (no `:hover`, no `:focus`, no media wrap). It cannot be
 * removed or aliased.
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

class StateRegistry
{
	/**
	 * The reserved base-slot key — every inheritance chain bottoms out
	 * here.
	 */
	public const BASE_KEY = 'idle';

	/**
	 * Built-in state definitions. Inheritance chains read top-down:
	 * `active` cascades through `hover` then `idle`; `focus-visible`
	 * cascades through `focus` then `idle`.
	 *
	 * @var array<string, array{label: string, selector: string, icon?: string, inheritsFrom?: string, hoverMediaWrap?: bool}>
	 */
	public const DEFAULTS = [
		'idle' => [
			'label'    => 'Idle',
			'selector' => '',
			'icon'     => 'circle',
		],
		'hover' => [
			'label'          => 'Hover',
			'selector'       => '&:hover',
			'icon'           => 'cursor',
			'inheritsFrom'   => 'idle',
			'hoverMediaWrap' => true,
		],
		'focus' => [
			'label'        => 'Focus',
			'selector'     => '&:focus',
			'icon'         => 'target',
			'inheritsFrom' => 'idle',
		],
		'focus-visible' => [
			'label'        => 'Focus visible',
			'selector'     => '&:focus-visible',
			'icon'         => 'target-arrow',
			'inheritsFrom' => 'focus',
		],
		'active' => [
			'label'        => 'Active',
			'selector'     => '&:active',
			'icon'         => 'click',
			'inheritsFrom' => 'hover',
		],
		'disabled' => [
			'label'        => 'Disabled',
			'selector'     => '&:disabled, &[aria-disabled="true"]',
			'icon'         => 'block',
			'inheritsFrom' => 'idle',
		],
	];

	/**
	 * Maximum number of links in an inheritance chain before the
	 * validator gives up. Catches cycles and pathological depth.
	 */
	protected const MAX_CHAIN_DEPTH = 16;

	/**
	 * Resolved, validated registry.
	 *
	 * @var array<string, array{key: string, label: string, selector: string, icon: string, inheritsFrom: ?string, hoverMediaWrap: bool}>
	 */
	protected array $states;

	/**
	 * @param  array<string, array<string, mixed>>  $raw  Pre-resolved
	 *                                                    state map. Pass
	 *                                                    {@see fromLayers()}'s
	 *                                                    output in
	 *                                                    production.
	 */
	public function __construct( array $raw = [] )
	{
		$this->states = $this->validate( $raw );
	}

	/**
	 * Builds a registry from the application's merged config + an
	 * optional `theme.json`-derived overrides array. Use this from the
	 * service provider; tests can also construct directly.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>|null  $configOverrides  Defaults to
	 *                                                      `config('artisanpack.visual-editor.states')`.
	 * @param  array<string, mixed>       $themeOverrides   `settings.custom.artisanpack.states`
	 *                                                      from the active
	 *                                                      `theme.json`.
	 */
	public static function fromLayers( ?array $configOverrides = null, array $themeOverrides = [] ): self
	{
		$config = $configOverrides ?? ( function_exists( 'config' )
			? (array) config( 'artisanpack.visual-editor.states', [] )
			: [] );

		$merged = array_replace_recursive( self::DEFAULTS, $config, $themeOverrides );

		// Allow explicit `null` overrides to drop a built-in state.
		$cleaned = array_filter( $merged, static fn ( $value ) => null !== $value );

		return new self( $cleaned );
	}

	/**
	 * Returns every registered state keyed by slug. `idle` is always
	 * first so iteration follows the inheritance-base-first order.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{key: string, label: string, selector: string, icon: string, inheritsFrom: ?string, hoverMediaWrap: bool}>
	 */
	public function all(): array
	{
		return $this->states;
	}

	/**
	 * Returns the definition for a single state, or `null` if the key
	 * isn't registered.
	 *
	 * @since 1.0.0
	 *
	 * @return array{key: string, label: string, selector: string, icon: string, inheritsFrom: ?string, hoverMediaWrap: bool}|null
	 */
	public function get( string $key ): ?array
	{
		return $this->states[ $key ] ?? null;
	}

	/**
	 * Returns just the state keys in registry order — convenient for
	 * iterating renderer emission or building the inspector switcher.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function keys(): array
	{
		return array_keys( $this->states );
	}

	/**
	 * Checks membership.
	 *
	 * @since 1.0.0
	 */
	public function has( string $key ): bool
	{
		return array_key_exists( $key, $this->states );
	}

	/**
	 * Returns the chain of state keys to walk when resolving an
	 * attribute value at the given state, starting at `$state` itself
	 * and following `inheritsFrom` links until `idle`. Unknown states
	 * collapse to `[idle]`.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function inheritanceChain( string $state ): array
	{
		if ( self::BASE_KEY === $state || ! $this->has( $state ) ) {
			return [ self::BASE_KEY ];
		}

		$chain   = [];
		$current = $state;
		$depth   = 0;

		while ( null !== $current && $depth < self::MAX_CHAIN_DEPTH ) {
			$chain[] = $current;

			if ( self::BASE_KEY === $current ) {
				return $chain;
			}

			$definition = $this->states[ $current ] ?? null;
			$current    = $definition['inheritsFrom'] ?? null;
			$depth++;
		}

		// Always terminate at `idle` so the resolver has a fall-through.
		if ( ! in_array( self::BASE_KEY, $chain, true ) ) {
			$chain[] = self::BASE_KEY;
		}

		return $chain;
	}

	/**
	 * Normalizes a raw state map and verifies invariants:
	 *  - `idle` must be present and have no selector.
	 *  - Every state must declare a non-empty `label`.
	 *  - Selectors must be non-empty strings (except `idle`'s, which
	 *    must be empty).
	 *  - Inheritance chains must terminate at `idle` and contain no
	 *    cycles.
	 *
	 * Throws on the first failure with a descriptive message so theme
	 * authors get actionable feedback when their `theme.json` is bad.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $raw
	 *
	 * @return array<string, array{key: string, label: string, selector: string, icon: string, inheritsFrom: ?string, hoverMediaWrap: bool}>
	 */
	public function validate( array $raw ): array
	{
		if ( ! isset( $raw[ self::BASE_KEY ] ) ) {
			throw new InvalidArgumentException( sprintf(
				'State registry is missing the reserved "%s" base state.',
				self::BASE_KEY
			) );
		}

		$normalized = [];

		foreach ( $raw as $key => $definition ) {
			if ( ! is_string( $key ) || '' === trim( $key ) ) {
				throw new InvalidArgumentException( 'State key must be a non-empty string.' );
			}

			if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_:-]*$/i', $key ) ) {
				throw new InvalidArgumentException( sprintf(
					'State key "%s" must contain only letters, numbers, hyphens, underscores, or colons.',
					$key
				) );
			}

			if ( ! is_array( $definition ) ) {
				throw new InvalidArgumentException( sprintf(
					'State "%s" must be defined as an associative array.',
					$key
				) );
			}

			$label = $definition['label'] ?? '';
			if ( ! is_string( $label ) || '' === trim( $label ) ) {
				throw new InvalidArgumentException( sprintf(
					'State "%s" must declare a non-empty `label`.',
					$key
				) );
			}

			$selector = $definition['selector'] ?? '';
			if ( self::BASE_KEY === $key ) {
				if ( '' !== $selector ) {
					throw new InvalidArgumentException( sprintf(
						'The reserved "%s" state must have an empty selector — it is the base slot.',
						self::BASE_KEY
					) );
				}
			} else {
				if ( ! is_string( $selector ) || '' === trim( $selector ) ) {
					throw new InvalidArgumentException( sprintf(
						'State "%s" must declare a non-empty `selector`.',
						$key
					) );
				}
			}

			$inheritsFrom = $definition['inheritsFrom'] ?? null;

			if ( self::BASE_KEY === $key ) {
				$inheritsFrom = null;
			} elseif ( null !== $inheritsFrom ) {
				if ( ! is_string( $inheritsFrom ) || '' === trim( $inheritsFrom ) ) {
					throw new InvalidArgumentException( sprintf(
						'State "%s" `inheritsFrom` must be a non-empty string or null.',
						$key
					) );
				}

				if ( ! array_key_exists( $inheritsFrom, $raw ) ) {
					throw new InvalidArgumentException( sprintf(
						'State "%s" inherits from "%s", which is not registered.',
						$key,
						$inheritsFrom
					) );
				}
			}

			$normalized[ $key ] = [
				'key'            => $key,
				'label'          => $label,
				'selector'       => is_string( $selector ) ? $selector : '',
				'icon'           => is_string( $definition['icon'] ?? null ) ? $definition['icon'] : '',
				'inheritsFrom'   => $inheritsFrom,
				'hoverMediaWrap' => (bool) ( $definition['hoverMediaWrap'] ?? false ),
			];
		}

		( new InheritanceChainValidator() )->assertAcyclic( $normalized );

		// Hoist `idle` to the front so iteration order is stable.
		$idle  = $normalized[ self::BASE_KEY ];
		unset( $normalized[ self::BASE_KEY ] );

		return [ self::BASE_KEY => $idle ] + $normalized;
	}
}
