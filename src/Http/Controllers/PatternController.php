<?php

/**
 * Pattern controller.
 *
 * Serves the REST surface for the `wp_block` entity behind the B1
 * core-data shim (see `docs/core-data-shim.md` §Patterns). The five
 * endpoints — index, show, store, update, destroy — mount under
 * `/visual-editor/api/patterns` via the package's auth-gated API group
 * and return responses shaped by {@see PatternResource}.
 *
 * Categories are addressed by slug (`categories: ["featured"]`) on the
 * way in and out; any slug missing from the `visual_editor_pattern_categories`
 * lookup table is auto-created on first use, matching WordPress's UX
 * and the B2 fixture shape.
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

use ArtisanPackUI\VisualEditor\Http\Requests\StorePatternRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\UpdatePatternRequest;
use ArtisanPackUI\VisualEditor\Http\Resources\PatternResource;
use ArtisanPackUI\VisualEditor\Models\VisualEditorPattern;
use ArtisanPackUI\VisualEditor\Models\VisualEditorPatternCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class PatternController extends Controller
{
	/**
	 * Maximum per-page size for the index endpoint. Keeps a malicious or
	 * accidental `?per_page=999999` query from dragging the inserter
	 * down while still letting the host page through explicit requests
	 * when necessary.
	 */
	protected const MAX_PER_PAGE = 100;

	/**
	 * Lists patterns with a paginated `{ data, meta }` envelope.
	 *
	 * Supports filtering by slug, status, synced flag, and category slug
	 * (one or many, OR semantics — `?categories=featured` or
	 * `?categories[]=featured&categories[]=pricing`).
	 *
	 * @since 1.0.0
	 */
	public function index( Request $request ): AnonymousResourceCollection
	{
		Gate::authorize( 'viewAny', VisualEditorPattern::class );

		$perPage = (int) $request->integer( 'per_page', 25 );

		if ( $perPage < 1 ) {
			$perPage = 25;
		}

		if ( $perPage > self::MAX_PER_PAGE ) {
			$perPage = self::MAX_PER_PAGE;
		}

		$query = VisualEditorPattern::query()->with( 'categories' )->orderBy( 'id' );

		$slug = $request->string( 'slug' )->toString();
		if ( '' !== $slug ) {
			$query->where( 'slug', $slug );
		}

		$status = $request->string( 'status' )->toString();
		if ( '' !== $status ) {
			$query->where( 'status', $status );
		}

		if ( $request->has( 'synced' ) ) {
			$query->where( 'synced', $request->boolean( 'synced' ) );
		}

		$categories = $this->normalizeCategoryFilter( $request->input( 'categories' ) );
		if ( [] !== $categories ) {
			$query->withAnyCategory( $categories );
		}

		return PatternResource::collection( $query->paginate( $perPage ) );
	}

	/**
	 * Returns a single pattern.
	 *
	 * The shim expects the record at the top level (not wrapped in `data`)
	 * so `fetchEntityRecord` can dispatch it straight into the cache.
	 *
	 * @since 1.0.0
	 */
	public function show( Request $request, VisualEditorPattern $pattern ): JsonResponse
	{
		Gate::authorize( 'view', $pattern );

		$pattern->loadMissing( 'categories' );

		return response()->json( ( new PatternResource( $pattern ) )->toArray( $request ) );
	}

	/**
	 * Creates a new pattern.
	 *
	 * @since 1.0.0
	 */
	public function store( StorePatternRequest $request ): JsonResponse
	{
		Gate::authorize( 'create', VisualEditorPattern::class );

		$data = $request->validated();

		$pattern = new VisualEditorPattern();
		$pattern->fill( [
			'slug'   => $data['slug'],
			'title'  => $data['title'] ?? '',
			'synced' => (bool) ( $data['synced'] ?? false ),
			'status' => $data['status'] ?? VisualEditorPattern::STATUS_PUBLISH,
		] );

		$pattern->setContentEnvelope( $this->normalizeContentEnvelope( $data['content'] ?? null ) );
		$pattern->save();

		if ( array_key_exists( 'categories', $data ) ) {
			$this->syncCategories( $pattern, $data['categories'] );
		}

		$pattern->load( 'categories' );

		return response()->json(
			( new PatternResource( $pattern ) )->toArray( $request ),
			Response::HTTP_CREATED
		);
	}

	/**
	 * Updates an existing pattern.
	 *
	 * @since 1.0.0
	 */
	public function update( UpdatePatternRequest $request, VisualEditorPattern $pattern ): JsonResponse
	{
		Gate::authorize( 'update', $pattern );

		$data = $request->validated();

		foreach ( [ 'slug', 'title', 'status' ] as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$pattern->{$field} = $data[ $field ];
			}
		}

		if ( array_key_exists( 'synced', $data ) ) {
			$pattern->synced = (bool) $data['synced'];
		}

		if ( array_key_exists( 'content', $data ) ) {
			$pattern->setContentEnvelope( $this->normalizeContentEnvelope( $data['content'] ) );
		}

		$pattern->save();

		if ( array_key_exists( 'categories', $data ) ) {
			$this->syncCategories( $pattern, $data['categories'] );
		}

		$pattern->load( 'categories' );

		return response()->json( ( new PatternResource( $pattern ) )->toArray( $request ) );
	}

	/**
	 * Deletes a pattern. The pivot rows are removed by the cascading
	 * foreign key on `visual_editor_pattern_category`.
	 *
	 * @since 1.0.0
	 */
	public function destroy( VisualEditorPattern $pattern ): JsonResponse
	{
		Gate::authorize( 'delete', $pattern );

		$pattern->delete();

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
	 * Coerces the `?categories=` / `?categories[]=` query value into a
	 * flat list of slug strings, dropping anything that isn't a non-empty
	 * string.
	 *
	 * @since 1.0.0
	 *
	 * @param  mixed  $value
	 *
	 * @return array<int, string>
	 */
	protected function normalizeCategoryFilter( mixed $value ): array
	{
		if ( is_string( $value ) ) {
			$value = [ $value ];
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		return array_values( array_filter(
			$value,
			fn ( $slug ) => is_string( $slug ) && '' !== $slug
		) );
	}

	/**
	 * Syncs the pattern's categories from an array of slugs, auto-creating
	 * any slug that doesn't already exist in the lookup table.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, mixed>  $slugs
	 */
	protected function syncCategories( VisualEditorPattern $pattern, array $slugs ): void
	{
		$normalized = array_values( array_unique( array_filter(
			$slugs,
			fn ( $slug ) => is_string( $slug ) && '' !== $slug
		) ) );

		$ids = [];

		foreach ( $normalized as $slug ) {
			$category = VisualEditorPatternCategory::firstOrCreate(
				[ 'slug' => $slug ],
				[ 'name' => $slug ]
			);

			$ids[] = $category->getKey();
		}

		$pattern->categories()->sync( $ids );
	}
}
