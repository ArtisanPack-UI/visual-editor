<?php

/**
 * `POST /visual-editor/api/query/resolve` controller.
 *
 * Wraps {@see QueryResolverContract::resolve()} for the editor canvas
 * preview and the React/Vue front-end renderers' client-side fetcher.
 * Returns paginated WP-shape results (the same envelope the G3 entity
 * adapters use under `data` + `meta` keys) so the same response shape
 * powers `useEntityRecord`-style consumers.
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

use ArtisanPackUI\VisualEditor\Http\Requests\QueryResolveRequest;
use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\PageResource;
use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\PostResource;
use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\WpEntityResource;
use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Throwable;

class QueryResolveController extends Controller
{
	public function __construct( protected Container $container ) {}

	public function resolve( QueryResolveRequest $request ): JsonResponse
	{
		if ( ! $this->container->bound( QueryResolverContract::class ) ) {
			return new JsonResponse(
				[ 'message' => 'Query runtime is not available. Install artisanpack-ui/cms-framework or bind a custom resolver to QueryResolverContract.' ],
				Response::HTTP_SERVICE_UNAVAILABLE
			);
		}

		/** @var QueryResolverContract $resolver */
		$resolver = $this->container->make( QueryResolverContract::class );
		$payload  = $request->validated();

		try {
			$paginator = $resolver->resolve( $payload );
		} catch ( Throwable $e ) {
			report( $e );

			return new JsonResponse(
				[ 'message' => 'Failed to resolve the query payload.' ],
				Response::HTTP_BAD_REQUEST
			);
		}

		$resourceClass = $this->resourceClassFor( isset( $payload['postType'] ) && is_string( $payload['postType'] ) ? $payload['postType'] : 'post' );

		$data = collect( $paginator->items() )->map(
			static fn ( object $item ): array => ( new $resourceClass( $item ) )->toArray( request() )
		)->values()->all();

		return new JsonResponse( [
			'data' => $data,
			'meta' => [
				'total'        => $paginator->total(),
				'per_page'     => $paginator->perPage(),
				'current_page' => $paginator->currentPage(),
				'last_page'    => $paginator->lastPage(),
			],
		] );
	}

	/**
	 * Pick the WP-shape transformer for the requested post type. Falls
	 * back to {@see PostResource} for unknown types so the response keeps
	 * a consistent shape — fields the underlying model does not expose
	 * are silently dropped by the resource's `extraFields()` contract.
	 *
	 * @return class-string<WpEntityResource>
	 */
	protected function resourceClassFor( string $postType ): string
	{
		return match ( $postType ) {
			'page'  => PageResource::class,
			default => PostResource::class,
		};
	}
}
