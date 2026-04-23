<?php

/**
 * TemplateResolver service.
 *
 * Walks the WordPress-style template hierarchy (`single-{slug}` →
 * `single` → `index`, `page-{slug}` → `page` → `index`, or an exact
 * slug match → `index`) and returns the first `VisualEditorTemplate`
 * record that matches. Callers treat a null result as the explicit
 * "render the 404 template" signal — the resolver never returns the
 * `404` record itself, because that's a rendering concern owned by the
 * front-end renderer (E2) and the site-editor preview (D2).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Resources;

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;

class TemplateResolver
{
	/**
	 * Resolves the most specific template for a given slug, walking the
	 * hierarchy fallback chain until a record matches.
	 *
	 * Example cascades:
	 *
	 *   forSlug( 'single-post' ) → single-post → single → index → null
	 *   forSlug( 'single' )      → single → index → null
	 *   forSlug( 'page-about' )  → page-about → page → index → null
	 *   forSlug( 'page' )        → page → index → null
	 *   forSlug( 'archive' )     → archive → index → null
	 *   forSlug( 'index' )       → index → null
	 *
	 * Returning null at the end of the chain leaves `404` as the caller's
	 * explicit fallback — see the service-level doc block for why.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $slug   The most-specific template slug to try first.
	 * @param  ?string $theme  Optional theme scope; when null, the query
	 *                         matches across themes (useful for host apps
	 *                         that only install one theme at a time).
	 */
	public function forSlug( string $slug, ?string $theme = null ): ?VisualEditorTemplate
	{
		foreach ( $this->fallbackChain( $slug ) as $candidate ) {
			$record = $this->findBySlug( $candidate, $theme );

			if ( null !== $record ) {
				return $record;
			}
		}

		return null;
	}

	/**
	 * Returns the ordered chain of slugs the resolver walks for a given
	 * input slug — exposed so downstream code (renderer, diagnostics) can
	 * introspect the hierarchy without re-running the query.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function fallbackChain( string $slug ): array
	{
		$chain = [ $slug ];

		// Specific `single-*` and `page-*` slugs fall back to the bare
		// `single` and `page` templates respectively. Other specific
		// slugs (`archive-*`, `author-*`, …) fall straight to `index`.
		if ( 'single' !== $slug && str_starts_with( $slug, 'single-' ) ) {
			$chain[] = 'single';
		} elseif ( 'page' !== $slug && str_starts_with( $slug, 'page-' ) ) {
			$chain[] = 'page';
		}

		if ( 'index' !== $slug ) {
			$chain[] = 'index';
		}

		return $chain;
	}

	/**
	 * Performs a single slug lookup, optionally scoped to a theme.
	 *
	 * @since 1.0.0
	 */
	protected function findBySlug( string $slug, ?string $theme ): ?VisualEditorTemplate
	{
		return VisualEditorTemplate::query()
			->forSlug( $slug, $theme )
			->first();
	}
}
