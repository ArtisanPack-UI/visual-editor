<?php

/**
 * Content-authoring API access middleware.
 *
 * Checks the {@see ContentAccess::ABILITY} gate in front of API routes
 * that serve post-editor authoring but aren't covered by a model policy,
 * such as the icon picker and the custom SVG sanitizer (#834). It runs
 * after the group's `auth` middleware, so a guest still gets a 401; an
 * authenticated user who fails the gate gets a JSON 403 whatever the
 * request's `Accept` header says.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.13.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Middleware;

use ArtisanPackUI\VisualEditor\Support\ContentAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureContentEditorAccess
{
	/**
	 * Allow the request through when the user passes the content-access
	 * gate; otherwise answer with a JSON 403.
	 *
	 * @since 1.13.0
	 *
	 * @param  Request  $request  The incoming API request.
	 * @param  Closure  $next     The next middleware in the pipeline.
	 *
	 * @return Response The downstream response, or the denial.
	 */
	public function handle( Request $request, Closure $next ): Response
	{
		if ( Gate::forUser( $request->user() )->allows( ContentAccess::ABILITY ) ) {
			return $next( $request );
		}

		return response()->json(
			[ 'message' => __( 'You are not allowed to edit content.' ) ],
			Response::HTTP_FORBIDDEN,
		);
	}
}
