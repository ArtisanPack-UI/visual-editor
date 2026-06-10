<?php

/**
 * Icon sets listing endpoint.
 *
 * Backs the Phase 4 picker's set-family chips (#555). The list comes
 * straight from the bundled `index.json` manifest so chips automatically
 * track whatever sets `scripts/sync-fa-icons.mjs` mirrors.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers\Icon;

use ArtisanPackUI\VisualEditor\Services\Icon\IconCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class IconSetsController extends Controller
{
	public function __construct( protected IconCatalog $catalog )
	{
	}

	public function index(): JsonResponse
	{
		return response()->json( [ 'data' => $this->catalog->sets() ] );
	}
}
