<?php

/**
 * Editor-side user autocomplete for the "Specific User" visibility rule.
 *
 * The Inspector picker calls this endpoint with a search term; the
 * response is a `{ id, email, name }` list the picker persists into
 * the block's `artisanpackVisibility.specificUser.users` array.
 *
 * The lookup is intentionally minimal — hosts with custom user models
 * or additional access constraints can rebind the controller through
 * the container, or filter results via the
 * `ap.visual-editor.visibility.user-search-results` filter.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers\Visibility;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class UsersSearchController
{
	public function __construct(
		protected Container $container,
		protected ConfigRepository $config,
	) {
	}

	public function index( Request $request ): JsonResponse
	{
		if ( ! Auth::check() ) {
			return new JsonResponse( [ 'data' => [] ], 401 );
		}

		$term  = trim( (string) $request->query( 'q', '' ) );
		$limit = max( 1, min( 20, (int) $request->query( 'limit', 10 ) ) );

		if ( '' === $term ) {
			return new JsonResponse( [ 'data' => [] ] );
		}

		$rows = $this->query( $term, $limit );

		if ( function_exists( 'applyFilters' ) ) {
			try {
				$filtered = applyFilters( 'ap.visual-editor.visibility.user-search-results', $rows, $term, $limit );
				if ( is_array( $filtered ) ) {
					$rows = $filtered;
				}
			} catch ( Throwable $e ) {
				report( $e );
			}
		}

		return new JsonResponse( [ 'data' => array_values( $rows ) ] );
	}

	/**
	 * @return array<int, array{id:int, email:string, name:string}>
	 */
	protected function query( string $term, int $limit ): array
	{
		$model = $this->userModel();

		if ( null === $model || ! class_exists( $model ) ) {
			return [];
		}

		try {
			$instance = new $model();

			if ( ! method_exists( $instance, 'newQuery' ) ) {
				return [];
			}

			$query = $instance->newQuery();

			$searchable = $this->searchableColumns( $instance );

			$query->where( function ( $inner ) use ( $searchable, $term ) {
				foreach ( $searchable as $column ) {
					$inner->orWhere( $column, 'like', '%' . $term . '%' );
				}
			} );

			$results = $query->limit( $limit )->get();

			$out = [];
			foreach ( $results as $user ) {
				$id    = $user->getAuthIdentifier();
				$email = is_string( $user->email ?? null ) ? $user->email : '';
				$name  = is_string( $user->name  ?? null ) ? $user->name  : $email;

				if ( ! is_numeric( $id ) ) {
					continue;
				}

				$out[] = [
					'id'    => (int) $id,
					'email' => $email,
					'name'  => $name,
				];
			}

			return $out;
		} catch ( Throwable $e ) {
			report( $e );
			return [];
		}
	}

	protected function userModel(): ?string
	{
		$configured = $this->config->get( 'artisanpack.visual-editor.visibility.user_model' );

		if ( is_string( $configured ) && '' !== $configured ) {
			return $configured;
		}

		$authModel = $this->config->get( 'auth.providers.users.model' );

		return is_string( $authModel ) && '' !== $authModel ? $authModel : null;
	}

	/**
	 * @return array<int, string>
	 */
	protected function searchableColumns( object $user ): array
	{
		$configured = $this->config->get( 'artisanpack.visual-editor.visibility.user_search_columns' );

		if ( is_array( $configured ) && [] !== $configured ) {
			return array_values( array_filter( $configured, 'is_string' ) );
		}

		$candidates = [];

		if ( property_exists( $user, 'email' )   || isset( $user->email ) )   { $candidates[] = 'email'; }
		if ( property_exists( $user, 'name' )    || isset( $user->name ) )    { $candidates[] = 'name'; }

		return [] === $candidates ? [ 'email' ] : $candidates;
	}
}
