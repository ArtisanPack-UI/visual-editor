<?php

/**
 * VisualEditorBlocks controller.
 *
 * Serves the registry of available block types for the React editor.
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

use ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry;
use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class VisualEditorBlocksController extends Controller
{
	public function __construct(
		protected BlockTypeRegistry $registry,
		protected DynamicBlockRegistry $dynamicRegistry,
	) {
	}

	/**
	 * Returns the list of registered block types.
	 *
	 * Each block type that also has a server-rendered implementation in the
	 * {@see DynamicBlockRegistry} is flagged with `apServerRender => true`.
	 * The editor uses that flag to synthesize a generic edit component
	 * (server-side-render preview + attribute-driven inspector controls) for
	 * blocks a downstream registered in PHP without shipping a client `edit`
	 * module — the runtime third-party block seam (#766).
	 *
	 * @since 1.0.0
	 */
	public function index(): JsonResponse
	{
		$blocks = array_map(
			function ( array $block ): array {
				$name = is_string( $block['name'] ?? null ) ? $block['name'] : '';

				if ( '' !== $name && $this->dynamicRegistry->has( $name ) ) {
					$block['apServerRender'] = true;
				}

				return $block;
			},
			$this->registry->all(),
		);

		return response()->json( [
			'blocks' => $blocks,
		] );
	}
}
