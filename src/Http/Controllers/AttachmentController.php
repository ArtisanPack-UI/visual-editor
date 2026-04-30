<?php

/**
 * Attachment controller.
 *
 * Serves the WP REST `/wp/v2/media/{id}` envelope that Gutenberg blocks
 * (notably `core/post-featured-image` and `core/cover`'s featured-image
 * option) resolve through `getEntityRecord('postType', 'attachment',
 * id)`. Without this endpoint the core-data shim's `attachment` entity
 * registration would 404 and those blocks would render the empty
 * placeholder even when a `featured_media` id is present on the post.
 *
 * The controller delegates to the host's media library through the
 * `apGetMedia()` helper exposed by `artisanpack-ui/media-library`. When
 * the helper isn't available (no media library installed, or the host
 * uses a different library) the endpoint 404s — block render falls
 * back to the empty placeholder, matching the behaviour for any
 * unresolved attachment id.
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

use ArtisanPackUI\VisualEditor\MediaBridge\GutenbergAttachmentAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class AttachmentController extends Controller
{
	public function __construct( protected GutenbergAttachmentAdapter $adapter )
	{
	}

	/**
	 * Returns a single media record in WP REST shape.
	 *
	 * @since 1.0.0
	 */
	public function show( int|string $id ): JsonResponse
	{
		if ( ! function_exists( 'apGetMedia' ) ) {
			return response()->json( null, Response::HTTP_NOT_FOUND );
		}

		$media = apGetMedia( (int) $id );

		if ( null === $media ) {
			return response()->json( null, Response::HTTP_NOT_FOUND );
		}

		return response()->json( $this->adapter->toWpRestShape( $media ) );
	}
}
