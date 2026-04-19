<?php

/**
 * Resource resolver.
 *
 * Maps a URL-friendly resource slug to the Eloquent model that backs it.
 * Reads the `resources` map from `config('artisanpack.visual-editor.resources')`
 * and enforces that the resolved model uses the `HasBlockContent` trait.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Resources;

use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResourceResolver
{
	/**
	 * Resolves a resource slug + id pair to a concrete model instance.
	 *
	 * Throws a 404 if the slug is unknown or the record does not exist, and a
	 * RuntimeException if the configured model is missing the trait.
	 *
	 * @since 1.0.0
	 *
	 * @param  string      $resource  The resource slug from the URL.
	 * @param  int|string  $id        The model primary key.
	 *
	 * @throws NotFoundHttpException When the slug is unknown or the record is missing.
	 * @throws RuntimeException      When the configured model doesn't use HasBlockContent.
	 */
	public function resolve( string $resource, int|string $id ): Model
	{
		$modelClass = $this->modelClassFor( $resource );
		$model      = $this->newModel( $modelClass );

		try {
			return $model->newQuery()
				->forVisualEditor()
				->findOrFail( $id );
		} catch ( ModelNotFoundException $exception ) {
			throw new NotFoundHttpException(
				sprintf( 'No %s with id %s.', $resource, (string) $id ),
				$exception
			);
		}
	}

	/**
	 * Returns the model class bound to a resource slug.
	 *
	 * @since 1.0.0
	 *
	 * @throws NotFoundHttpException When the slug is not configured.
	 */
	public function modelClassFor( string $resource ): string
	{
		$map = $this->resourceMap();

		if ( ! isset( $map[ $resource ] ) ) {
			throw new NotFoundHttpException(
				sprintf( 'Unknown visual-editor resource "%s".', $resource )
			);
		}

		$modelClass = $map[ $resource ];

		if ( ! is_string( $modelClass ) || ! class_exists( $modelClass ) ) {
			throw new RuntimeException(
				sprintf( 'Visual-editor resource "%s" must point to a valid model class.', $resource )
			);
		}

		return $modelClass;
	}

	/**
	 * Instantiates the model and asserts the trait is present.
	 *
	 * @since 1.0.0
	 *
	 * @param  class-string<Model>  $modelClass
	 */
	protected function newModel( string $modelClass ): Model
	{
		$model = new $modelClass();

		if ( ! $model instanceof Model ) {
			throw new RuntimeException(
				sprintf( 'Visual-editor resource "%s" must extend Eloquent Model.', $modelClass )
			);
		}

		if ( ! in_array( HasBlockContent::class, $this->usedTraits( $model ), true ) ) {
			throw new RuntimeException(
				sprintf( 'Visual-editor resource "%s" must use the HasBlockContent trait.', $modelClass )
			);
		}

		return $model;
	}

	/**
	 * Returns the recursive list of traits used by a model.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function usedTraits( Model $model ): array
	{
		$traits = [];
		$class  = $model::class;

		do {
			$traits = array_merge( $traits, class_uses( $class ) ?: [] );
			$class  = get_parent_class( $class );
		} while ( false !== $class );

		return array_values( array_unique( $traits ) );
	}

	/**
	 * Returns the configured slug → model class map.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, class-string<Model>>
	 */
	protected function resourceMap(): array
	{
		$map = config( 'artisanpack.visual-editor.resources', [] );

		return is_array( $map ) ? $map : [];
	}
}
