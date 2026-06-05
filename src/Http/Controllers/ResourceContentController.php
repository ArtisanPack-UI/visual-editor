<?php

/**
 * ResourceContent controller.
 *
 * Serves GET/PUT JSON endpoints for the block tree of any model registered in
 * `config('artisanpack.visual-editor.resources')`. Authorization is delegated
 * to the model's Laravel policy — this controller never checks ownership or
 * role on its own.
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

use ArtisanPackUI\VisualEditor\Http\Requests\UpdateResourceContentRequest;
use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ResourceContentController extends Controller
{
	public function __construct( protected ResourceResolver $resolver )
	{
	}

	/**
	 * Returns the block tree for the given resource.
	 *
	 * @since 1.0.0
	 */
	public function show( string $resource, int|string $id ): JsonResponse
	{
		$model = $this->resolver->resolve( $resource, $id );

		Gate::authorize( 'view', $model );

		return response()->json( $this->transform( $resource, $model ) );
	}

	/**
	 * Persists an updated block tree for the given resource.
	 *
	 * @since 1.0.0
	 */
	public function update(
		UpdateResourceContentRequest $request,
		string $resource,
		int|string $id
	): JsonResponse {
		$model = $this->resolver->resolve( $resource, $id );

		Gate::authorize( 'update', $model );

		/** @var array<int, array<string, mixed>> $blocks */
		$blocks = $request->validated( 'blocks' );

		$model->setBlockContent( $blocks );
		$model->save();

		return response()->json( $this->transform( $resource, $model ) );
	}

	/**
	 * Shapes a model for the JSON response.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function transform( string $resource, Model $model ): array
	{
		return [
			'id'         => $model->getKey(),
			'resource'   => $resource,
			'blocks'     => $model->getBlockContent(),
			'updated_at' => $model->updated_at?->toIso8601String(),
		];
	}
}
