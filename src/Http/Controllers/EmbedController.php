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
}
