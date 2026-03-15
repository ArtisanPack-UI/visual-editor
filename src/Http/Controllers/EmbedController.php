<?php

/**
 * Embed Controller.
 *
 * Handles oEmbed resolution and platform detection
 * requests from embed block edit views.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Http\Controllers
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers;

use ArtisanPackUI\VisualEditor\Services\OEmbedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

/**
 * Controller for embed block server-side operations.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Http\Controllers
 *
 * @since      1.0.0
 */
class EmbedController extends Controller
{
	/**
	 * Resolve a URL via oEmbed or OpenGraph fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param Request       $request The HTTP request.
	 * @param OEmbedService $oembed  The oEmbed service.
	 *
	 * @return JsonResponse
	 */
	public function resolve( Request $request, OEmbedService $oembed ): JsonResponse
	{
		$request->validate( [
			'url'       => 'required|url',
			'maxWidth'  => 'nullable|integer|min:1',
			'maxHeight' => 'nullable|integer|min:1',
		] );

		$url       = $request->input( 'url' );
		$maxWidth  = $request->integer( 'maxWidth' ) ?: null;
		$maxHeight = $request->integer( 'maxHeight' ) ?: null;

		$result = $oembed->resolve( $url, $maxWidth, $maxHeight );

		if ( ! $result ) {
			return response()->json( [
				'success' => false,
				'message' => 'Could not resolve embed for this URL.',
			], 422 );
		}

		$platform = $oembed->detectPlatform( $url );

		return response()->json( [
			'success'  => true,
			'data'     => $result,
			'platform' => $platform,
		] );
	}

	/**
	 * Geocode an address via Nominatim (server-side proxy).
	 *
	 * Nominatim requires a proper User-Agent header which
	 * browser fetch requests may not provide reliably.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return JsonResponse
	 */
	public function geocode( Request $request ): JsonResponse
	{
		$request->validate( [
			'q' => 'required|string|max:500',
		] );

		$query = $request->input( 'q' );

		try {
			$response = Http::withHeaders( [
				'Accept'     => 'application/json',
				'User-Agent' => 'ArtisanPackUI-VisualEditor/1.0 (geocoding proxy)',
			] )
				->timeout( 5 )
				->connectTimeout( 2 )
				->get( 'https://nominatim.openstreetmap.org/search', [
					'format' => 'json',
					'limit'  => 1,
					'q'      => $query,
				] );
		} catch ( \Throwable $e ) {
			return response()->json( [
				'success' => false,
				'results' => [],
			], 504 );
		}

		if ( ! $response->successful() ) {
			return response()->json( [
				'success' => false,
				'results' => [],
			], 422 );
		}

		return response()->json( [
			'success' => true,
			'results' => $response->json(),
		] );
	}
}
