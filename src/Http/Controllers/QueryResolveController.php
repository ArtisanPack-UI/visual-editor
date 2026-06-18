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
use ArtisanPackUI\VisualEditor\Services\HostRelatedTermsResolver;
use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
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

		// Related-Posts editor preview (#601): when `relatedTo` is set,
		// resolve the host post's primary taxonomy + terms server-side
		// and rewrite the payload into a related-by-taxonomy query.
		// Returns an empty paginator early when no host post matches
		// or the host has no related-terms signal — matching the
		// editor's expected zero-result behavior without ever hitting
		// the resolver with bad attrs.
		if ( isset( $payload['relatedTo'] ) ) {
			$expanded = $this->expandRelatedTo( $resolver, $payload );

			if ( null === $expanded ) {
				return new JsonResponse( [
					'data' => [],
					'meta' => [
						'total'        => 0,
						'per_page'     => isset( $payload['perPage'] ) ? (int) $payload['perPage'] : 3,
						'current_page' => 1,
						'last_page'    => 1,
					],
				] );
			}

			$payload = $expanded;
		}

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
	 * Resolve the `relatedTo` editor-preview shortcut into a literal
	 * related-by-taxonomy query payload. Returns `null` when the host
	 * post cannot be loaded or carries no related-terms signal so the
	 * caller can short-circuit with an empty paginator instead of
	 * issuing a "give me every post" query.
	 *
	 * @since 1.2.0
	 *
	 * @param  array<string, mixed>  $payload
	 *
	 * @return array<string, mixed>|null
	 */
	protected function expandRelatedTo( QueryResolverContract $resolver, array $payload ): ?array
	{
		$hostId = isset( $payload['relatedTo'] ) ? (int) $payload['relatedTo'] : 0;

		if ( $hostId < 1 ) {
			return null;
		}

		$requestedType = isset( $payload['postType'] ) && is_string( $payload['postType'] )
			? trim( $payload['postType'] )
			: '';

		$helper = new HostRelatedTermsResolver( $resolver );
		$host   = $helper->loadHostPost( $hostId, '' === $requestedType ? 'post' : $requestedType );

		if ( null === $host ) {
			return null;
		}

		[ $taxonomy, $termIds ] = $helper->hostRelatedTerms( $host );

		if ( [] === $termIds ) {
			return null;
		}

		$next = $payload;
		unset( $next['relatedTo'] );

		// The host post's actual type wins over the requested type —
		// "related" only makes sense within one type, and the inliner
		// path applies the same rule. Excluding the host id prevents
		// the host itself from appearing in its own related-posts row.
		$next['postType'] = $helper->hostPostType( $host );
		$next['exclude']  = $this->mergeExclude( $next['exclude'] ?? [], $hostId );
		$next['taxQuery'] = [
			'taxonomy' => $taxonomy,
			'terms'    => $termIds,
			'operator' => 'IN',
		];

		return $next;
	}

	/**
	 * Merge the caller's `exclude` list with the host post id, keeping
	 * unique positive integers so a malformed payload never sneaks a
	 * non-numeric value past the resolver layer.
	 *
	 * @param  mixed  $existing
	 *
	 * @return array<int, int>
	 */
	protected function mergeExclude( mixed $existing, int $hostId ): array
	{
		$out = [];

		if ( is_array( $existing ) ) {
			foreach ( $existing as $value ) {
				if ( is_numeric( $value ) ) {
					$int = (int) $value;

					if ( $int > 0 ) {
						$out[] = $int;
					}
				}
			}
		}

		$out[] = $hostId;

		return array_values( array_unique( $out ) );
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
