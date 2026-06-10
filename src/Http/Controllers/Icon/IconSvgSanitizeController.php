<?php

/**
 * Custom SVG sanitization endpoint.
 *
 * Phase 5 (#556) of the Icon Block feature (#494). Authors paste or
 * upload a one-off SVG in the block sidebar; the editor POSTs it here
 * to get the canonical sanitized result + the human-readable list of
 * things that were stripped. The block persists the sanitized SVG into
 * its `customSvg` attribute and renders the warnings inline.
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

use ArtisanPackUI\VisualEditor\Services\Icon\SvgSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IconSvgSanitizeController extends Controller
{
	/**
	 * Cap the payload at 256 KB. Inline icon SVGs are usually a few KB;
	 * anything dramatically larger is either a misuse (full illustrations
	 * being pasted as icons) or a DoS attempt against the DOM parser.
	 */
	private const MAX_INPUT_BYTES = 262_144;

	public function __construct( protected SvgSanitizer $sanitizer )
	{
	}

	public function store( Request $request ): JsonResponse
	{
		$rawSvg = $request->input( 'svg', '' );
		if ( ! is_string( $rawSvg ) ) {
			return response()->json( [
				'svg'      => '',
				'warnings' => [ 'svg must be a string' ],
			], 422 );
		}

		if ( strlen( $rawSvg ) > self::MAX_INPUT_BYTES ) {
			return response()->json( [
				'svg'      => '',
				'warnings' => [ 'svg exceeds the 256 KB size limit' ],
			], 413 );
		}

		$result = $this->sanitizer->sanitize( $rawSvg );

		return response()->json( [
			'svg'      => $result->sanitized,
			'warnings' => $result->warnings,
		] );
	}
}
