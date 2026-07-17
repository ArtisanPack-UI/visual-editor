<?php

/**
 * Resolves block bindings against a {@see BindingContext} and rewrites
 * a block tree's `attrs` so the downstream renderer never sees the
 * binding layer.
 *
 * Walks the tree once per render pass, batches eager-load hints from
 * every registered source driver, then iterates each block's `bindings`
 * map and applies the resolved value (or the empty-value policy) on
 * top of the static `attrs`. Trees without bindings round-trip
 * byte-identically — the resolver is a strict pass-through when no
 * `bindings` keys are present.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Bindings;

use ArtisanPackUI\VisualEditor\Registries\BlockBindingSourceRegistry;
use ArtisanPackUI\VisualEditor\Support\BlockShape;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class BindingResolver
{
	public const POLICY_FALLBACK    = 'fallback';
	public const POLICY_HIDE        = 'hide';
	public const POLICY_PLACEHOLDER = 'placeholder';

	/**
	 * Allowed `onEmpty` policy values. Anything else is treated as `fallback`.
	 *
	 * @var array<int, string>
	 */
	protected const POLICIES = [
		self::POLICY_FALLBACK,
		self::POLICY_HIDE,
		self::POLICY_PLACEHOLDER,
	];

	public function __construct( protected BlockBindingSourceRegistry $registry )
	{
	}

	/**
	 * Resolve every binding in the given block tree against the supplied
	 * context. Returns a new tree — the input is left untouched.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, array<string, mixed>>  $blocks
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function resolve( array $blocks, ?BindingContext $context = null ): array
	{
		if ( [] === $blocks ) {
			return $blocks;
		}

		$context ??= new BindingContext();

		$this->prepareEagerLoads( $blocks, $context );

		return array_values( array_map(
			fn ( $block ) => $this->resolveBlock( $block, $context ),
			$blocks
		) );
	}

	/**
	 * Walk a single block (and its inner blocks) applying every binding.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	protected function resolveBlock( array $block, BindingContext $context ): array
	{
		// Shape-agnostic read via BlockShape — supports both
		// Gutenberg's `attributes` (editor persistence, bindings under
		// `attributes.bindings`) and parse_blocks()'s `attrs`
		// (top-level `bindings`). BlockShape::readBindings guards
		// against a block that has an unrelated attribute literally
		// named `bindings` by requiring at least one entry with a
		// `source` key.
		[ $attrKey, $attrs ] = BlockShape::readAttrs( $block );
		$bindings            = BlockShape::readBindings( $block );

		if ( is_array( $bindings ) && [] !== $bindings ) {
			foreach ( $bindings as $attribute => $binding ) {
				if ( ! is_string( $attribute ) || ! is_array( $binding ) ) {
					continue;
				}

				$attrs = $this->applyBinding( $attribute, $binding, $attrs, $context );
			}

			$block[ $attrKey ] = $attrs;
		}

		if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) && [] !== $block['innerBlocks'] ) {
			$block['innerBlocks'] = array_values( array_map(
				fn ( $inner ) => is_array( $inner ) ? $this->resolveBlock( $inner, $context ) : $inner,
				$block['innerBlocks']
			) );
		}

		return $block;
	}

	/**
	 * Resolve a single attribute binding and merge the result into `$attrs`.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $binding
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return array<string, mixed>
	 */
	protected function applyBinding( string $attribute, array $binding, array $attrs, BindingContext $context ): array
	{
		$sourceName = is_string( $binding['source'] ?? null ) ? $binding['source'] : '';
		$args       = is_array( $binding['args'] ?? null ) ? $binding['args'] : [];
		$policy     = $this->normalizePolicy( $binding['onEmpty'] ?? null );

		$source = '' === $sourceName ? null : $this->registry->get( $sourceName );

		if ( null === $source ) {
			// Unknown driver — log once per resolve pass via report(), then
			// fall back so a missing third-party package never breaks a
			// render.
			$this->logUnknownSource( $sourceName );

			return $this->applyEmpty( $attribute, $attrs, $policy, $binding );
		}

		try {
			$value = $source->resolve( $context, $args );
		} catch ( Throwable $e ) {
			report( $e );

			return $this->applyEmpty( $attribute, $attrs, $policy, $binding );
		}

		if ( $this->isEmpty( $value ) ) {
			return $this->applyEmpty( $attribute, $attrs, $policy, $binding );
		}

		$attrs[ $attribute ] = $value;

		return $attrs;
	}

	/**
	 * Apply the empty-value policy when a binding resolves to nothing.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attrs
	 * @param  array<string, mixed>  $binding
	 *
	 * @return array<string, mixed>
	 */
	protected function applyEmpty( string $attribute, array $attrs, string $policy, array $binding ): array
	{
		if ( self::POLICY_FALLBACK === $policy ) {
			return $attrs;
		}

		if ( self::POLICY_HIDE === $policy ) {
			$attrs[ $attribute ] = null;

			return $attrs;
		}

		// POLICY_PLACEHOLDER
		$placeholder         = $binding['placeholder'] ?? '';
		$attrs[ $attribute ] = is_scalar( $placeholder ) ? (string) $placeholder : '';

		return $attrs;
	}

	/**
	 * Normalize a user-supplied `onEmpty` value to a known policy constant.
	 *
	 * @since 1.1.0
	 */
	protected function normalizePolicy( mixed $policy ): string
	{
		if ( is_string( $policy ) && in_array( $policy, self::POLICIES, true ) ) {
			return $policy;
		}

		return self::POLICY_FALLBACK;
	}

	/**
	 * Test whether a resolved value should trigger the empty-value policy.
	 *
	 * Empty rules:
	 * - `null` is always empty.
	 * - An empty string is empty.
	 * - An empty array is empty.
	 * - `0`, `0.0`, and `false` are NOT empty — they're legitimate values.
	 *
	 * @since 1.1.0
	 */
	protected function isEmpty( mixed $value ): bool
	{
		if ( null === $value ) {
			return true;
		}

		if ( is_string( $value ) && '' === $value ) {
			return true;
		}

		if ( is_array( $value ) && [] === $value ) {
			return true;
		}

		return false;
	}

	/**
	 * Pre-eager-load every relation declared by every source driver across
	 * the entire block tree. Runs once per resolve pass so the parent model
	 * is hydrated with all relations before any binding is resolved.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, array<string, mixed>>  $blocks
	 */
	protected function prepareEagerLoads( array $blocks, BindingContext $context ): void
	{
		$model = $context->model();

		if ( ! $model instanceof Model ) {
			return;
		}

		$bindingArgsBySource = [];
		$this->collectBindings( $blocks, $bindingArgsBySource );

		if ( [] === $bindingArgsBySource ) {
			return;
		}

		$relations = [];

		foreach ( $bindingArgsBySource as $sourceName => $argsList ) {
			$source = $this->registry->get( $sourceName );

			if ( null === $source ) {
				continue;
			}

			try {
				$declaredRelations = $source->eagerLoadRelations( $argsList );
			} catch ( Throwable $e ) {
				// A misbehaving source driver must not break the wider
				// render pass — report the failure and treat it as if
				// the driver declared no eager loads.
				report( $e );
				continue;
			}

			foreach ( $declaredRelations as $relation ) {
				if ( is_string( $relation ) && '' !== $relation ) {
					$relations[ $relation ] = true;
				}
			}
		}

		if ( [] === $relations ) {
			return;
		}

		$missing = array_filter(
			array_keys( $relations ),
			static fn ( string $relation ): bool => ! $model->relationLoaded( $relation )
		);

		if ( [] !== $missing ) {
			try {
				$model->loadMissing( $missing );
			} catch ( Throwable $e ) {
				// DB errors / missing relations / etc. — bindings on
				// dotted paths will degrade to fallback at resolve
				// time. Don't take the whole render down.
				report( $e );
			}
		}
	}

	/**
	 * Walk the tree collecting every binding's args, grouped by source name.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, array<string, mixed>>     $blocks
	 * @param  array<string, array<int, array<string, mixed>>>  $collected
	 */
	protected function collectBindings( array $blocks, array &$collected ): void
	{
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			// Shape-agnostic bindings read via BlockShape — sees both
			// top-level (parse_blocks) and nested-in-attributes (editor)
			// placements, and rejects false positives where a block has
			// an unrelated attribute named `bindings`.
			$bindings = BlockShape::readBindings( $block );

			if ( is_array( $bindings ) ) {
				foreach ( $bindings as $binding ) {
					if ( ! is_array( $binding ) ) {
						continue;
					}

					$sourceName = is_string( $binding['source'] ?? null ) ? $binding['source'] : '';

					if ( '' === $sourceName ) {
						continue;
					}

					$args                       = is_array( $binding['args'] ?? null ) ? $binding['args'] : [];
					$collected[ $sourceName ][] = $args;
				}
			}

			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$this->collectBindings( $block['innerBlocks'], $collected );
			}
		}
	}

	/**
	 * Report an unknown source driver via Laravel's `report()` helper so
	 * the render keeps going. Inspector UIs surface the warning separately
	 * during authoring.
	 *
	 * @since 1.1.0
	 */
	protected function logUnknownSource( string $sourceName ): void
	{
		report( new BindingException( sprintf(
			'Block binding source "%s" is not registered. Falling back.',
			'' === $sourceName ? '(empty)' : $sourceName
		) ) );
	}
}
