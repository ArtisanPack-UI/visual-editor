<?php

/**
 * BlockPreview controller.
 *
 * Generic server-renderer endpoint for dynamic blocks. Accepts
 * `{ name, attributes }`, resolves the registered {@see DynamicBlock},
 * validates and authorizes the call, runs `render()`, and returns the
 * resulting HTML.
 *
 * The editor batches every server-rendered block on the canvas into one
 * `items` request (#1010), so inserting a pattern with many such blocks
 * costs one request instead of one per block. The single-block payload
 * is still accepted.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers;

use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditor\Http\Requests\BlockPreviewRequest;
use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingRecordAccess;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Stringable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class BlockPreviewController extends Controller
{
	/**
	 * Format a block name must match; mirrors {@see BlockPreviewRequest}.
	 *
	 * @since 1.17.0
	 */
	protected const NAME_PATTERN = '/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/';

	public function __construct(
		protected DynamicBlockRegistry $registry,
		protected BindingResolver $bindingResolver,
		protected ResourceResolver $resourceResolver,
	) {
	}

	/**
	 * Render a registered dynamic block for the given attributes.
	 *
	 * A request with an `items` list is a batch; see {@see previewBatch()}.
	 *
	 * @since 1.0.0
	 * @since 1.17.0 Accepts a batch of blocks under `items`.
	 */
	public function preview( BlockPreviewRequest $request ): JsonResponse
	{
		if ( $request->has( 'items' ) ) {
			/** @var list<mixed> $items */
			$items = $request->validated( 'items' );

			return $this->previewBatch( $request, $items );
		}

		/** @var string $name */
		$name = $request->validated( 'name' );

		/** @var array<string, mixed> $attributes */
		$attributes = $request->validated( 'attributes' ) ?? [];

		/** @var array<int, array<string, mixed>> $innerBlocks */
		$innerBlocks = $request->validated( 'innerBlocks' ) ?? [];

		/** @var array<string, mixed> $bindings */
		$bindings = $request->validated( 'bindings' ) ?? [];

		$context = [] !== $bindings ? $this->buildContext( $request ) : null;

		[ $status, $body ] = $this->renderBlock( $request, $name, $attributes, $innerBlocks, $bindings, $context );

		return response()->json( $body, $status );
	}

	/**
	 * Render a batch of blocks in one request (#1010).
	 *
	 * Each item is `{ key, name, attributes, innerBlocks, bindings,
	 * context }`, the single-block payload plus a client-chosen `key`.
	 * A context record shared by several items is loaded and its `view`
	 * policy checked once.
	 *
	 * Response:
	 * - `results` list<array>  One entry per item, in request order:
	 *   `{ key, name, html }` on success, or `{ key, name, error,
	 *   message, status }` on failure, where `status` is the HTTP status
	 *   the single-block request would have returned. One failing item
	 *   doesn't fail the others.
	 *
	 * @since 1.17.0
	 *
	 * @param  list<mixed>  $items  The validated `items` list.
	 */
	protected function previewBatch( BlockPreviewRequest $request, array $items ): JsonResponse
	{
		$results = [];
		$records = [];

		foreach ( $items as $item ) {
			$key = is_array( $item ) ? ( $item['key'] ?? null ) : null;

			if ( ! is_string( $key ) && ! is_int( $key ) ) {
				$results[] = $this->itemError( null, null, 'invalid_item', 'Each item needs a string or integer key.', 422 );

				continue;
			}

			$name = $item['name'] ?? null;

			if ( ! is_string( $name ) || 1 !== preg_match( self::NAME_PATTERN, $name ) ) {
				$results[] = $this->itemError( $key, null, 'invalid_item', 'Each item needs a valid block name.', 422 );

				continue;
			}

			$attributes  = $item['attributes'] ?? [];
			$innerBlocks = $item['innerBlocks'] ?? [];
			$bindings    = $item['bindings'] ?? [];
			$context     = $item['context'] ?? [];

			if ( ! is_array( $attributes ) || ! is_array( $innerBlocks ) || ! is_array( $bindings ) || ! is_array( $context ) ) {
				$results[] = $this->itemError( $key, $name, 'invalid_item', 'attributes, innerBlocks, bindings and context must be arrays.', 422 );

				continue;
			}

			$bindingContext = null;

			if ( [] !== $bindings ) {
				$resource  = $context['resource'] ?? null;
				$id        = $context['id'] ?? null;
				$recordKey = (string) json_encode( [ $resource, $id ] );

				if ( ! array_key_exists( $recordKey, $records ) ) {
					$model = $this->resolveContextModel( $resource, $id );

					$records[ $recordKey ] = [
						'model'   => $model,
						'allowed' => null === $model || BindingRecordAccess::allows( $model ),
					];
				}

				if ( ! $records[ $recordKey ]['allowed'] ) {
					$results[] = $this->itemError( $key, $name, 'forbidden', 'You may not view the record this block reads.', 403 );

					continue;
				}

				$bindingContext = new BindingContext(
					$records[ $recordKey ]['model'],
					is_array( $context['draft'] ?? null ) ? $context['draft'] : [],
				);
			}

			try {
				[ $status, $body ] = $this->renderBlock( $request, $name, $attributes, $innerBlocks, $bindings, $bindingContext );
			} catch ( Throwable $e ) {
				report( $e );

				[ $status, $body ] = [ 500, [
					'error'   => 'render_failed',
					'name'    => $name,
					'message' => 'Rendering failed.',
				] ];
			}

			$results[] = 200 === $status
				? [ 'key' => $key ] + $body
				: [ 'key' => $key ] + $body + [ 'status' => $status ];
		}

		return response()->json( [ 'results' => $results ] );
	}

	/**
	 * Resolve, validate, authorize and render one block.
	 *
	 * Returns the HTTP status and JSON body a single-block request
	 * responds with, so the batch path can report the same outcome per
	 * item.
	 *
	 * @since 1.17.0
	 *
	 * @param  string                            $name         The block name.
	 * @param  array<string, mixed>              $attributes   The block's attributes.
	 * @param  array<int, array<string, mixed>>  $innerBlocks  The block's inner tree.
	 * @param  array<string, mixed>              $bindings     The block's bindings sidecar.
	 * @param  BindingContext|null               $context      Context to resolve bindings against; null when unbound.
	 *
	 * @return array{0: int, 1: array<string, mixed>}
	 */
	protected function renderBlock(
		BlockPreviewRequest $request,
		string $name,
		array $attributes,
		array $innerBlocks,
		array $bindings,
		?BindingContext $context,
	): array {
		$block = $this->registry->get( $name );

		if ( null === $block ) {
			return [ 404, [
				'error' => 'block_not_registered',
				'name'  => $name,
			] ];
		}

		if ( [] !== $bindings && null !== $context ) {
			[ $attributes ] = $this->applyBindings( $attributes, $bindings, $context );
		}

		try {
			$validated = $block->validateAttrs( $attributes );
		} catch ( InvalidArgumentException $e ) {
			return [ 422, [
				'error'   => 'invalid_attributes',
				'name'    => $name,
				'message' => $e->getMessage(),
			] ];
		}

		if ( ! $block->authorize( $request->user(), $validated ) ) {
			return [ 403, [
				'error' => 'unauthorized',
				'name'  => $name,
			] ];
		}

		try {
			$html = $this->renderToString( $block, $validated, $innerBlocks );
		} catch ( Throwable $e ) {
			report( $e );

			return [ 500, [
				'error'   => 'render_failed',
				'name'    => $name,
				'message' => 'Rendering failed.',
			] ];
		}

		return [ 200, [
			'name' => $name,
			'html' => $html,
		] ];
	}

	/**
	 * A per-item batch error entry.
	 *
	 * @since 1.17.0
	 *
	 * @param  int|string|null  $key      The item's key, or null when it had none.
	 * @param  string|null      $name     The item's block name, or null when it had none.
	 * @param  string           $error    Machine-readable error code.
	 * @param  string           $message  Human-readable explanation.
	 * @param  int              $status   The HTTP status a single-block request would return.
	 *
	 * @return array{key: int|string|null, name: string|null, error: string, message: string, status: int}
	 */
	protected function itemError( int|string|null $key, ?string $name, string $error, string $message, int $status ): array
	{
		return [
			'key'     => $key,
			'name'    => $name,
			'error'   => $error,
			'message' => $message,
			'status'  => $status,
		];
	}

	/**
	 * Normalize a block's render() return value to a string.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>             $attrs
	 * @param  array<int, array<string, mixed>> $innerBlocks
	 */
	protected function renderToString( DynamicBlock $block, array $attrs, array $innerBlocks = [] ): string
	{
		// Blocks that need their inner tree at render time (loop,
		// snippet-style templates) implement WantsInnerBlocks so the
		// preview endpoint — the same endpoint the React and Vue
		// renderers hit for server HTML — forwards the tree. Without
		// this branch, DynamicLoopBlock returns an empty string on
		// every non-Blade render surface.
		if ( $block instanceof \ArtisanPackUI\VisualEditor\Blocks\WantsInnerBlocks ) {
			$result = $block->renderWithInner( $attrs, $innerBlocks );
		} else {
			$result = $block->render( $attrs );
		}

		if ( $result instanceof View ) {
			return $result->render();
		}

		if ( $result instanceof Stringable ) {
			return (string) $result;
		}

		if ( is_string( $result ) ) {
			return $result;
		}

		throw new InvalidArgumentException(
			'Dynamic block render() must return a View, Stringable, or string.',
		);
	}

	/**
	 * Run the binding layer (#504) for a single block's attributes.
	 *
	 * Wraps the attributes in a one-block tree so the resolver runs its
	 * usual walk + eager-load preparation, then unpacks the resolved
	 * `attrs` back out. The shape matches what the renderer-side block
	 * tree already uses.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $attributes
	 * @param  array<string, mixed>  $bindings
	 *
	 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
	 */
	protected function applyBindings( array $attributes, array $bindings, BindingContext $context ): array
	{
		$resolved = $this->bindingResolver->resolve(
			[ [
				'attrs'    => $attributes,
				'bindings' => $bindings,
			] ],
			$context,
		);

		$first    = $resolved[0] ?? [];
		$newAttrs = is_array( $first['attrs'] ?? null ) ? $first['attrs'] : $attributes;

		return [ $newAttrs, $bindings ];
	}

	/**
	 * Build the {@see BindingContext} for the preview request.
	 *
	 * Pulls the parent model out of the request's `context.resource` +
	 * `context.id` pair (via the existing {@see ResourceResolver}) when
	 * supplied; missing or unknown resources degrade silently to a
	 * model-less context. The draft snapshot — `context.draft` — is
	 * forwarded verbatim so the source drivers can prefer unsaved
	 * editor edits over saved values.
	 *
	 * A record whose model has a policy needs the user's `view` ability,
	 * or the request fails with 403 (F-13).
	 *
	 * @since 1.1.0
	 * @since 1.15.0 Requires the `view` ability when the model has a policy.
	 */
	protected function buildContext( BlockPreviewRequest $request ): BindingContext
	{
		$resource = $request->validated( 'context.resource' );
		$id       = $request->validated( 'context.id' );
		$draft    = $request->validated( 'context.draft' ) ?? [];

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
