<?php

/**
 * Binding source that walks a dotted-path off the parent model and
 * returns the value at the end of the chain.
 *
 * Accepts `args.path` (e.g. `author.profile.display_name`,
 * `categories.0.name`). Each non-leaf segment must resolve to either
 * another Eloquent model, a Collection / Arrayable, or an array — the
 * source bails to `null` (which becomes "empty" for the policy layer)
 * the moment a segment dead-ends.
 *
 * Relations encountered along the path are also returned from
 * {@see self::eagerLoadRelations()} so the resolver can preload the
 * full chain on the parent model with a single `loadMissing()` call,
 * keeping N+1 risk down to "one query per distinct relation chain in
 * the tree."
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Bindings\Sources;

use ArrayAccess;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingAttributeAllowlist;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\BlockBindingSource;
use ArtisanPackUI\VisualEditor\Services\Bindings\ModelAttributeVisibility;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class RelationSource implements BlockBindingSource
{
	public function name(): string
	{
		return 'relation';
	}

	public function resolve( BindingContext $context, array $args ): mixed
	{
		$path = is_string( $args['path'] ?? null ) ? trim( $args['path'] ) : '';

		if ( '' === $path ) {
			return null;
		}

		$model = $context->model();

		if ( ! $model instanceof Model ) {
			return null;
		}

		$segments = explode( '.', $path );
		$current  = $model;

		foreach ( $segments as $segment ) {
			$current = $this->walkSegment( $current, $segment, $model, $path );

			if ( null === $current ) {
				return null;
			}
		}

		// With the allowlist enforced (#869), a path that ends on a
		// related record (or a list of them) would publish every
		// attribute that record serializes, bypassing the per-attribute
		// check. Only scalar leaves are allowed.
		if ( BindingAttributeAllowlist::isEnforced() && BindingAttributeAllowlist::containsModel( $current ) ) {
			return null;
		}

		return $current;
	}

	public function availableFields( string $resource, ?string $modelClass = null ): array
	{
		// Paths are free-form (`author.name`, `categories.0.title`, etc.).
		// The picker falls back to a free-text input when this is empty.
		return [];
	}

	public function eagerLoadRelations( array $bindingArgs ): array
	{
		$relations = [];

		foreach ( $bindingArgs as $args ) {
			$path = is_string( $args['path'] ?? null ) ? trim( $args['path'] ) : '';

			if ( '' === $path ) {
				continue;
			}

			$segments = explode( '.', $path );

			// Drop the final segment — the leaf is the resolved value,
			// not a relation to load. Numeric segments (collection
			// indices) also can't be eager-loaded.
			array_pop( $segments );

			$relationParts = [];

			foreach ( $segments as $segment ) {
				if ( '' === $segment || ctype_digit( $segment ) ) {
					break;
				}

				$relationParts[] = $segment;
			}

			if ( [] === $relationParts ) {
				continue;
			}

			$relations[ implode( '.', $relationParts ) ] = true;
		}

		return array_keys( $relations );
	}

	/**
	 * Walk one segment of the dotted path. Returns null when the value at
	 * the segment is missing.
	 *
	 * @since 1.1.0
	 * @since 1.15.0 Takes the root model and path for the allowlist check (#869).
	 *
	 * @param  mixed       $current  Value reached so far.
	 * @param  string      $segment  Next path segment.
	 * @param  Model|null  $root     The binding's parent record.
	 * @param  string      $path     Full binding path.
	 */
	protected function walkSegment( mixed $current, string $segment, ?Model $root = null, string $path = '' ): mixed
	{
		if ( '' === $segment ) {
			return null;
		}

		if ( $current instanceof Model ) {
			// Honour the model's `$hidden` / `$visible` lists (#848).
			// Front-end renders expose resolved values to visitors, so a
			// path such as `author.password` must not read a column or
			// relation the model keeps out of its serialized form.
			if ( ! ModelAttributeVisibility::isReadable( $current, $segment ) ) {
				return null;
			}

			// `getAttribute()` calls any method named like the segment,
			// so only walk into typed relations, not arbitrary methods
			// such as `forceDelete` (#850).
			if ( ! ModelAttributeVisibility::isSafeToRead( $current, $segment ) ) {
				return null;
			}

			// Related records are gated by the opt-in allowlist (#869);
			// the parent record's own attributes are not.
			if ( $current !== $root && ! BindingAttributeAllowlist::allows( $current, $segment, $path ) ) {
				return null;
			}

			return $current->getAttribute( $segment );
		}

		if ( $current instanceof Collection ) {
			if ( ctype_digit( $segment ) ) {
				return $current->get( (int) $segment );
			}

			return $current->map( fn ( $item ) => $this->walkSegment( $item, $segment, $root, $path ) )
				->filter( fn ( $v ): bool => null !== $v )
				->values()
				->all();
		}

		if ( is_array( $current ) ) {
			return $current[ $segment ] ?? null;
		}

		if ( $current instanceof ArrayAccess ) {
			return $current[ $segment ] ?? null;
		}

		if ( $current instanceof Arrayable ) {
			$asArray = $current->toArray();

			return $asArray[ $segment ] ?? null;
		}

		return null;
	}
}
