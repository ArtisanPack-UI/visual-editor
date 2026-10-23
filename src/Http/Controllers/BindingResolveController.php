<?php

/**
 * BindingResolve controller.
 *
 * Stateless endpoint the editor calls when it needs the resolved
 * values of active bindings (#504). The HOC overlays the resolved
 * values on top of the block's static `attrs` so the canvas reflects
 * the binding in real time instead of waiting for the frontend
 * renderer to do the substitution.
 *
 * The editor batches every bound block on the canvas into one
 * `items` request (#1009), so inserting a pattern with dozens of
 * bound blocks costs one request instead of one per block. The
 * single-block payload is still accepted.
 *
 * Why a separate endpoint and not `blocks/preview`?
 * - Preview wants the rendered HTML (string). The canvas overlay wants
 *   the structured value (object / array / scalar) so the block's
 *   `edit` component can drive its own rendering off it.
 * - Resolution is cheap (no block rendering, no view layer); making
 *   the editor pay for a full preview round-trip per binding is wasteful.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers;

use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingRecordAccess;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BindingResolveController extends Controller
{
	/**
	 * Most items one batch request may carry.
	 *
	 * @since 1.17.0
	 */
	public const MAX_ITEMS = 100;

	public function __construct(
		protected BindingResolver $resolver,
		protected ResourceResolver $resourceResolver,
	) {
	}

	/**
	 * Resolve one block's bindings, or a batch of blocks' bindings.
	 *
	 * Single-block request body:
	 * - `attrs`    array<string, mixed>  Block's static fallback values.
	 * - `bindings` array<string, array>  Sidecar map (attr → binding).
	 * - `context`  array  Resource + id + optional draft snapshot.
	 *
	 * Single-block response:
	 * - `values` array<string, mixed>  Resolved values, keyed by attribute.
	 *   Only attributes that have a binding are present; the client
	 *   merges them on top of its static attributes.
	 *
	 * A request with an `items` list is a batch; see {@see resolveBatch()}.
	 *
	 * @since 1.1.0
	 * @since 1.17.0 Accepts a batch of blocks under `items`.
	 */
	public function resolve( Request $request ): JsonResponse
	{
		if ( $request->has( 'items' ) ) {
			return $this->resolveBatch( $request->input( 'items' ) );
		}

		$attrs    = $request->input( 'attrs', [] );
		$bindings = $request->input( 'bindings', [] );

		if ( ! is_array( $attrs ) || ! is_array( $bindings ) ) {
			return response()->json( [
				'error'   => 'invalid_payload',
				'message' => 'attrs and bindings must be arrays.',
			], 422 );
		}

		if ( [] === $bindings ) {
			return response()->json( [ 'values' => (object) [] ] );
		}

		$context = $this->buildContext( $request );

		$resolved = $this->resolver->resolve( [ $this->previewNode( $attrs, $bindings ) ], $context );

		return response()->json( [
			'values' => $this->boundValues( $resolved[0] ?? [], $attrs, $bindings ),
		] );
	}

	/**
	 * Resolve a batch of blocks' bindings in one request (#1009).
	 *
	 * Each item is `{ key, attrs, bindings, context }`, the single-block
	 * payload plus a client-chosen `key`. Items that share a context
	 * record are resolved together: the record is loaded and its `view`
	 * policy checked once, then {@see BindingResolver::resolve()} runs
	 * over one combined tree so relation eager loads are shared too.
	 *
	 * Response:
	 * - `results` list<array>  One entry per item, in request order:
	 *   `{ key, values }` on success, or `{ key, error, message }` when
	 *   the item is malformed (`invalid_item`) or its record is
	 *   forbidden (`forbidden`). One bad item doesn't fail the others.
	 *
	 * A batch that isn't a list, or has more than {@see MAX_ITEMS}
	 * items, fails as a whole with 422.
	 *
	 * @since 1.17.0
	 *
	 * @param  mixed  $items  The request's `items` input.
	 */
	protected function resolveBatch( mixed $items ): JsonResponse
	{
		if ( ! is_array( $items ) || ! array_is_list( $items ) ) {
			return response()->json( [
				'error'   => 'invalid_payload',
				'message' => 'items must be a list.',
			], 422 );
		}

		if ( count( $items ) > self::MAX_ITEMS ) {
			return response()->json( [
				'error'   => 'too_many_items',
				'message' => sprintf( 'At most %d items per batch.', self::MAX_ITEMS ),
			], 422 );
		}

		$results = [];
		$groups  = [];
		$records = [];

		foreach ( $items as $index => $item ) {
			$key = is_array( $item ) ? ( $item['key'] ?? null ) : null;

			if ( ! is_string( $key ) && ! is_int( $key ) ) {
				$results[ $index ] = $this->itemError( null, 'invalid_item', 'Each item needs a string or integer key.' );

				continue;
			}

			$attrs    = $item['attrs'] ?? [];
			$bindings = $item['bindings'] ?? [];
			$context  = $item['context'] ?? [];

			if ( ! is_array( $attrs ) || ! is_array( $bindings ) || ! is_array( $context ) ) {
				$results[ $index ] = $this->itemError( $key, 'invalid_item', 'attrs, bindings and context must be arrays.' );

				continue;
			}

			if ( [] === $bindings ) {
				$results[ $index ] = [ 'key' => $key, 'values' => (object) [] ];

				continue;
			}

			$resource = $context['resource'] ?? null;
			$id       = $context['id'] ?? null;
			$draft    = is_array( $context['draft'] ?? null ) ? $context['draft'] : [];

			$recordKey = json_encode( [ $resource, $id ] );

			if ( ! array_key_exists( $recordKey, $records ) ) {
				$model = $this->resolveContextModel( $resource, $id );

				$records[ $recordKey ] = [
					'model'   => $model,
					'allowed' => null === $model || BindingRecordAccess::allows( $model ),
				];
			}

			if ( ! $records[ $recordKey ]['allowed'] ) {
				$results[ $index ] = $this->itemError( $key, 'forbidden', 'You may not view the record this binding reads.' );

				continue;
			}

			// The draft snapshot is part of the context, so items on the
			// same record with different drafts resolve separately.
			$groupKey = $recordKey . '|' . json_encode( $draft );

			$groups[ $groupKey ]['record']  = $recordKey;
			$groups[ $groupKey ]['draft']   = $draft;
			$groups[ $groupKey ]['items'][] = [
				'index'    => $index,
				'key'      => $key,
				'attrs'    => $attrs,
				'bindings' => $bindings,
			];
		}

		foreach ( $groups as $group ) {
			$context = new BindingContext( $records[ $group['record'] ]['model'], $group['draft'] );

			$tree = array_map(
				fn ( array $item ): array => $this->previewNode( $item['attrs'], $item['bindings'] ),
				$group['items'],
			);

			$resolved = $this->resolver->resolve( $tree, $context );

			foreach ( $group['items'] as $position => $item ) {
				$results[ $item['index'] ] = [
					'key'    => $item['key'],
					'values' => $this->boundValues( $resolved[ $position ] ?? [], $item['attrs'], $item['bindings'] ),
				];
			}
		}

		ksort( $results );

		return response()->json( [ 'results' => array_values( $results ) ] );
	}

	/**
	 * Wrap one block's attrs and bindings in a resolvable tree node.
	 *
	 * @since 1.17.0
	 *
	 * @param  array<string, mixed>  $attrs     The block's static attributes.
	 * @param  array<string, mixed>  $bindings  The block's bindings sidecar.
	 *
	 * @return array{name: string, attrs: array<string, mixed>, bindings: array<string, mixed>}
	 */
	protected function previewNode( array $attrs, array $bindings ): array
	{
		return [
			'name'     => 'editor/preview',
			'attrs'    => $attrs,
			'bindings' => $bindings,
		];
	}

	/**
	 * Pick the resolved value of each bound attribute out of a resolved
	 * node. Only keys that had a binding declared are returned so the
	 * client doesn't accidentally overwrite an unbound attr.
	 *
	 * @since 1.17.0
	 *
	 * @param  array<string, mixed>  $node      The resolved tree node.
	 * @param  array<string, mixed>  $attrs     The block's static attributes, used as fallbacks.
	 * @param  array<string, mixed>  $bindings  The block's bindings sidecar.
	 *
	 * @return array<string, mixed>|object An empty object when no attribute is bound.
	 */
	protected function boundValues( array $node, array $attrs, array $bindings ): array|object
	{
		$resolvedAttrs = is_array( $node['attrs'] ?? null ) ? $node['attrs'] : [];

		$values = [];

		foreach ( $bindings as $attribute => $_ ) {
			if ( ! is_string( $attribute ) ) {
				continue;
			}

			$values[ $attribute ] = $resolvedAttrs[ $attribute ] ?? ( $attrs[ $attribute ] ?? null );
		}

		return empty( $values ) ? (object) [] : $values;
	}

	/**
	 * A per-item batch error entry.
	 *
	 * @since 1.17.0
	 *
	 * @param  int|string|null  $key      The item's key, or null when it had none.
	 * @param  string           $error    Machine-readable error code.
	 * @param  string           $message  Human-readable explanation.
	 *
	 * @return array{key: int|string|null, error: string, message: string}
	 */
	protected function itemError( int|string|null $key, string $error, string $message ): array
	{
		return [
			'key'     => $key,
			'error'   => $error,
			'message' => $message,
		];
	}

	/**
	 * Resolve the parent model from the request's context payload.
	 * Mirrors {@see BlockPreviewController::buildContext()} but inline
	 * so we don't have to share the helper.
	 *
	 * A record whose model has a policy needs the user's `view` ability,
	 * or the request fails with 403 (F-13).
	 *
	 * @since 1.1.0
	 * @since 1.15.0 Requires the `view` ability when the model has a policy.
	 */
	protected function buildContext( Request $request ): BindingContext
	{
		$resource = $request->input( 'context.resource' );
		$id       = $request->input( 'context.id' );
		$draft    = $request->input( 'context.draft', [] );

		$model = $this->resolveContextModel( $resource, $id );

		// F-13: a record the host guards with a policy needs `view`.
		if ( null !== $model && ! BindingRecordAccess::allows( $model ) ) {
			abort( Response::HTTP_FORBIDDEN );
		}

		return new BindingContext(
			$model,
			is_array( $draft ) ? $draft : [],
		);
	}

	/**
	 * Load the record a binding context names, or null when the context
	 * names none or the record doesn't exist.
	 *
	 * @since 1.17.0
	 *
	 * @param  mixed  $resource  The context's resource key.
	 * @param  mixed  $id        The context's record id.
	 */
	protected function resolveContextModel( mixed $resource, mixed $id ): ?Model
	{
		if ( ! is_string( $resource ) || '' === $resource || ( ! is_string( $id ) && ! is_int( $id ) ) ) {
			return null;
		}

		try {
			$resolved = $this->resourceResolver->resolve( $resource, $id );
		} catch ( NotFoundHttpException | InvalidArgumentException $e ) {
			return null;
		}

		return $resolved instanceof Model ? $resolved : null;
	}
}
