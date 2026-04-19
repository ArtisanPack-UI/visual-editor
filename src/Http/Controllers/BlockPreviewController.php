<?php

/**
 * BlockPreview controller.
 *
 * Stub POST endpoint that will server-render a block tree preview once M6
 * lands. Until then it validates the payload shape and echoes it back so the
 * React client can wire up the round-trip ahead of the real renderer.
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

use ArtisanPackUI\VisualEditor\Http\Requests\UpdateResourceContentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class BlockPreviewController extends Controller
{
	/**
	 * Returns a pass-through preview of the submitted block tree.
	 *
	 * Reuses UpdateResourceContentRequest so the stub enforces the same
	 * BlockTreeRule validation the real renderer will need.
	 *
	 * @since 1.0.0
	 */
	public function preview( UpdateResourceContentRequest $request ): JsonResponse
	{
		/** @var array<int, array<string, mixed>> $blocks */
		$blocks = $request->validated( 'blocks' );

		return response()->json( [
			'status' => 'stub',
			'blocks' => $blocks,
			'html'   => null,
		] );
	}
}
