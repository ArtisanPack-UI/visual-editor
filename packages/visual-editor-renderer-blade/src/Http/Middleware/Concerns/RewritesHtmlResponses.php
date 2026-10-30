<?php

/**
 * Shared guard for middleware that rewrites a finished HTML document.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.19.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Http\Middleware\Concerns;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait RewritesHtmlResponses
{
	/**
	 * Whether the response is a buffered HTML document this middleware may
	 * rewrite.
	 *
	 * @since 1.15.0
	 * @since 1.19.0 Moved here from {@see \ArtisanPackUI\VisualEditorRendererBlade\Http\Middleware\InjectQueuedGlobalStyles}.
	 *
	 * @param  mixed  $response  Response from the pipeline.
	 */
	protected function isInjectable( mixed $response ): bool
	{
		if ( ! $response instanceof Response
			|| $response instanceof StreamedResponse
			|| $response instanceof BinaryFileResponse
			|| $response instanceof JsonResponse
			|| $response->isRedirection()
			|| $response->isInformational()
			|| $response->headers->has( 'Content-Encoding' )
		) {
			return false;
		}

		$type = strtolower( (string) $response->headers->get( 'Content-Type', '' ) );

		return '' === $type || str_contains( $type, 'text/html' );
	}
}
