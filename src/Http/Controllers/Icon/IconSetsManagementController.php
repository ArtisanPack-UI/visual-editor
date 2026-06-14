<?php

/**
 * Admin icon-set management endpoints.
 *
 * Phase 6 (#557) of the Icon Block feature (#494). Backs the settings
 * screen — list, upload, rename, delete. Every action runs through the
 * bound {@see SiteEditorAccessGate} (the package's existing
 * visual-editor management policy) so the surface is unreachable for
 * users who can't reach the rest of the site editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers\Icon;

use ArtisanPackUI\VisualEditor\Http\Requests\Icon\RenameIconSetRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\Icon\UploadIconSetRequest;
use ArtisanPackUI\VisualEditor\Services\Icon\IconSetUploader;
use ArtisanPackUI\VisualEditor\Services\Icon\PrefixCollisionException;
use ArtisanPackUI\VisualEditor\Services\Icon\UploadedIconSet;
use ArtisanPackUI\VisualEditor\Services\Icon\UploadedIconSetRegistry;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class IconSetsManagementController extends Controller
{
	public function __construct(
		protected UploadedIconSetRegistry $registry,
		protected IconSetUploader $uploader,
		protected SiteEditorAccessGate $gate,
	) {
	}

	public function index( Request $request ): Response
	{
		if ( $denial = $this->gate->check( $request ) ) {
			return $denial;
		}

		return new JsonResponse( [
			'data' => array_map(
				static fn ( UploadedIconSet $set ): array => $set->toArray(),
				$this->registry->all(),
			),
		] );
	}

	public function store( UploadIconSetRequest $request ): Response
	{
		if ( $denial = $this->gate->check( $request ) ) {
			return $denial;
		}

		try {
			$result = $this->uploader->upload(
				$request->file( 'zip' ),
				(string) $request->input( 'prefix' ),
				(string) $request->input( 'label' ),
			);
		} catch ( PrefixCollisionException $e ) {
			return new JsonResponse( [
				'error'  => 'prefix_collision',
				'prefix' => $e->prefix,
				'message' => $e->getMessage(),
			], Response::HTTP_CONFLICT );
		} catch ( RuntimeException $e ) {
			return new JsonResponse( [
				'error'   => 'upload_failed',
				'message' => $e->getMessage(),
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		return new JsonResponse( [
			'data'   => $this->registry->find( (string) $request->input( 'prefix' ) )?->toArray(),
			'report' => $result->toArray(),
		], Response::HTTP_CREATED );
	}

	public function update( RenameIconSetRequest $request, string $prefix ): Response
	{
		if ( $denial = $this->gate->check( $request ) ) {
			return $denial;
		}

		if ( ! $this->registry->has( $prefix ) ) {
			return new JsonResponse( [ 'error' => 'not_found' ], Response::HTTP_NOT_FOUND );
		}

		try {
			$updated = $this->registry->rename( $prefix, (string) $request->input( 'label' ) );
		} catch ( RuntimeException $e ) {
			return new JsonResponse( [
				'error'   => 'rename_failed',
				'message' => $e->getMessage(),
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		return new JsonResponse( [ 'data' => $updated->toArray() ] );
	}

	public function destroy( Request $request, string $prefix ): Response
	{
		if ( $denial = $this->gate->check( $request ) ) {
			return $denial;
		}

		if ( ! $this->registry->has( $prefix ) ) {
			return new JsonResponse( [ 'error' => 'not_found' ], Response::HTTP_NOT_FOUND );
		}

		$this->uploader->delete( $prefix );

		return new JsonResponse( null, Response::HTTP_NO_CONTENT );
	}
}
