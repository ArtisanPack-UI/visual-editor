<?php

/**
 * ResourceAppliedTemplate controller.
 *
 * Serves GET for the *applied* template of a content item (issue #619). The
 * post-editor's composed-view mode fetches this to wrap the raw content block
 * list in the same template blocks + template-parts that render on the
 * frontend, without a server-side render round-trip.
 *
 * The applied template is derived from the model's `template` attribute
 * (cms-framework-owned on Page/Post; opt-in on custom `HasBlockContent`
 * models). Resolution runs through the shared H5 {@see TemplateResolver} so
 * source-of-truth (theme file vs DB override) stays consistent with the
 * site-editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers;

use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplate;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplatePart;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplatePartResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplateResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ResourceAppliedTemplateController extends Controller
{
	public function __construct(
		protected ResourceResolver $resources,
		protected TemplateResolver $templates,
		protected TemplatePartResolver $templateParts,
	) {
	}

	/**
	 * GET `{resource}/{id}/applied-template` — resolve the applied template
	 * for a content item.
	 *
	 * Response envelope on hit:
	 *   {
	 *     slug: string,
	 *     name: string,
	 *     source: 'db' | 'theme',
	 *     blocks: array,
	 *     template_parts: array<string, {slug, area, blocks, ...}>,
	 *   }
	 *
	 * When the model's `template` attribute is empty *or* the referenced slug
	 * cannot be resolved, returns 200 with a discriminated body the client
	 * uses to fall back to the default template:
	 *   { status: 'missing', reason: 'empty' | 'unknown-slug', slug?: string }
	 *
	 * A 200 status (rather than 404) keeps the browser devtools quiet on
	 * every editor mount — the "missing" state is the endpoint's normal,
	 * routine response for any content that hasn't chosen a template.
	 *
	 * @since 1.1.0
	 */
	public function show( string $resource, int|string $id ): JsonResponse
	{
		$model = $this->resources->resolve( $resource, $id );

		Gate::authorize( 'view', $model );

		$slug = $this->readTemplateSlug( $model );

		if ( '' === $slug ) {
			return response()->json( [ 'status' => 'missing', 'reason' => 'empty' ] );
		}

		$resolved = $this->templates->find( $slug );

		if ( ! $resolved instanceof ResolvedTemplate ) {
			return response()->json( [
				'status' => 'missing',
				'reason' => 'unknown-slug',
				'slug'   => $slug,
			] );
		}

		return response()->json( [
			'status'         => 'ok',
			'slug'           => $resolved->slug,
			'name'           => $resolved->title,
			'source'         => $resolved->source,
			'blocks'         => $resolved->blocks,
			'template_parts' => $this->collectReferencedParts( $resolved->blocks ),
		] );
	}

	/**
	 * Reads the content model's `template` attribute, treating missing /
	 * non-scalar / whitespace-only values as empty.
	 *
	 * @since 1.1.0
	 */
	protected function readTemplateSlug( Model $model ): string
	{
		$value = $model->getAttribute( 'template' );

		if ( ! is_string( $value ) ) {
			return '';
		}

		return trim( $value );
	}

	/**
	 * Walk a block tree collecting every `core/template-part` slug reachable
	 * from it (including through nested parts) and return their resolved
	 * envelopes keyed by slug. Unresolved refs are skipped silently — the
	 * client renders the empty part shell in that case, matching the
	 * site-editor's read-only behavior.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, array<string, mixed>>  $blocks
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function collectReferencedParts( array $blocks ): array
	{
		$queue   = $this->extractPartSlugs( $blocks );
		$out     = [];
		$visited = [];

		while ( [] !== $queue ) {
			$slug = array_shift( $queue );

			if ( isset( $visited[ $slug ] ) ) {
				continue;
			}

			$visited[ $slug ] = true;

			$part = $this->templateParts->find( $slug );

			if ( ! $part instanceof ResolvedTemplatePart ) {
				continue;
			}

			$out[ $slug ] = [
				'slug'   => $part->slug,
				'area'   => $part->area,
				'title'  => $part->title,
				'source' => $part->source,
				'blocks' => $part->blocks,
			];

			// Recurse into nested part refs.
			foreach ( $this->extractPartSlugs( $part->blocks ) as $nested ) {
				if ( ! isset( $visited[ $nested ] ) ) {
					$queue[] = $nested;
				}
			}
		}

		return $out;
	}

	/**
	 * Depth-first walk collecting `attributes.slug` values from every
	 * `core/template-part` block in a tree. Ignores refs without a slug
	 * (WP allows an `id`-based ref, but this repo's templates are
	 * slug-addressed — an id-only ref is a shape the site editor never
	 * emits).
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, array<string, mixed>>  $blocks
	 *
	 * @return array<int, string>
	 */
	protected function extractPartSlugs( array $blocks ): array
	{
		$slugs = [];

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( ( $block['name'] ?? null ) === 'core/template-part' ) {
				$slug = $block['attributes']['slug'] ?? null;

				if ( is_string( $slug ) && '' !== trim( $slug ) ) {
					$slugs[] = trim( $slug );
				}
			}

			$inner = $block['innerBlocks'] ?? null;

			if ( is_array( $inner ) && [] !== $inner ) {
				foreach ( $this->extractPartSlugs( $inner ) as $nested ) {
					$slugs[] = $nested;
				}
			}
		}

		return $slugs;
	}
}
