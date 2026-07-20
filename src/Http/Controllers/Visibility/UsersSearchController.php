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
 * `ap.visualEditor.visibility.userSearchResults` filter.
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

use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class UsersSearchController
{
	public function __construct(
		protected Container $container,
		protected ConfigRepository $config,
		protected SiteEditorAccessGate $gate,
	) {
	}

	public function index( Request $request ): JsonResponse
	{
		// Gate on the bound `SiteEditorAccessGate` — the same contract
		// the site-editor SPA mount + admin icon-set management routes
		// use. `Auth::check()` alone would let any authenticated
		// visitor (customer-facing accounts, cms-framework
		// subscribers, e-commerce login sessions, …) enumerate the
		// full users table via `?q=<letter>` iteration, since the
		// default `['api', 'auth']` middleware stack accepts every
		// authenticated principal.
		$gateResponse = $this->gate->check( $request );
		if ( null !== $gateResponse ) {
			return new JsonResponse( [ 'data' => [] ], 403 );
		}

		$term  = trim( (string) $request->query( 'q', '' ) );
		$limit = max( 1, min( 20, (int) $request->query( 'limit', 10 ) ) );

		if ( '' === $term ) {
			return new JsonResponse( [ 'data' => [] ] );
		}

		$rows = $this->query( $term, $limit );

		if ( function_exists( 'applyFilters' ) ) {
			try {
				$filtered = applyFilters( 'ap.visualEditor.visibility.userSearchResults', $rows, $term, $limit );
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

			// Escape LIKE metacharacters (`%` / `_` / `|`) so a caller
			// sending `?q=%25` cannot collapse the pattern to `%%%`
			// and dump the first N users in one request. `|` is the
			// explicit ESCAPE character — matches the pattern used by
			// `EntitySearchController::buildLikeQuery()`.
			$escaped = str_replace(
				[ '|', '%', '_' ],
				[ '||', '|%', '|_' ],
				$term
			);
			$needle  = '%' . $escaped . '%';

			$query->where( function ( $inner ) use ( $searchable, $needle ) {
				foreach ( $searchable as $column ) {
					$inner->orWhereRaw( $column . " LIKE ? ESCAPE '|'", [ $needle ] );
				}
			} );

			$results = $query->limit( $limit )->get();

			$out = [];
			foreach ( $results as $user ) {
				$id    = $user->getAuthIdentifier();
				$email = is_string( $user->email ?? null ) ? $user->email : '';
				$name  = is_string( $user->name  ?? null ) ? $user->name  : $email;

				// Accept both integer keys and non-numeric string keys
				// (UUIDs from `HasUuids`) so hosts on either model
				// keying scheme surface real results in the picker.
				if ( ! is_scalar( $id ) || '' === (string) $id ) {
					continue;
				}

				$out[] = [
					'id'    => is_int( $id ) ? $id : (string) $id,
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
