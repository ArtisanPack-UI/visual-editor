<?php

/**
 * Imports a remote image into the media library.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers;

use ArtisanPackUI\VisualEditor\Http\Requests\ImportMediaRequest;
use ArtisanPackUI\VisualEditor\Services\Media\RemoteImageException;
use ArtisanPackUI\VisualEditor\Services\Media\RemoteImageFetcher;
use ArtisanPackUI\VisualEditor\Support\MediaImportAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * Backs the editor's offer to import pasted external images (#1066).
 *
 * The media library is optional, so it's reached through its
 * `apUploadMedia()` helper, like {@see AttachmentController}, and uploads
 * are authorized with its own policy.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.20.0
 */
class MediaImportController extends Controller
{
	public function __construct( protected RemoteImageFetcher $fetcher )
	{
	}

	/**
	 * Download a URL and add it to the media library.
	 *
	 * @since 1.20.0
	 *
	 * @param  ImportMediaRequest  $request  The validated request.
	 *
	 * @return JsonResponse The new media item's id and URL.
	 */
	public function store( ImportMediaRequest $request ): JsonResponse
	{
		if ( ! MediaImportAccess::isAvailable() ) {
			return response()->json( [ 'message' => __( 'No media library is installed.' ) ], Response::HTTP_NOT_IMPLEMENTED );
		}

		Gate::authorize( 'create', MediaImportAccess::MEDIA_MODEL );

		try {
			$file = $this->fetcher->fetch( $request->string( 'url' )->toString() );
		} catch ( RemoteImageException $exception ) {
			return response()->json( [ 'message' => $exception->getMessage() ], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		try {
			$media = apUploadMedia( $file, array_filter( [ 'alt_text' => $request->input( 'alt' ) ] ) );
		} finally {
			@unlink( $file->getPathname() );
		}

		return response()->json( [ 'id' => $media->id, 'url' => $media->url() ], Response::HTTP_CREATED );
	}
}
