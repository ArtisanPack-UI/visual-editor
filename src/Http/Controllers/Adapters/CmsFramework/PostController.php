<?php

/**
 * PostController — WP-shape REST surface for cms-framework's `Post`.
 *
 * Pinned to the `posts` slug; resolves the underlying model class
 * through {@see ResourceResolver} so cms-framework's
 * `Modules\Blog\Models\Post` (registered via the
 * `ap.visualEditor.resources` filter) — or a host-app override — is
 * served without this controller importing the class. That keeps the
 * package booting standalone.
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

use ArtisanPackUI\VisualEditor\Http\Requests\Adapters\CmsFramework\StorePostRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\Adapters\CmsFramework\UpdatePostRequest;
use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\PostResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PostController extends WpEntityController
{
	protected function slug(): string
	{
		return 'posts';
	}

	protected function resourceClass(): string
	{
		return PostResource::class;
	}

	/**
	 * Creates a new post.
	 *
	 * @since 1.0.0
	 */
	public function store( StorePostRequest $request ): JsonResponse
	{
		$model = $this->persistNew( $request->validated() );

		return $this->toResponse( $request, $model, Response::HTTP_CREATED );
	}

	/**
	 * Updates an existing post.
	 *
	 * @since 1.0.0
	 */
	public function update( UpdatePostRequest $request, int|string $id ): JsonResponse
	{
		$model = $this->persistUpdate( $id, $request->validated() );

		return $this->toResponse( $request, $model );
	}

	/**
	 * Stages taxonomy ids for the post-save sync. The base controller
	 * fills column attributes; categories/tags are BelongsToMany
	 * relations that need `sync()` after the model has a primary key.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data
	 */
	protected function fill( Model $model, array $data ): Model
	{
		$model = parent::fill( $model, $data );

		foreach ( [ 'categories', 'tags' ] as $relation ) {
			if ( ! array_key_exists( $relation, $data ) || ! is_array( $data[ $relation ] ) || ! method_exists( $model, $relation ) ) {
				continue;
			}

			// Buffer the ids until after save() — registering a
			// `saved` listener on the instance keeps the sync within
			// the request lifecycle without touching the host model.
			$ids = array_values( array_filter( $data[ $relation ], 'is_int' ) );

			$model->saved( static function ( Model $saved ) use ( $relation, $ids ): void {
				if ( method_exists( $saved->{$relation}(), 'sync' ) ) {
					$saved->{$relation}()->sync( $ids );
				}
			} );
		}

		return $model;
	}
}
