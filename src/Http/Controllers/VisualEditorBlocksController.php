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
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class VisualEditorBlocksController extends Controller
{
	public function __construct( protected BlockTypeRegistry $registry )
	{
	}

	/**
	 * Returns the list of registered block types.
	 *
	 * @since 1.0.0
	 */
	public function index(): JsonResponse
	{
		return response()->json( [
			'blocks' => $this->registry->all(),
		] );
	}
}
