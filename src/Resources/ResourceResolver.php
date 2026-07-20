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
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResourceResolver
{
	/**
	 * @param  array<string, class-string<Model>>  $resources  Slug → model class map.
	 *                                                         Constructed in
	 *                                                         {@see \ArtisanPackUI\VisualEditor\VisualEditorServiceProvider}
	 *                                                         from
	 *                                                         `config('artisanpack.visual-editor.resources')`
	 *                                                         merged with the
	 *                                                         `ap.visualEditor.resources` filter result.
	 *                                                         The constructor performs no
	 *                                                         validation on the entries — invalid
	 *                                                         classes only surface on the first
	 *                                                         {@see self::resolve()} or
	 *                                                         {@see self::modelClassFor()} call so a
	 *                                                         filter contributor whose class isn't
	 *                                                         loaded (e.g. cms-framework standalone)
	 *                                                         doesn't trip boot.
	 */
	public function __construct( protected array $resources = [] )
	{
	}

	/**
	 * Resolves a resource slug + id pair to a concrete model instance.
	 *
	 * @since 1.0.0
	 *
	 * @param  string      $resource  The resource slug from the URL.
	 * @param  int|string  $id        The model primary key.
	 *
	 * @throws NotFoundHttpException    When the slug is unknown or the record is missing.
	 * @throws InvalidArgumentException When the configured model doesn't use HasBlockContent.
	 * @throws RuntimeException         When the configured class is missing or not an Eloquent model.
	 */
	public function resolve( string $resource, int|string $id ): Model
	{
		$modelClass = $this->modelClassFor( $resource );
		$model      = $this->newModel( $resource, $modelClass );

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
	 * @throws RuntimeException      When the slug points at a missing or non-string class.
	 */
	public function modelClassFor( string $resource ): string
	{
		if ( ! isset( $this->resources[ $resource ] ) ) {
			throw new NotFoundHttpException(
				sprintf( 'Unknown visual-editor resource "%s".', $resource )
			);
		}

		$modelClass = $this->resources[ $resource ];

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
	 * Validation happens here — on first resolve — rather than at boot so a
	 * filter contributor whose class isn't loaded (cms-framework installed
	 * without visual-editor's resources, for example) doesn't trip the
	 * service provider during application boot.
	 *
	 * @since 1.0.0
	 *
	 * @param  class-string<Model>  $modelClass
	 */
	protected function newModel( string $resource, string $modelClass ): Model
	{
		$model = new $modelClass();

		if ( ! $model instanceof Model ) {
			throw new RuntimeException(
				sprintf( 'Visual-editor resource "%s" must extend Eloquent Model.', $modelClass )
			);
		}

		if ( ! in_array( HasBlockContent::class, class_uses_recursive( $model::class ), true ) ) {
			throw new InvalidArgumentException( sprintf(
				'Resource [%s] resolves to [%s] which does not use HasBlockContent.',
				$resource,
				$modelClass
			) );
		}

		return $model;
	}
}
