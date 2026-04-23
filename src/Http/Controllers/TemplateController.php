<?php

/**
 * Template controller.
 *
 * Serves the REST surface for the `wp_template` entity behind the B1
 * core-data shim (see `docs/core-data-shim.md` §Templates). The five
 * endpoints — index, show, store, update, destroy — mount under
 * `/visual-editor/api/templates` via the package's auth-gated API
 * group and return responses shaped by {@see TemplateResource}.
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

use ArtisanPackUI\VisualEditor\Http\Requests\StoreTemplateRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\UpdateTemplateRequest;
use ArtisanPackUI\VisualEditor\Http\Resources\TemplateResource;
use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class TemplateController extends Controller
{
	/**
	 * Maximum per-page size for the index endpoint. Keeps a malicious or
	 * accidental `?per_page=999999` query from dragging the site-editor
	 * sidebar down while still letting the host page through explicit
	 * requests when necessary.
	 */
	protected const MAX_PER_PAGE = 100;

	/**
	 * Lists templates with a paginated `{ data, meta }` envelope.
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
		Gate::authorize( 'viewAny', VisualEditorTemplate::class );

		$perPage = (int) $request->integer( 'per_page', 25 );

		if ( $perPage < 1 ) {
			$perPage = 25;
		}

		if ( $perPage > self::MAX_PER_PAGE ) {
			$perPage = self::MAX_PER_PAGE;
		}

		$query = VisualEditorTemplate::query()->orderBy( 'id' );

		$theme = $request->string( 'theme' )->toString();
		if ( '' !== $theme ) {
			$query->where( 'theme', $theme );
		}

		$slug = $request->string( 'slug' )->toString();
		if ( '' !== $slug ) {
			$query->where( 'slug', $slug );
		}

		$status = $request->string( 'status' )->toString();
		if ( '' !== $status ) {
			$query->where( 'status', $status );
		}

		return TemplateResource::collection( $query->paginate( $perPage ) );
	}

	/**
	 * Returns a single template.
	 *
	 * The shim expects the record at the top level (not wrapped in `data`)
	 * so `fetchEntityRecord` can dispatch it straight into the cache.
	 *
	 * @since 1.0.0
	 */
	public function show( Request $request, VisualEditorTemplate $template ): JsonResponse
	{
		Gate::authorize( 'view', $template );

		return response()->json( ( new TemplateResource( $template ) )->toArray( $request ) );
	}

	/**
	 * Creates a new template.
	 *
	 * @since 1.0.0
	 */
	public function store( StoreTemplateRequest $request ): JsonResponse
	{
		Gate::authorize( 'create', VisualEditorTemplate::class );

		$data = $request->validated();

		$template = new VisualEditorTemplate();
		$template->fill( [
			'slug'        => $data['slug'],
			'title'       => $data['title'] ?? '',
			'description' => $data['description'] ?? null,
			'status'      => $data['status'] ?? VisualEditorTemplate::STATUS_PUBLISH,
			'theme'       => $data['theme'],
			'source'      => $data['source'] ?? VisualEditorTemplate::SOURCE_CUSTOM,
			'origin'      => $data['origin'] ?? null,
		] );

		$template->setContentEnvelope( $this->normalizeContentEnvelope( $data['content'] ?? null ) );
		$template->save();

		return response()->json(
			( new TemplateResource( $template ) )->toArray( $request ),
			Response::HTTP_CREATED
		);
	}

	/**
	 * Updates an existing template.
	 *
	 * @since 1.0.0
	 */
	public function update( UpdateTemplateRequest $request, VisualEditorTemplate $template ): JsonResponse
	{
		Gate::authorize( 'update', $template );

		$data = $request->validated();

		foreach ( [ 'slug', 'title', 'description', 'status', 'theme', 'source', 'origin' ] as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$template->{$field} = $data[ $field ];
			}
		}

		if ( array_key_exists( 'content', $data ) ) {
			$template->setContentEnvelope( $this->normalizeContentEnvelope( $data['content'] ) );
		}

		$template->save();

		return response()->json( ( new TemplateResource( $template ) )->toArray( $request ) );
	}

	/**
	 * Deletes a template.
	 *
	 * @since 1.0.0
	 */
	public function destroy( VisualEditorTemplate $template ): JsonResponse
	{
		Gate::authorize( 'delete', $template );

		$template->delete();

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
}
