<?php

/**
 * Navigation controller.
 *
 * Serves the REST surface for the `wp_navigation` entity behind the B1
 * core-data shim (see `docs/core-data-shim.md` §Navigation). The five
 * endpoints — index, show, store, update, destroy — mount under
 * `/visual-editor/api/navigation` via the package's auth-gated API
 * group and return responses shaped by {@see NavigationResource}.
 *
 * Menu-location resolution is handled separately by
 * {@see \ArtisanPackUI\VisualEditor\Services\MenuLocationResolver};
 * V1 does not expose a REST surface for locations (reads go through
 * the service, writes are config-only per §8 of the V1 plan doc).
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

use ArtisanPackUI\VisualEditor\Http\Requests\StoreNavigationRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\UpdateNavigationRequest;
use ArtisanPackUI\VisualEditor\Http\Resources\NavigationResource;
use ArtisanPackUI\VisualEditor\Models\VisualEditorNavigation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class NavigationController extends Controller
{
	/**
	 * Maximum per-page size for the index endpoint. Keeps a malicious or
	 * accidental `?per_page=999999` query from dragging the editor's
	 * nav-picker dropdown down while still letting the host page through
	 * explicit requests when necessary.
	 */
	protected const MAX_PER_PAGE = 100;

	/**
	 * Lists navigation records with a paginated `{ data, meta }` envelope.
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
		Gate::authorize( 'viewAny', VisualEditorNavigation::class );

		$perPage = (int) $request->integer( 'per_page', 25 );

		if ( $perPage < 1 ) {
			$perPage = 25;
		}

		if ( $perPage > self::MAX_PER_PAGE ) {
			$perPage = self::MAX_PER_PAGE;
		}

		$query = VisualEditorNavigation::query()
			->orderBy( 'menu_order' )
			->orderBy( 'id' );

		$slug = $request->string( 'slug' )->toString();
		if ( '' !== $slug ) {
			$query->where( 'slug', $slug );
		}

		$status = $request->string( 'status' )->toString();
		if ( '' !== $status ) {
			$query->where( 'status', $status );
		}

		return NavigationResource::collection( $query->paginate( $perPage ) );
	}

	/**
	 * Returns a single navigation record.
	 *
	 * The shim expects the record at the top level (not wrapped in `data`)
	 * so `fetchEntityRecord` can dispatch it straight into the cache.
	 *
	 * @since 1.0.0
	 */
	public function show( Request $request, VisualEditorNavigation $navigation ): JsonResponse
	{
		Gate::authorize( 'view', $navigation );

		return response()->json( ( new NavigationResource( $navigation ) )->toArray( $request ) );
	}

	/**
	 * Creates a new navigation record.
	 *
	 * @since 1.0.0
	 */
	public function store( StoreNavigationRequest $request ): JsonResponse
	{
		Gate::authorize( 'create', VisualEditorNavigation::class );

		$data = $request->validated();

		$navigation = new VisualEditorNavigation();
		$navigation->fill( [
			'slug'       => $data['slug'],
			'title'      => $data['title'] ?? '',
			'status'     => $data['status'] ?? VisualEditorNavigation::STATUS_PUBLISH,
			'menu_order' => $data['menu_order'] ?? 0,
		] );

		$navigation->setContentEnvelope( $this->normalizeContentEnvelope( $data['content'] ?? null ) );
		$navigation->save();

		return response()->json(
			( new NavigationResource( $navigation ) )->toArray( $request ),
			Response::HTTP_CREATED
		);
	}

	/**
	 * Updates an existing navigation record.
	 *
	 * @since 1.0.0
	 */
	public function update( UpdateNavigationRequest $request, VisualEditorNavigation $navigation ): JsonResponse
	{
		Gate::authorize( 'update', $navigation );

		$data = $request->validated();

		foreach ( [ 'slug', 'title', 'status', 'menu_order' ] as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$navigation->{$field} = $data[ $field ];
			}
		}

		if ( array_key_exists( 'content', $data ) ) {
			$navigation->setContentEnvelope( $this->normalizeContentEnvelope( $data['content'] ) );
		}

		$navigation->save();

		return response()->json( ( new NavigationResource( $navigation ) )->toArray( $request ) );
	}

	/**
	 * Deletes a navigation record.
	 *
	 * @since 1.0.0
	 */
	public function destroy( VisualEditorNavigation $navigation ): JsonResponse
	{
		Gate::authorize( 'delete', $navigation );

		$navigation->delete();

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
