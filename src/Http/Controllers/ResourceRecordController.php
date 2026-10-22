<?php

/**
 * ResourceRecord controller.
 *
 * Serves the minimal `{ id, title, slug }` list and single-record
 * endpoints for any model registered in
 * `config('artisanpack.visual-editor.resources')` (or contributed through
 * the `ap.visualEditor.resources` filter). Backs record pickers such as
 * the single-content block's "Post" dropdown for content types that
 * have no dedicated adapter controller (#1003). `posts` and `pages`
 * keep their WP-shape adapters, which are registered first.
 *
 * Authorization mirrors {@see ResourceContentController}: every record
 * is checked against the model's `view` policy ability.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.16.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers;

use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use ArtisanPackUI\VisualEditor\Support\RecordIdList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ResourceRecordController extends Controller
{
	/**
	 * Hard cap on `per_page` and on the number of `include` ids.
	 */
	protected const MAX_PER_PAGE = 100;

	/**
	 * Page size when the request doesn't send `per_page`.
	 */
	protected const DEFAULT_PER_PAGE = 25;

	public function __construct( protected ResourceResolver $resolver )
	{
	}

	/**
	 * Lists records for a resource.
	 *
	 * Supports `search` (matched against the `title` column when the
	 * table has one), `per_page` (capped at {@see self::MAX_PER_PAGE}),
	 * and `include` (a comma-separated id list that limits the result to
	 * those records). Records the user may not `view` are left out.
	 *
	 * @since 1.16.0
	 */
	public function index( Request $request, string $resource ): JsonResponse
	{
		$query = $this->resolver->query( $resource );
		$model = $query->getModel();

		$include = RecordIdList::parse( $request->query( 'include' ), self::MAX_PER_PAGE );

		if ( [] !== $include ) {
			$query->whereIn( $model->getKeyName(), $include );
		}

		$search = trim( $request->string( 'search' )->toString() );

		if ( '' !== $search && $this->hasColumn( $model, 'title' ) ) {
			$query->where( 'title', 'like', '%' . $search . '%' );
		}

		$records = $query
			->orderBy( $model->getKeyName(), 'desc' )
			->limit( [] !== $include ? count( $include ) : $this->perPage( $request ) )
			->get()
			->filter( fn ( Model $record ): bool => Gate::allows( 'view', $record ) )
			->map( fn ( Model $record ): array => $this->transform( $record ) )
			->values()
			->all();

		return response()->json( [ 'data' => $records ] );
	}

	/**
	 * Returns a single record for a resource.
	 *
	 * @since 1.16.0
	 */
	public function show( string $resource, int|string $id ): JsonResponse
	{
		$model = $this->resolver->resolve( $resource, $id );

		Gate::authorize( 'view', $model );

		return response()->json( $this->transform( $model ) );
	}

	/**
	 * Shapes a record for the JSON response.
	 *
	 * @since 1.16.0
	 *
	 * @return array{id: mixed, title: string, slug: string|null}
	 */
	protected function transform( Model $model ): array
	{
		$title = $model->getAttribute( 'title' );
		$slug  = $model->getAttribute( 'slug' );

		return [
			'id'    => $model->getKey(),
			'title' => is_string( $title ) ? $title : '',
			'slug'  => is_string( $slug ) && '' !== $slug ? $slug : null,
		];
	}

	/**
	 * Resolves the requested page size within the allowed range.
	 *
	 * @since 1.16.0
	 */
	protected function perPage( Request $request ): int
	{
		$perPage = $request->integer( 'per_page', self::DEFAULT_PER_PAGE );

		if ( $perPage < 1 ) {
			return self::DEFAULT_PER_PAGE;
		}

		return min( $perPage, self::MAX_PER_PAGE );
	}

	/**
	 * Whether the model's table has the given column.
	 *
	 * @since 1.16.0
	 */
	protected function hasColumn( Model $model, string $column ): bool
	{
		return $model->getConnection()->getSchemaBuilder()->hasColumn( $model->getTable(), $column );
	}
}
