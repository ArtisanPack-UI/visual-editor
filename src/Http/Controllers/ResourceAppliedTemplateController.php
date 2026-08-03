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
use Illuminate\Http\Request;
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
	 * When the resolved slug is empty *or* the referenced slug cannot be
	 * resolved, returns 200 with a discriminated body the client uses to fall
	 * back to the default template:
	 *   { status: 'missing', reason: 'empty' | 'unknown-slug', slug?: string }
	 *
	 * A 200 status (rather than 404) keeps the browser devtools quiet on
	 * every editor mount — the "missing" state is the endpoint's normal,
	 * routine response for any content that hasn't chosen a template.
	 *
	 * An optional `?template=` query parameter overrides the slug stored on
	 * the model. The editor sends whatever the document panel currently has
	 * selected, which the debounced save may not have persisted yet; without
	 * the override a preview taken right after a template change resolves the
	 * *previous* template. Reading is gated on the same `view` authorization
	 * either way, and an unresolvable override falls through to the normal
	 * `unknown-slug` response.
	 *
	 * @since 1.1.0
	 */
	public function show( Request $request, string $resource, int|string $id ): JsonResponse
	{
		$model = $this->resources->resolve( $resource, $id );

		Gate::authorize( 'view', $model );

		$slug = $this->readRequestedSlug( $request ) ?? $this->readTemplateSlug( $model );

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

		$parts = $this->collectReferencedParts( $resolved->blocks );

		foreach ( $parts as $slug => $part ) {
			if ( is_array( $part ) && is_array( $part['blocks'] ?? null ) ) {
				$parts[ $slug ]['blocks'] = $this->rewriteCoreToFork( $part['blocks'] );
			}
		}

		return response()->json( [
			'status'         => 'ok',
			'slug'           => $resolved->slug,
			'name'           => $resolved->title,
			'source'         => $resolved->source,
			'blocks'         => $this->rewriteCoreToFork( $resolved->blocks ),
			'template_parts' => $parts,
		] );
	}

	/**
	 * Rewrite every `core/x` block name to its `artisanpack/x` fork.
	 *
	 * The I7 cutover (#415) replaced `registerCoreBlocks()` with
	 * `registerArtisanPackBlocks()`, so the editor only knows the
	 * `artisanpack/*` namespace. Theme templates on disk are authored in
	 * core markup (`wp:group`, `wp:heading`, `wp:template-part`), which
	 * means the resolved blocks would mount as unregistered types and the
	 * composed view would render nothing where the chrome belongs — the
	 * same class of failure as #674, one layer up.
	 *
	 * The mapping mirrors {@see \ArtisanPackUI\VisualEditor\Support\BlockMarkupHydrator::aliasFor()}.
	 * It is deliberately unconditional rather than gated on a registry
	 * lookup: {@see BlockTypeRegistry} only knows server-rendered PHP
	 * blocks, while most forks are JS-only, so gating would silently skip
	 * nearly every block. A core name with no fork ends up unregistered
	 * under either namespace, so the blanket rewrite costs nothing there.
	 *
	 * Rewriting `core/template-part` in particular hands part resolution
	 * to the fork's own edit component (#674/#675), which fetches the
	 * referenced part through the core-data shim.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, array<string, mixed>>  $blocks
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function rewriteCoreToFork( array $blocks ): array
	{
		$out = [];

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				$out[] = $block;
				continue;
			}

			$name = $block['name'] ?? null;

			if ( is_string( $name ) && str_starts_with( $name, 'core/' ) ) {
				$block['name'] = 'artisanpack/' . substr( $name, strlen( 'core/' ) );
			}

			if ( is_array( $block['innerBlocks'] ?? null ) && [] !== $block['innerBlocks'] ) {
				$block['innerBlocks'] = $this->rewriteCoreToFork( $block['innerBlocks'] );
			}

			$out[] = $block;
		}

		return $out;
	}

	/**
	 * Reads the optional `?template=` override off the request.
	 *
	 * Returns `null` when the parameter is absent or non-scalar so the caller
	 * can fall back to the model attribute. A present-but-blank value returns
	 * `''`, which is a deliberate "no template" instruction rather than a
	 * fallback — that's how the editor previews a content item whose template
	 * selection has been cleared but not yet saved.
	 *
	 * @since 1.1.0
	 */
	protected function readRequestedSlug( Request $request ): ?string
	{
		if ( ! $request->has( 'template' ) ) {
			return null;
		}

		$value = $request->query( 'template' );

		// A blank `?template=` reaches us as null because Laravel's default
		// `ConvertEmptyStringsToNull` middleware rewrites the query bag. The
		// parameter being present at all is the signal that matters, so a
		// null here means "explicitly no template", not "not supplied".
		if ( null === $value ) {
			return '';
		}

		if ( ! is_string( $value ) ) {
			return null;
		}

		return trim( $value );
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
