<?php

/**
 * Icon SVG lookup.
 *
 * Backs the Phase 4 canvas render (#555): once an iconRef is saved on a
 * block, the editor fetches the actual SVG markup through this endpoint
 * and inlines it into the placeholder. The picker has the SVG already
 * (it ships inline with search results), but a reopened post-editor
 * session needs a way to resolve the saved iconRef without reopening
 * the picker.
 *
 * Two forms share the route: `?set=fas&name=cube` resolves one icon, and
 * `?icons=fas:cube,fab:react` resolves up to {@see self::MAX_BATCH} icons
 * in one request (#1012), so a pattern with many distinct icons doesn't
 * send one request per icon.
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
	/**
	 * Most icons one batched request may ask for.
	 *
	 * @since 1.17.0
	 */
	public const MAX_BATCH = 100;

	public function __construct( protected IconSvgResolver $resolver )
	{
	}

	public function show( Request $request ): JsonResponse
	{
		if ( $request->query->has( 'icons' ) ) {
			return $this->batch( $request );
		}

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

		// Icon SVGs are static per (set, name), so let the browser reuse
		// the response across editor sessions (#1004). `private` because
		// the route sits behind the content-editor gate.
		return response()
			->json( [ 'svg' => $svg ] )
			->header( 'Cache-Control', 'private, max-age=86400' );
	}

	/**
	 * Resolve a comma-separated `set:name` list into a `{ "set:name": svg }`
	 * map. Unknown or malformed icons map to `null` without failing the
	 * rest of the batch.
	 *
	 * @since 1.17.0
	 *
	 * @param  Request  $request  The incoming request.
	 *
	 * @return JsonResponse The keyed SVG map, or a 400 for an empty or oversized list.
	 */
	protected function batch( Request $request ): JsonResponse
	{
		$raw  = $request->query( 'icons' );
		$keys = is_string( $raw )
			? array_values( array_unique( array_filter( array_map( 'trim', explode( ',', $raw ) ), 'strlen' ) ) )
			: [];

		if ( [] === $keys ) {
			return response()->json( [ 'message' => __( 'No icons were requested.' ) ], 400 );
		}

		if ( count( $keys ) > self::MAX_BATCH ) {
			return response()->json(
				[ 'message' => __( 'No more than :max icons can be requested at once.', [ 'max' => self::MAX_BATCH ] ) ],
				400,
			);
		}

		$icons       = [];
		$allResolved = true;

		foreach ( $keys as $key ) {
			$parts = explode( ':', $key, 2 );
			$svg   = 2 === count( $parts ) ? $this->resolver->resolve( $parts[0], $parts[1] ) : null;

			$icons[ $key ] = $svg;
			$allResolved   = $allResolved && null !== $svg;
		}

		// Force an object so a malformed numeric key (`?icons=0`) can't turn
		// the map into a JSON list. Like the single form, only cache a fully
		// resolved answer so an icon set registered later isn't hidden.
		$response = response()->json( $icons, 200, [], JSON_FORCE_OBJECT );

		return $allResolved
			? $response->header( 'Cache-Control', 'private, max-age=86400' )
			: $response;
	}
}
