<?php

/**
 * SiteController — exposes the editor-facing site-meta envelope at
 * `GET /visual-editor/api/site/{id}` for the `artisanpack/site-*` block
 * previews (#481).
 *
 * The site is a singleton: the `{id}` segment is required by the
 * core-data shim's URL builder (which always appends an id to a
 * single-record fetch) but the controller treats it as a sentinel
 * and always returns the same record. The response shape mirrors a
 * lightweight WP REST `wp/v2/` site envelope so the shim's
 * `useEntityRecord('root', '__unstableBase', '...')` reads through
 * the standard path without bespoke selectors.
 *
 * Returns a synthesized record when no source has populated the meta
 * (config + cms-framework settings both empty) — empty strings for
 * the text fields, null for the logo. The editor consumer (`createEntityPlaceholderEdit`)
 * treats empty values as "no value" and falls back to its placeholder
 * label, matching the front-end Blade renderer's behaviour on the
 * same empty input.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers;

use ArtisanPackUI\VisualEditor\Resources\SiteMetaResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class SiteController extends Controller
{
	public function __construct( protected SiteMetaResolver $resolver )
	{
	}

	/**
	 * Returns the singleton site-meta record.
	 *
	 * @since 1.0.0
	 */
	public function show( int|string $id ): JsonResponse
	{
		$meta = $this->resolver->resolve();

		return response()->json( $this->shape( $id, $meta ) );
	}

	/**
	 * Shape the resolver output into the WP-shape site record the
	 * editor's core-data shim consumes.
	 *
	 * `title` / `description` use the `{ raw, rendered }` shape so
	 * the shim's `flattenRawProperties()` selector path (which other
	 * post-type entities round-trip through) treats them the same way
	 * as a `wp_template` / `wp_block` title. `logo` is a media id or
	 * null, `logoUrl` is a flat URL string the site-logo preview can
	 * consume without a follow-up `attachment` fetch.
	 *
	 * @since 1.0.0
	 *
	 * @param  array{title: string, description: string, url: string, logoId: ?int, iconId: ?int, logoUrl: string}  $meta
	 *
	 * @return array<string, mixed>
	 */
	protected function shape( int|string $id, array $meta ): array
	{
		return [
			'id'          => $id,
			'title'       => [ 'raw' => $meta['title'], 'rendered' => $meta['title'] ],
			'description' => [ 'raw' => $meta['description'], 'rendered' => $meta['description'] ],
			'url'         => $meta['url'],
			'logo'        => $meta['logoId'],
			'icon'        => $meta['iconId'],
			'logoUrl'     => $meta['logoUrl'],
		];
	}
}
