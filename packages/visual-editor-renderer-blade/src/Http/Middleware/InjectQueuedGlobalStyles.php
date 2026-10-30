<?php

/**
 * Safety net for the head placement of global styles (#879).
 *
 * In an `@extends` layout or a component layout the blocks render before the
 * layout's `<head>`, so `<x-ve-blocks>` / `<x-ve-template>` queue the global
 * styles on the `ve-global-styles` Blade stack for `<x-ve-blocks-styles>` to
 * print. A layout that has neither that component nor its own
 * `@stack('ve-global-styles')` would never print them. This middleware,
 * pushed onto the `web` group, delivers them on the way out: in `<head>`,
 * before its first stylesheet (`<link rel="stylesheet">` or `<style>`) so
 * the theme's own CSS still wins as it does in the editor canvas, else just
 * before `</head>`; without a `</head>`, right after the opening `<body…>`
 * tag; otherwise at the end.
 *
 * It only touches HTML responses: streamed, binary-file, JSON, redirect,
 * encoded (`Content-Encoding`) and non-`text/html` responses pass through
 * untouched. It never prints the styles twice: it does nothing once the
 * stack has been printed by `<x-ve-blocks-styles>`, nor when the body
 * already contains the queued tag (a host-printed `@stack`). Its state lives
 * on the scoped {@see GlobalStylesPlacement}, so long-lived workers (Octane)
 * start every request clean.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Http\Middleware;

use ArtisanPackUI\VisualEditorRendererBlade\Http\Middleware\Concerns\RewritesHtmlResponses;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesPlacement;
use Closure;
use Illuminate\Http\Request;

class InjectQueuedGlobalStyles
{
	use RewritesHtmlResponses;

	/**
	 * Deliver queued global styles the layout never printed.
	 *
	 * @since 1.15.0
	 *
	 * @param  Request                      $request  Incoming request.
	 * @param  Closure(Request): mixed      $next     Next middleware.
	 *
	 * @return mixed The (possibly amended) response.
	 */
	public function handle( Request $request, Closure $next ): mixed
	{
		$response = $next( $request );

		if ( ! app()->bound( GlobalStylesPlacement::class ) ) {
			return $response;
		}

		$placement = app( GlobalStylesPlacement::class );
		$tag       = $placement->pendingTag();

		if ( null === $tag || ! $this->isInjectable( $response ) ) {
			return $response;
		}

		$content = $response->getContent();

		if ( ! is_string( $content ) || '' === $content ) {
			return $response;
		}

		$placement->markDelivered();

		if ( str_contains( $content, $tag ) ) {
			return $response;
		}

		$response->setContent( self::inject( $content, $tag ) );

		if ( $response->headers->has( 'Content-Length' ) ) {
			$response->headers->set( 'Content-Length', (string) strlen( (string) $response->getContent() ) );
		}

		return $response;
	}

	/**
	 * Insert `$tag` into the head before its first stylesheet, else before
	 * the first `</head>`; without one, after the first opening `<body…>`
	 * tag; otherwise at the end of `$html`.
	 *
	 * @since 1.15.0
	 *
	 * @param  string  $html  Response body.
	 * @param  string  $tag   Markup to insert.
	 */
	public static function inject( string $html, string $tag ): string
	{
		if ( 1 === preg_match( '/<\/head\s*>/i', $html, $match, PREG_OFFSET_CAPTURE ) ) {
			$offset = (int) $match[0][1];
			$head   = substr( $html, 0, $offset );

			// The first stylesheet in the head, skipping comments.
			$scan = (string) preg_replace_callback( '/<!--.*?-->/s', static fn ( array $comment ): string => str_repeat( ' ', strlen( $comment[0] ) ), $head );

			if ( 1 === preg_match( '/<style\b|<link\b(?=(?:[^>"\']|"[^"]*"|\'[^\']*\')*\brel\s*=\s*(["\']?)[^"\'>]*\bstylesheet\b)/i', $scan, $sheet, PREG_OFFSET_CAPTURE ) ) {
				$offset = (int) $sheet[0][1];
			}

			return substr( $html, 0, $offset ) . $tag . substr( $html, $offset );
		}

		if ( 1 === preg_match( '/<body(?:\s(?:[^>"\']|"[^"]*"|\'[^\']*\')*)?>/i', $html, $match, PREG_OFFSET_CAPTURE ) ) {
			$offset = (int) $match[0][1] + strlen( $match[0][0] );

			return substr( $html, 0, $offset ) . $tag . substr( $html, $offset );
		}

		return $html . $tag;
	}
}
