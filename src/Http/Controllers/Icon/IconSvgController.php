<?php

/**
 * Single-icon SVG lookup.
 *
 * Backs the Phase 4 canvas render (#555): once an iconRef is saved on a
 * block, the editor fetches the actual SVG markup through this endpoint
 * and inlines it into the placeholder. The picker has the SVG already
 * (it ships inline with search results), but a reopened post-editor
 * session needs a way to resolve the saved iconRef without reopening
 * the picker.
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

use ArtisanPackUI\VisualEditor\Services\Icon\IconSvgResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IconSvgController extends Controller
{
	public function __construct( protected IconSvgResolver $resolver )
	{
	}

	public function show( Request $request ): JsonResponse
	{
		// Explicit `is_string` guards before string-cast so array-style
		// query params (`?set[]=fas`) don't pass through as the literal
		// "Array" — the resolver would reject them anyway via its set/
		// name allowlist, but guarding here keeps the 400 path clean.
		$rawSet  = $request->query( 'set', '' );
		$rawName = $request->query( 'name', '' );
		$set     = is_string( $rawSet ) ? $rawSet : '';
		$name    = is_string( $rawName ) ? $rawName : '';

		if ( '' === $set || '' === $name ) {
			return response()->json( [ 'svg' => null ], 400 );
		}

		$svg = $this->resolver->resolve( $set, $name );

		if ( null === $svg ) {
			return response()->json( [ 'svg' => null ], 404 );
		}

		return response()->json( [ 'svg' => $svg ] );
	}
}
