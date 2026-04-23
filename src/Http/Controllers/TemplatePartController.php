<?php

/**
 * TemplatePart controller.
 *
 * Serves the REST surface for the `wp_template_part` entity behind the
 * B1 core-data shim (see `docs/core-data-shim.md` §Template parts). The
 * five endpoints — index, show, store, update, destroy — mount under
 * `/visual-editor/api/template-parts` via the package's auth-gated API
 * group and return responses shaped by {@see TemplatePartResource}.
 *
 * The `show` action additionally computes a `referenced_by` list —
 * template slugs whose serialized block tree embeds this part through a
 * `core/template-part` block — so the D2 site-editor can warn before
 * deleting a part that's still in use. The relationship is derived at
 * read time rather than persisted because parts and templates have no
 * FK between them: either side can outlive the other.
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

use ArtisanPackUI\VisualEditor\Http\Requests\StoreTemplatePartRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\UpdateTemplatePartRequest;
use ArtisanPackUI\VisualEditor\Http\Resources\TemplatePartResource;
use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;
use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplatePart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class TemplatePartController extends Controller
{
	/**
	 * Maximum per-page size for the index endpoint. Keeps a malicious or
	 * accidental `?per_page=999999` query from dragging the site-editor
	 * sidebar down while still letting the host page through explicit
	 * requests when necessary.
	 */
	protected const MAX_PER_PAGE = 100;

	/**
	 * Lists template parts with a paginated `{ data, meta }` envelope.
	 *
	 * The shim reads `meta.total` and `meta.last_page` for the selectors
	 * `getEntityRecordsTotalItems` / `getEntityRecordsTotalPages`; the
	 * default Laravel `Resource::collection( $paginator )` response
	 * already emits those keys, so no custom envelope is necessary.
	 *
	 * @since 1.0.0
	 */
	public function index( Request $request ): AnonymousResourceCollection
	{
		Gate::authorize( 'viewAny', VisualEditorTemplatePart::class );

		$perPage = (int) $request->integer( 'per_page', 25 );

		if ( $perPage < 1 ) {
			$perPage = 25;
		}

		if ( $perPage > self::MAX_PER_PAGE ) {
			$perPage = self::MAX_PER_PAGE;
		}

		$query = VisualEditorTemplatePart::query()->orderBy( 'id' );

		$theme = $request->string( 'theme' )->toString();
		if ( '' !== $theme ) {
			$query->where( 'theme', $theme );
		}

		$slug = $request->string( 'slug' )->toString();
		if ( '' !== $slug ) {
			$query->where( 'slug', $slug );
		}

		$area = $request->string( 'area' )->toString();
		if ( '' !== $area ) {
			$query->where( 'area', $area );
		}

		return TemplatePartResource::collection( $query->paginate( $perPage ) );
	}

	/**
	 * Returns a single template part.
	 *
	 * The shim expects the record at the top level (not wrapped in `data`)
	 * so `fetchEntityRecord` can dispatch it straight into the cache.
	 * `referenced_by` is merged in at the top level so the D2 delete
	 * flow can read it without a separate request.
	 *
	 * @since 1.0.0
	 */
	public function show( Request $request, VisualEditorTemplatePart $templatePart ): JsonResponse
	{
		Gate::authorize( 'view', $templatePart );

		$payload = ( new TemplatePartResource( $templatePart ) )->toArray( $request );

		$payload['referenced_by'] = $this->resolveReferencedBy( $templatePart );

		return response()->json( $payload );
	}

	/**
	 * Creates a new template part.
	 *
	 * @since 1.0.0
	 */
	public function store( StoreTemplatePartRequest $request ): JsonResponse
	{
		Gate::authorize( 'create', VisualEditorTemplatePart::class );

		$data = $request->validated();

		$part = new VisualEditorTemplatePart();
		$part->fill( [
			'slug'  => $data['slug'],
			'title' => $data['title'] ?? '',
			'area'  => $data['area'],
			'theme' => $data['theme'],
		] );

		$part->setContentEnvelope( $this->normalizeContentEnvelope( $data['content'] ?? null ) );
		$part->save();

		return response()->json(
			( new TemplatePartResource( $part ) )->toArray( $request ),
			Response::HTTP_CREATED
		);
	}

	/**
	 * Updates an existing template part.
	 *
	 * @since 1.0.0
	 */
	public function update( UpdateTemplatePartRequest $request, VisualEditorTemplatePart $templatePart ): JsonResponse
	{
		Gate::authorize( 'update', $templatePart );

		$data = $request->validated();

		foreach ( [ 'slug', 'title', 'area', 'theme' ] as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$templatePart->{$field} = $data[ $field ];
			}
		}

		if ( array_key_exists( 'content', $data ) ) {
			$templatePart->setContentEnvelope( $this->normalizeContentEnvelope( $data['content'] ) );
		}

		$templatePart->save();

		return response()->json( ( new TemplatePartResource( $templatePart ) )->toArray( $request ) );
	}

	/**
	 * Deletes a template part.
	 *
	 * @since 1.0.0
	 */
	public function destroy( VisualEditorTemplatePart $templatePart ): JsonResponse
	{
		Gate::authorize( 'delete', $templatePart );

		$templatePart->delete();

		return response()->json( null, Response::HTTP_NO_CONTENT );
	}

	/**
	 * Normalizes the inbound content payload into the `{ raw, blocks }`
	 * envelope the model expects. Form-request validation guarantees
	 * the shape before we get here; this method just guards against
	 * missing keys and bad types as a belt-and-braces fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed  $content
	 *
	 * @return array{raw: string, blocks: array<int, array<string, mixed>>}
	 */
	protected function normalizeContentEnvelope( mixed $content ): array
	{
		if ( ! is_array( $content ) ) {
			return [ 'raw' => '', 'blocks' => [] ];
		}

		return [
			'raw'    => isset( $content['raw'] ) && is_string( $content['raw'] ) ? $content['raw'] : '',
			'blocks' => isset( $content['blocks'] ) && is_array( $content['blocks'] ) ? array_values( $content['blocks'] ) : [],
		];
	}

	/**
	 * Resolves the list of template slugs whose block tree embeds this
	 * part via a `core/template-part` block.
	 *
	 * Correctness matters more than performance here: the V1 site-editor
	 * only calls this on a single part at a time (before showing a
	 * delete-confirmation dialog), so a linear scan over the templates
	 * for the matching theme is fine. If D2 grows to render the browser
	 * with per-part reference counts, this can move to a cached
	 * aggregate without changing the response contract.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function resolveReferencedBy( VisualEditorTemplatePart $part ): array
	{
		$slug  = $part->slug;
		$theme = $part->theme;

		// A part is only referenced from templates in the same theme —
		// `core/template-part` attributes carry the theme alongside the
		// slug, so the cross-theme case never resolves.
		$templates = VisualEditorTemplate::query()
			->where( 'theme', $theme )
			->orderBy( 'slug' )
			->get( [ 'slug', 'content' ] );

		$matches = [];

		foreach ( $templates as $template ) {
			if ( $this->blockTreeReferencesPart( $template->getBlocks(), $slug, $theme ) ) {
				$matches[] = $template->slug;
			}
		}

		// Deduplicate while preserving order — the scan may visit the
		// same template twice if the block tree embeds the part more
		// than once.
		return array_values( array_unique( $matches ) );
	}

	/**
	 * Recursively checks whether a parsed block tree contains a
	 * `core/template-part` pointer matching the given slug + theme.
	 *
	 * An empty/missing `theme` attribute on the block is treated as a
	 * same-theme reference. The B2 fixtures always set `theme` on the
	 * block, but older exports may omit it; since
	 * {@see resolveReferencedBy()} only scans templates already filtered
	 * to the target theme, an untagged reference inside one of those
	 * templates can only resolve to a part in that same theme.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<string, mixed>>  $blocks
	 */
	protected function blockTreeReferencesPart( array $blocks, string $slug, string $theme ): bool
	{
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';

			if ( 'core/template-part' === $name ) {
				$attributes     = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];
				$attributeSlug  = isset( $attributes['slug'] ) && is_string( $attributes['slug'] ) ? $attributes['slug'] : '';
				$attributeTheme = isset( $attributes['theme'] ) && is_string( $attributes['theme'] ) ? $attributes['theme'] : '';

				if ( $slug === $attributeSlug && ( '' === $attributeTheme || $theme === $attributeTheme ) ) {
					return true;
				}
			}

			$innerBlocks = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : [];

			if ( [] !== $innerBlocks && $this->blockTreeReferencesPart( $innerBlocks, $slug, $theme ) ) {
				return true;
			}
		}

		return false;
	}
}
