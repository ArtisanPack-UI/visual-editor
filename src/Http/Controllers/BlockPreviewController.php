<?php

/**
 * BlockPreview controller.
 *
 * Generic server-renderer endpoint for dynamic blocks. Accepts
 * `{ name, attributes }`, resolves the registered {@see DynamicBlock},
 * validates and authorizes the call, runs `render()`, and returns the
 * resulting HTML.
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
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Stringable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class BlockPreviewController extends Controller
{
	public function __construct(
		protected DynamicBlockRegistry $registry,
		protected BindingResolver $bindingResolver,
		protected ResourceResolver $resourceResolver
	) {
	}

	/**
	 * Render a registered dynamic block for the given attributes.
	 *
	 * @since 1.0.0
	 */
	public function preview( BlockPreviewRequest $request ): JsonResponse
	{
		/** @var string $name */
		$name = $request->validated( 'name' );

		/** @var array<string, mixed> $attributes */
		$attributes = $request->validated( 'attributes' ) ?? [];

		/** @var array<int, array<string, mixed>> $innerBlocks */
		$innerBlocks = $request->validated( 'innerBlocks' ) ?? [];

		/** @var array<string, mixed> $bindings */
		$bindings = $request->validated( 'bindings' ) ?? [];

		$block = $this->registry->get( $name );

		if ( null === $block ) {
			return response()->json( [
				'error' => 'block_not_registered',
				'name'  => $name,
			], 404 );
		}

		if ( [] !== $bindings ) {
			$context = $this->buildContext( $request );

			[ $attributes, $bindings ] = $this->applyBindings( $attributes, $bindings, $context );
		}

		try {
			$validated = $block->validateAttrs( $attributes );
		} catch ( InvalidArgumentException $e ) {
			return response()->json( [
				'error'   => 'invalid_attributes',
				'name'    => $name,
				'message' => $e->getMessage(),
			], 422 );
		}

		if ( ! $block->authorize( $request->user(), $validated ) ) {
			return response()->json( [
				'error' => 'unauthorized',
				'name'  => $name,
			], 403 );
		}

		try {
			$html = $this->renderToString( $block, $validated, $innerBlocks );
		} catch ( Throwable $e ) {
			report( $e );

			return response()->json( [
				'error'   => 'render_failed',
				'name'    => $name,
				'message' => 'Rendering failed.',
			], 500 );
		}

		return response()->json( [
			'name' => $name,
			'html' => $html,
		] );
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
			'Dynamic block render() must return a View, Stringable, or string.'
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
			$context
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
	 * @since 1.1.0
	 */
	protected function buildContext( BlockPreviewRequest $request ): BindingContext
	{
		$resource = $request->validated( 'context.resource' );
		$id       = $request->validated( 'context.id' );
		$draft    = $request->validated( 'context.draft' ) ?? [];

		$model = null;

		if ( is_string( $resource ) && '' !== $resource && ( is_string( $id ) || is_int( $id ) ) ) {
			try {
				$resolved = $this->resourceResolver->resolve( $resource, $id );
				$model    = $resolved instanceof Model ? $resolved : null;
			} catch ( NotFoundHttpException | InvalidArgumentException $e ) {
				$model = null;
			}
		}

		return new BindingContext(
			$model,
			is_array( $draft ) ? $draft : [],
		);
	}
}
