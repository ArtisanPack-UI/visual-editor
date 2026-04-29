<?php

/**
 * PageController — WP-shape REST surface for cms-framework's `Page`.
 *
 * Pinned to the `pages` slug; resolves the underlying model class
 * through {@see ResourceResolver} so cms-framework's
 * `Modules\Pages\Models\Page` — or a host-app override — is served
 * without this controller importing the class.
 *
 * Layers page-only `parent` / `menu_order` / `template` write-through
 * onto the {@see WpEntityController} base.
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

use ArtisanPackUI\VisualEditor\Http\Requests\Adapters\CmsFramework\StorePageRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\Adapters\CmsFramework\UpdatePageRequest;
use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\PageResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PageController extends WpEntityController
{
	protected function slug(): string
	{
		return 'pages';
	}

	protected function resourceClass(): string
	{
		return PageResource::class;
	}

	public function store( StorePageRequest $request ): JsonResponse
	{
		$model = $this->persistNew( $request->validated() );

		return $this->toResponse( $request, $model, Response::HTTP_CREATED );
	}

	public function update( UpdatePageRequest $request, int|string $id ): JsonResponse
	{
		$model = $this->persistUpdate( $id, $request->validated() );

		return $this->toResponse( $request, $model );
	}

	/**
	 * Adds `parent`, `menu_order`, and `template` to the base fillable
	 * set so the WP-shape inbound names map onto the page columns.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $data
	 */
	protected function fill( Model $model, array $data ): Model
	{
		$model = parent::fill( $model, $data );

		// `parent` is the WP-shape name; cms-framework stores it as
		// `parent_id`. Pick whichever column the host model has.
		if ( array_key_exists( 'parent', $data ) ) {
			$model->setAttribute(
				$this->columnFor( $model, [ 'parent', 'parent_id' ] ),
				$data['parent']
			);
		}

		foreach ( [ 'menu_order', 'template' ] as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$model->setAttribute( $field, $data[ $field ] );
			}
		}

		return $model;
	}
}
