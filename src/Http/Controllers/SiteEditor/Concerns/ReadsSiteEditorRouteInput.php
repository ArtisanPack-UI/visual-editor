<?php

/**
 * Shared route and query input helpers for the site-editor controllers.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers\SiteEditor\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Reads the `?theme=` query value and recognizes row-id URL parameters
 * the same way across the site-editor controllers.
 *
 * @since 1.20.0
 */
trait ReadsSiteEditorRouteInput
{
	/**
	 * Read the trimmed `?theme=` query value.
	 *
	 * Returns an empty string when the parameter is absent or blank, and
	 * null when it isn't a string (`?theme[]=x`), so the caller can answer
	 * with {@see invalidThemeQuery()} instead of a cast error (#1075).
	 *
	 * @since 1.20.0
	 *
	 * @param  Request  $request  The incoming request.
	 *
	 * @return string|null The theme slug, `''` when absent, or null when invalid.
	 */
	protected function themeQuery( Request $request ): ?string
	{
		$theme = $request->query( 'theme', '' );

		if ( ! is_string( $theme ) ) {
			return null;
		}

		return trim( $theme );
	}

	/**
	 * The 422 returned for a non-string `?theme=` value (#1075).
	 *
	 * @since 1.20.0
	 *
	 * @return JsonResponse
	 */
	protected function invalidThemeQuery(): JsonResponse
	{
		return response()->json( [
			'message' => 'The theme query parameter must be a string.',
			'errors'  => [ 'theme' => [ 'The theme query parameter must be a string.' ] ],
		], Response::HTTP_UNPROCESSABLE_ENTITY );
	}

	/**
	 * Whether a URL parameter could be a row's primary key.
	 *
	 * `0` is the file-only sentinel and never a row id (#438). An all-digit
	 * value can also be a slug, such as `404`, so callers check for a slug
	 * match before treating it as an id (#1074).
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $input  The URL parameter.
	 *
	 * @return bool
	 */
	protected function couldBeRowId( string $input ): bool
	{
		return ctype_digit( $input ) && (int) $input > 0;
	}
}
