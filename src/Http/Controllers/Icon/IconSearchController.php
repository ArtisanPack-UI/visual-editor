<?php

/**
 * Icon search endpoint.
 *
 * Backs the Phase 4 picker (#555). Accepts `?q=`, `?set=`, `?page=`,
 * `?per_page=` and returns a paginated envelope shaped for the React
 * picker's grid.
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
use ArtisanPackUI\VisualEditor\Services\Icon\IconSvgResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IconSearchController extends Controller
{
	public function __construct(
		protected IconCatalog $catalog,
		protected IconSvgResolver $resolver,
	) {
	}

	public function index( Request $request ): JsonResponse
	{
		// Explicit `is_string` guards before string-cast — `?q[]=foo`
		// would otherwise cast to the literal "Array" and become the
		// search needle.
		$rawQuery = $request->query( 'q', '' );
		$query    = is_string( $rawQuery ) ? $rawQuery : '';
		$set      = $request->query( 'set' );
		$page     = max( 1, (int) $request->query( 'page', 1 ) );
		$perPage  = max( 1, (int) $request->query( 'per_page', IconCatalog::DEFAULT_PER_PAGE ) );

		$result = $this->catalog->search(
			$query,
			is_string( $set ) && '' !== $set ? $set : null,
			$page,
			$perPage,
		);

		// Decorate each row with inline SVG markup so the picker grid
		// can render the real glyphs instead of just the names. The
		// SVGs come straight from disk via the trusted icons registry,
		// so no sanitization is layered on here.
		$result['data'] = array_map(
			fn ( array $icon ): array => $icon + [
				'svg' => $this->resolver->resolve( $icon['set'], $icon['name'] ) ?? '',
			],
			$result['data'],
		);

		return response()->json( $result );
	}
}
