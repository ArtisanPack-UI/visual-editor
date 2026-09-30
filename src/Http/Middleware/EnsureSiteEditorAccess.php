<?php

/**
 * Site-editor API access middleware.
 *
 * Runs the bound {@see SiteEditorAccessGate} — the same contract that
 * protects the `/visual-editor/site/{path?}` SPA shell — in front of the
 * site-editor data API routes that mutate site-wide records (templates,
 * template parts, menus, menu items, patterns, global styles). Without
 * it any authenticated user could write site-wide data through the JSON
 * API even though they could never reach the site-editor shell.
 *
 * The gate contract returns a page-oriented {@see Response} (an install
 * page, a 503 "not configured" view, a login redirect, …) on denial. An
 * API client can't do anything useful with an HTML page, so a denial is
 * normalized to a `403` JSON body — unless the gate itself already
 * answered with JSON, in which case its response is returned verbatim so
 * a host gate can shape its own API errors.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.12.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Middleware;

use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteEditorAccess
{
	/**
	 * Create the middleware.
	 *
	 * @since 1.12.0
	 *
	 * @param  SiteEditorAccessGate  $gate  The bound site-editor access gate.
	 */
	public function __construct( protected SiteEditorAccessGate $gate )
	{
	}

	/**
	 * Allow the request through when the gate allows it; otherwise answer
	 * with a JSON 403.
	 *
	 * @since 1.12.0
	 *
	 * @param  Request  $request  The incoming API request.
	 * @param  Closure  $next     The next middleware in the pipeline.
	 *
	 * @return Response The downstream response, or the denial.
	 */
	public function handle( Request $request, Closure $next ): Response
	{
		$denial = $this->gate->check( $request );

		if ( null === $denial ) {
			return $next( $request );
		}

		if ( $denial instanceof JsonResponse ) {
			return $denial;
		}

		return response()->json(
			[ 'message' => __( 'You are not allowed to manage site-editor data.' ) ],
			Response::HTTP_FORBIDDEN,
		);
	}
}
