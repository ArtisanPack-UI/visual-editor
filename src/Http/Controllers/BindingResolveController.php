<?php

/**
 * BindingResolve controller.
 *
 * Stateless endpoint the editor calls per block when it needs the
 * resolved value of an active binding (#504). The HOC overlays the
 * resolved values on top of the block's static `attrs` so the canvas
 * reflects the binding in real time instead of waiting for the
 * frontend renderer to do the substitution.
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
	public function __construct(
		protected BindingResolver $resolver,
		protected ResourceResolver $resourceResolver,
	) {
	}

	/**
	 * Resolve a batch of bindings against the request's context.
	 *
	 * Request body:
	 * - `attrs`    array<string, mixed>  Block's static fallback values.
	 * - `bindings` array<string, array>  Sidecar map (attr → binding).
	 * - `context`  array  Resource + id + optional draft snapshot.
	 *
	 * Response:
	 * - `values` array<string, mixed>  Resolved values, keyed by attribute.
	 *   Only attributes that have a binding are present; the client
	 *   merges them on top of its static attributes.
	 *
	 * @since 1.1.0
	 */
	public function resolve( Request $request ): JsonResponse
	{
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

		$tree = [ [
			'name'     => 'editor/preview',
			'attrs'    => $attrs,
			'bindings' => $bindings,
		] ];

		$resolved = $this->resolver->resolve( $tree, $context );

		$resolvedAttrs = is_array( $resolved[0]['attrs'] ?? null ) ? $resolved[0]['attrs'] : [];

		// Only return values for keys that had a binding declared so
		// the client doesn't accidentally overwrite an unbound attr.
		$values = [];

		foreach ( $bindings as $attribute => $_ ) {
			if ( ! is_string( $attribute ) ) {
				continue;
			}

			$values[ $attribute ] = $resolvedAttrs[ $attribute ] ?? ( $attrs[ $attribute ] ?? null );
		}

		return response()->json( [
			'values' => empty( $values ) ? (object) [] : $values,
		] );
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

		$model = null;

		if ( is_string( $resource ) && '' !== $resource && ( is_string( $id ) || is_int( $id ) ) ) {
			try {
				$resolved = $this->resourceResolver->resolve( $resource, $id );
				$model    = $resolved instanceof Model ? $resolved : null;
			} catch ( NotFoundHttpException | InvalidArgumentException $e ) {
				$model = null;
			}
		}

		// F-13: a record the host guards with a policy needs `view`.
		if ( null !== $model && ! BindingRecordAccess::allows( $model ) ) {
			abort( Response::HTTP_FORBIDDEN );
		}

		return new BindingContext(
			$model,
			is_array( $draft ) ? $draft : [],
		);
	}
}
