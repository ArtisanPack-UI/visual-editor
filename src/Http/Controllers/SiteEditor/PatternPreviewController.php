<?php

/**
 * Pattern preview controller — #832.
 *
 * Renders a batch of site-editor patterns to front-end HTML for the scaled
 * previews on pattern cards (inserter Patterns panel, Site Editor pattern
 * grid, page pattern modal). The client gathers the cards that scroll into
 * view and sends their ids in one request:
 *
 *     POST /visual-editor/api/patterns/preview
 *     { "patterns": [ "about-full", "12" ] }
 *
 * and gets back the shared stylesheet markup plus one entry per pattern:
 *
 *     {
 *       "styles": "<link …><style …>…</style>",
 *       "patterns": {
 *         "about-full": { "html": "<section …>…</section>" },
 *         "12":         { "error": "not_found" }
 *       }
 *     }
 *
 * A pattern that can't be found or fails to render reports a per-pattern
 * error, so its card keeps the block-name fallback while the rest of the
 * batch still renders. Rendered HTML is cached by
 * {@see PatternPreviewCache}.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.13.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers\SiteEditor;

use ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor\PatternPreviewRequest;
use ArtisanPackUI\VisualEditor\SiteEditor\Previews\PatternPreviewRenderer;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\PatternResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedPattern;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Throwable;

class PatternPreviewController extends Controller
{
	/**
	 * Storage prefix cms-framework applies to user-source slugs.
	 */
	protected const USER_SLUG_PREFIX = 'user/';

	/**
	 * POST `/visual-editor/api/patterns/preview` — render a batch of patterns.
	 *
	 * @since 1.13.0
	 */
	public function preview( PatternPreviewRequest $request ): JsonResponse
	{
		/** @var array<int, string> $ids */
		$ids = $request->validated( 'patterns' );

		// Resolved per request: the renderer memoizes the active theme, and
		// the router reuses controller instances on long-lived workers.
		$renderer  = app( PatternPreviewRenderer::class );
		$available = $renderer->available();
		$results   = [];

		foreach ( $ids as $id ) {
			$results[ $id ] = $available
				? $this->renderOne( $renderer, $id )
				: [ 'error' => 'renderer_unavailable' ];
		}

		return response()->json( [
			'styles'   => $available ? $this->sharedStyles( $renderer ) : '',
			// Force an object so a single numeric id doesn't serialize as a list.
			'patterns' => (object) $results,
		] );
	}

	/**
	 * The batch's shared stylesheet markup. A failure here (a bad theme
	 * manifest, unmigrated fonts) is reported and degrades to no shared
	 * styles, so it can't turn the whole batch into a 500.
	 *
	 * @since 1.13.0
	 */
	protected function sharedStyles( PatternPreviewRenderer $renderer ): string
	{
		try {
			return $renderer->styles();
		} catch ( Throwable $e ) {
			report( $e );

			return '';
		}
	}

	/**
	 * Render one pattern, mapping lookup and render failures to a
	 * per-pattern error.
	 *
	 * @since 1.13.0
	 *
	 * @return array{html: string}|array{error: string}
	 */
	protected function renderOne( PatternPreviewRenderer $renderer, string $id ): array
	{
		$pattern = $this->findPattern( $id );

		if ( ! $pattern instanceof ResolvedPattern ) {
			return [ 'error' => 'not_found' ];
		}

		try {
			return [ 'html' => $renderer->render( $pattern ) ];
		} catch ( Throwable $e ) {
			report( $e );

			return [ 'error' => 'render_failed' ];
		}
	}

	/**
	 * Resolve a pattern by the id the pattern API hands the client: a
	 * numeric DB id for user patterns, the slug for theme patterns. Mirrors
	 * {@see PatternController::findPatternByIdOrSlug()}.
	 *
	 * @since 1.13.0
	 */
	protected function findPattern( string $id ): ?ResolvedPattern
	{
		// Resolved per call rather than injected: pattern writes swap the
		// resolver singleton ({@see PatternController::refreshResolver()}),
		// and a long-lived controller instance would keep the stale one.
		$resolver = app( PatternResolver::class );

		if ( ctype_digit( $id ) && (int) $id > 0 ) {
			foreach ( $resolver->all() as $candidate ) {
				if ( $candidate instanceof ResolvedPattern && $candidate->wpId === (int) $id ) {
					return $candidate;
				}
			}

			return null;
		}

		$resolved = $resolver->find( $id );

		if ( ! $resolved instanceof ResolvedPattern && ! str_starts_with( $id, self::USER_SLUG_PREFIX ) ) {
			$resolved = $resolver->find( self::USER_SLUG_PREFIX . $id );
		}

		return $resolved instanceof ResolvedPattern ? $resolved : null;
	}
}
