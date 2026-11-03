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
 * Reads string query values and recognizes row-id and composite URL
 * parameters the same way across the site-editor controllers.
 *
 * @since 1.20.0
 */
trait ReadsSiteEditorRouteInput
{
	/**
	 * Read a trimmed string query value.
	 *
	 * Returns an empty string when the parameter is absent or blank, and
	 * null when it isn't a string (`?status[]=x`), so the caller can answer
	 * with {@see invalidStringQuery()} instead of a cast error (#1075, #1092).
	 *
	 * @since 1.20.0
	 *
	 * @param  Request  $request  The incoming request.
	 * @param  string   $key      The query parameter name.
	 *
	 * @return string|null The trimmed value, `''` when absent, or null when invalid.
	 */
	protected function stringQuery( Request $request, string $key ): ?string
	{
		$value = $request->query( $key, '' );

		if ( null === $value ) {
			return '';
		}

		if ( ! is_string( $value ) ) {
			return null;
		}

		return trim( $value );
	}

	/**
	 * The 422 returned for a non-string query value (#1075, #1092).
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $key  The query parameter name.
	 *
	 * @return JsonResponse
	 */
	protected function invalidStringQuery( string $key ): JsonResponse
	{
		$message = sprintf( 'The %s query parameter must be a string.', $key );

		return response()->json( [
			'message' => $message,
			'errors'  => [ $key => [ $message ] ],
		], Response::HTTP_UNPROCESSABLE_ENTITY );
	}

	/**
	 * Split a `<theme>//<slug>` record id into its parts (#1094).
	 *
	 * File-only templates and parts with an all-digit slug use this id,
	 * so a slug such as `404` can't collide with a row id. Returns null
	 * when the value isn't in that shape.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $input  The URL parameter.
	 *
	 * @return array{theme: string, slug: string}|null
	 */
	protected function splitCompositeId( string $input ): ?array
	{
		$parts = explode( '//', $input, 2 );

		if ( 2 !== count( $parts ) || '' === $parts[0] || '' === $parts[1] ) {
			return null;
		}

		return [ 'theme' => $parts[0], 'slug' => $parts[1] ];
	}

	/**
	 * The 422 returned when a `<theme>//<slug>` id names a different theme
	 * than the request's `theme` (#1094).
	 *
	 * @since 1.20.0
	 *
	 * @return JsonResponse
	 */
	protected function compositeThemeMismatch(): JsonResponse
	{
		$message = 'The theme does not match the theme in the record id.';

		return response()->json( [
			'message' => $message,
			'errors'  => [ 'theme' => [ $message ] ],
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
