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
	/** The media library's model, named as a string because it's optional. */
	protected const MEDIA_MODEL = 'ArtisanPackUI\\MediaLibrary\\Models\\Media';

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
		if ( ! function_exists( 'apUploadMedia' ) ) {
			return response()->json( [ 'message' => __( 'No media library is installed.' ) ], Response::HTTP_NOT_IMPLEMENTED );
		}

		Gate::authorize( 'create', self::MEDIA_MODEL );

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
