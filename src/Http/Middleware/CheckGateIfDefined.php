<?php

/**
 * Check Gate If Defined Middleware.
 *
 * Only performs an authorization check when the gate has been
 * explicitly registered. When the gate is not defined (e.g. the
 * cms-framework is not installed), the request passes through
 * without any authorization check (graceful degradation).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Http\Middleware
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that checks an authorization gate only when it is defined.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Http\Middleware
 *
 * @since      1.0.0
 */
class CheckGateIfDefined
{
	/**
	 * Handle an incoming request.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The incoming request.
	 * @param Closure $next    The next middleware.
	 * @param string  $ability The gate ability to check.
	 *
	 * @return Response
	 */
	public function handle( Request $request, Closure $next, string $ability ): Response
	{
		if ( Gate::has( $ability ) ) {
			if ( ! $request->user() || ! $request->user()->can( $ability ) ) {
				abort( 403 );
			}
		}

		return $next( $request );
	}
}
