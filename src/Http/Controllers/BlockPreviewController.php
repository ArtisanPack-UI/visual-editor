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
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Stringable;
use Throwable;

class BlockPreviewController extends Controller
{
	public function __construct( protected DynamicBlockRegistry $registry )
	{
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

		$block = $this->registry->get( $name );

		if ( null === $block ) {
			return response()->json( [
				'error' => 'block_not_registered',
				'name'  => $name,
			], 404 );
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
			$html = $this->renderToString( $block, $validated );
		} catch ( Throwable $e ) {
			report( $e );

			return response()->json( [
				'error'   => 'render_failed',
				'name'    => $name,
				'message' => $e->getMessage(),
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
	 * @param  array<string, mixed>  $attrs
	 */
	protected function renderToString( DynamicBlock $block, array $attrs ): string
	{
		$result = $block->render( $attrs );

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
}
