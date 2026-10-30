<?php

/**
 * Trims the stylesheets `<x-ve-blocks-styles />` printed from a finished page
 * (#1032). See {@see FrontendStylesOptimizer} for what it removes and why it
 * works on the response rather than during the render.
 *
 * Added to the `web` group ahead of {@see InjectQueuedGlobalStyles}, so it
 * sees the document after that middleware has delivered any queued global
 * styles. Anything that bypasses it (mail, `Blade::render()` into a string, a
 * JSON or streamed response) keeps the component's full output.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.19.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Http\Middleware;

use ArtisanPackUI\VisualEditorRendererBlade\Http\Middleware\Concerns\RewritesHtmlResponses;
use ArtisanPackUI\VisualEditorRendererBlade\Services\FrontendStylesOptimizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OptimizeBlockStyles
{
	use RewritesHtmlResponses;

	/**
	 * Trim the document's block stylesheets.
	 *
	 * @since 1.19.0
	 *
	 * @param  Request                  $request  Incoming request.
	 * @param  Closure(Request): mixed  $next     Next middleware.
	 *
	 * @return mixed The (possibly amended) response.
	 */
	public function handle( Request $request, Closure $next ): mixed
	{
		$response = $next( $request );

		if ( ! $this->isInjectable( $response ) ) {
			return $response;
		}

		/** @var Response $response */
		$content = $response->getContent();

		if ( ! is_string( $content ) || ! str_contains( $content, 'data-ve-' ) ) {
			return $response;
		}

		$optimized = app( FrontendStylesOptimizer::class )->optimize( $content );

		if ( $optimized === $content ) {
			return $response;
		}

		$response->setContent( $optimized );

		if ( $response->headers->has( 'Content-Length' ) ) {
			$response->headers->set( 'Content-Length', (string) strlen( $optimized ) );
		}

		return $response;
	}
}
