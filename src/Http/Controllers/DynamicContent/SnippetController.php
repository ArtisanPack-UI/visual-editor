<?php

/**
 * Snippet CRUD controller.
 *
 * Backs the Snippets admin UI and the editor's snippet inserter.
 * Every action runs through {@see SiteEditorAccessGate} — the same
 * gate that fronts the site-editor SPA and the icon-set admin — so
 * a low-privilege authenticated user cannot overwrite or delete
 * site-wide snippets by hitting the JSON API directly. Snippet edits
 * propagate to every placement, so the surface has to be gated at
 * the same tier as templates and patterns.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers\DynamicContent;

use ArtisanPackUI\VisualEditor\Http\Controllers\Requests\SnippetRequest;
use ArtisanPackUI\VisualEditor\Models\Snippet;
use ArtisanPackUI\VisualEditor\Services\DynamicContent\SnippetCycleGuard;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class SnippetController extends Controller
{
	public function __construct(
		protected SnippetCycleGuard $cycleGuard,
		protected SiteEditorAccessGate $gate,
	) {
	}

	public function index( Request $request ): JsonResponse|Response
	{
		if ( $denial = $this->gate->check( $request ) ) {
			return $denial;
		}

		$search = trim( (string) $request->query( 'search', '' ) );

		$query = Snippet::query()->orderBy( 'slug' );

		if ( '' !== $search ) {
			$query->where( function ( $q ) use ( $search ): void {
				$q->where( 'slug', 'like', '%' . $search . '%' )
					->orWhere( 'title', 'like', '%' . $search . '%' );
			} );
		}

		return response()->json( [
			'data' => $query->limit( 200 )->get()->map( fn ( Snippet $s ): array => $this->toArray( $s ) )->all(),
		] );
	}

	public function store( SnippetRequest $request ): JsonResponse|Response
	{
		if ( $denial = $this->gate->check( $request ) ) {
			return $denial;
		}

		$data          = $request->validated();
		$data['blocks'] ??= [];

		$snippet = new Snippet( $data );

		$authorId = $this->currentUserId( $request );
		if ( null !== $authorId ) {
			$snippet->author_id = $authorId;
		}

		// Reject a self-cycle at create time even though the id is not
		// yet assigned — the guard treats the row's slug as its
		// identity when checking a placement.
		$this->cycleGuard->assertNoCycle( $snippet->slug, (array) $data['blocks'] );

		$snippet->save();

		return response()->json( [ 'data' => $this->toArray( $snippet ) ], 201 );
	}

	public function show( Request $request, Snippet $snippet ): JsonResponse|Response
	{
		if ( $denial = $this->gate->check( $request ) ) {
			return $denial;
		}

		return response()->json( [ 'data' => $this->toArray( $snippet ) ] );
	}

	public function update( SnippetRequest $request, Snippet $snippet ): JsonResponse|Response
	{
		if ( $denial = $this->gate->check( $request ) ) {
			return $denial;
		}

		$data = $request->validated();

		$this->cycleGuard->assertNoCycle(
			(string) ( $data['slug'] ?? $snippet->slug ),
			(array) ( $data['blocks'] ?? $snippet->blocks ?? [] )
		);

		$snippet->fill( $data )->save();

		return response()->json( [ 'data' => $this->toArray( $snippet->fresh() ) ] );
	}

	public function destroy( Request $request, Snippet $snippet ): JsonResponse|Response
	{
		if ( $denial = $this->gate->check( $request ) ) {
			return $denial;
		}

		$snippet->delete();

		return response()->json( null, 204 );
	}

	/**
	 * @return array<string, mixed>
	 *
	 * @since 1.4.0
	 */
	protected function toArray( Snippet $snippet ): array
	{
		return [
			'id'         => $snippet->id,
			'slug'       => $snippet->slug,
			'title'      => $snippet->title,
			'blocks'     => is_array( $snippet->blocks ) ? $snippet->blocks : [],
			'author_id'  => $snippet->author_id,
			'created_at' => optional( $snippet->created_at )->toIso8601String(),
			'updated_at' => optional( $snippet->updated_at )->toIso8601String(),
		];
	}

	/**
	 * @since 1.4.0
	 */
	protected function currentUserId( Request $request ): ?int
	{
		$user = $request->user();

		if ( null === $user ) {
			return null;
		}

		$id = method_exists( $user, 'getAuthIdentifier' ) ? $user->getAuthIdentifier() : null;

		return is_int( $id ) || ( is_string( $id ) && ctype_digit( $id ) ) ? (int) $id : null;
	}
}
