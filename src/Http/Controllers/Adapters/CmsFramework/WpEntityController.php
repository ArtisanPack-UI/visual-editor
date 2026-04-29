<?php

/**
 * Abstract WP-shape entity controller.
 *
 * Generic CRUD scaffolding for any `HasBlockContent` Eloquent model
 * registered in `config('artisanpack.visual-editor.resources')` (or
 * contributed via the `ap.visual-editor.resources` filter from #397).
 * Handles `index`, `show`, `destroy`, and the shared persistence
 * helpers; concrete subclasses ({@see PostController},
 * {@see PageController}) own their typed `store`/`update` so each
 * request validates against its own form request class.
 *
 * Authorization runs through Laravel's policy layer
 * (`Gate::authorize($action, $model)`) so cms-framework's
 * `PostPolicy` / `PagePolicy` (or any host-defined policy) keep
 * gating their models even though this controller never imports
 * either class.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers\Adapters\CmsFramework;

use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\WpEntityResource;
use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

abstract class WpEntityController extends Controller
{
	/**
	 * Hard cap on the index `per_page` parameter. Keeps a malicious or
	 * accidental `?per_page=999999` from dragging the editor sidebar
	 * down while still letting the host page through explicit requests
	 * within the cap.
	 */
	protected const MAX_PER_PAGE = 100;

	public function __construct( protected ResourceResolver $resolver )
	{
	}

	/**
	 * The slug this controller serves — fixed to `posts` / `pages` on
	 * concrete subclasses. Used to look up the model class through
	 * `ResourceResolver` at request time.
	 *
	 * @since 1.0.0
	 */
	abstract protected function slug(): string;

	/**
	 * FQCN of the {@see WpEntityResource} subclass used to shape
	 * responses for this controller's entity.
	 *
	 * @since 1.0.0
	 *
	 * @return class-string<WpEntityResource>
	 */
	abstract protected function resourceClass(): string;

	/**
	 * Lists records with a paginated `{ data, meta, links }` envelope.
	 *
	 * @since 1.0.0
	 */
	public function index( Request $request ): AnonymousResourceCollection
	{
		$model = $this->newModel();

		Gate::authorize( 'viewAny', $model::class );

		$perPage = (int) $request->integer( 'per_page', 25 );

		if ( $perPage < 1 ) {
			$perPage = 25;
		}

		if ( $perPage > self::MAX_PER_PAGE ) {
			$perPage = self::MAX_PER_PAGE;
		}

		$query = $model->newQuery()->orderBy( $model->getKeyName(), 'desc' );

		$status = $request->string( 'status' )->toString();
		if ( '' !== $status ) {
			$query->where( 'status', $status );
		}

		$resourceClass = $this->resourceClass();

		return $resourceClass::collection( $query->paginate( $perPage ) );
	}

	/**
	 * Returns a single record at the top level of the response so
	 * `core-data`'s `fetchEntityRecord` can dispatch it straight into
	 * the cache.
	 *
	 * @since 1.0.0
	 */
	public function show( Request $request, int|string $id ): JsonResponse
	{
		$model = $this->resolver->resolve( $this->slug(), $id );

		Gate::authorize( 'view', $model );

		return $this->toResponse( $request, $model );
	}

	/**
	 * Deletes a record.
	 *
	 * @since 1.0.0
	 */
	public function destroy( int|string $id ): JsonResponse
	{
		$model = $this->resolver->resolve( $this->slug(), $id );

		Gate::authorize( 'delete', $model );

		$model->delete();

		return response()->json( null, Response::HTTP_NO_CONTENT );
	}

	/**
	 * Persists a new record from validated WP-shape data. Concrete
	 * controllers call this from their typed `store()` method.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data
	 */
	protected function persistNew( array $data ): Model
	{
		$model = $this->newModel();

		Gate::authorize( 'create', $model::class );

		$model = $this->fill( $model, $data );
		$model = $this->setBlockContent( $model, $data );
		$model->save();

		return $model;
	}

	/**
	 * Persists updates to an existing record from validated WP-shape
	 * data. Concrete controllers call this from their typed `update()`
	 * method.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data
	 */
	protected function persistUpdate( int|string $id, array $data ): Model
	{
		$model = $this->resolver->resolve( $this->slug(), $id );

		Gate::authorize( 'update', $model );

		$model = $this->fill( $model, $data );
		$model = $this->setBlockContent( $model, $data );
		$model->save();

		return $model;
	}

	/**
	 * Wraps a model in the controller's resource class and returns it
	 * as a JsonResponse. Concrete controllers call this from
	 * `store()`/`update()` to keep the response envelope consistent.
	 *
	 * @since 1.0.0
	 */
	protected function toResponse( Request $request, Model $model, int $status = Response::HTTP_OK ): JsonResponse
	{
		$resourceClass = $this->resourceClass();

		return response()->json(
			( new $resourceClass( $model ) )->toArray( $request ),
			$status
		);
	}

	/**
	 * Returns a fresh, unpersisted instance of the model class bound
	 * to this controller's slug.
	 *
	 * @since 1.0.0
	 */
	protected function newModel(): Model
	{
		$class = $this->resolver->modelClassFor( $this->slug() );

		return new $class();
	}

	/**
	 * Maps the validated WP-shape payload onto Eloquent attributes.
	 * Subclasses can override / extend to handle post-type-specific
	 * fields (categories/tags, parent/menu_order/template).
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data
	 */
	protected function fill( Model $model, array $data ): Model
	{
		foreach ( $this->fillableFields() as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$model->setAttribute( $field, $data[ $field ] );
			}
		}

		// `featured_media` is the WP-shape attribute name; cms-framework
		// stores it as `featured_image_id`. Pick whichever column the
		// host model actually has so the saved value round-trips.
		if ( array_key_exists( 'featured_media', $data ) ) {
			$model->setAttribute(
				$this->columnFor( $model, [ 'featured_media', 'featured_image_id' ] ),
				$data['featured_media']
			);
		}

		// Author flows through to the cms-framework `author_id` column.
		if ( array_key_exists( 'author', $data ) ) {
			$model->setAttribute( 'author_id', $data['author'] );
		}

		return $model;
	}

	/**
	 * Pulls the inbound block tree (under `content.blocks`) onto the
	 * model via the `HasBlockContent` trait helper. Inbound `content.raw`
	 * is intentionally discarded — the host renders block trees on demand
	 * through the matching renderer package, so storing a stale HTML
	 * mirror would only invite drift between the two.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data
	 */
	protected function setBlockContent( Model $model, array $data ): Model
	{
		if ( ! array_key_exists( 'content', $data ) || ! method_exists( $model, 'setBlockContent' ) ) {
			return $model;
		}

		$content = $data['content'];

		if ( ! is_array( $content ) ) {
			return $model;
		}

		$blocks = isset( $content['blocks'] ) && is_array( $content['blocks'] )
			? array_values( $content['blocks'] )
			: [];

		$model->setBlockContent( $blocks );

		return $model;
	}

	/**
	 * The base set of attributes to fill from the validated payload.
	 * Subclasses extend to add post-type-specific fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function fillableFields(): array
	{
		return [ 'title', 'slug', 'excerpt', 'status' ];
	}

	/**
	 * Returns the first column name from `$candidates` that the model
	 * actually has, falling back to the first candidate so a `setAttribute`
	 * call still succeeds (the model will silently store the value as a
	 * dynamic attribute when the column doesn't exist, which surfaces as a
	 * QueryException on save — useful as a debugging signal).
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, string>  $candidates
	 */
	protected function columnFor( Model $model, array $candidates ): string
	{
		foreach ( $candidates as $candidate ) {
			if ( in_array( $candidate, $model->getFillable(), true ) ) {
				return $candidate;
			}

			if ( array_key_exists( $candidate, $model->getCasts() ) ) {
				return $candidate;
			}

			if ( $model->exists && array_key_exists( $candidate, $model->getAttributes() ) ) {
				return $candidate;
			}
		}

		return $candidates[0] ?? '';
	}
}
