<?php

/**
 * Content API Controller.
 *
 * Handles API endpoints for content save, publish, unpublish,
 * schedule, and autosave operations.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Http\Controllers
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers;

use ArtisanPackUI\VisualEditor\Models\Content;
use ArtisanPackUI\VisualEditor\Services\ContentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

/**
 * Content API controller class.
 *
 * @since 1.0.0
 */
class ContentApiController extends Controller
{
	use AuthorizesRequests;

	/**
	 * The content service instance.
	 *
	 * @since 1.0.0
	 *
	 * @var ContentService
	 */
	protected ContentService $contentService;

	/**
	 * Creates a new controller instance.
	 *
	 * @since 1.0.0
	 *
	 * @param ContentService $contentService The content service.
	 */
	public function __construct( ContentService $contentService )
	{
		$this->contentService = $contentService;
	}

	/**
	 * Saves content as a draft.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The HTTP request.
	 * @param Content $content The content to save.
	 *
	 * @return JsonResponse
	 */
	public function save( Request $request, Content $content ): JsonResponse
	{
		$user = $request->user();
		if ( !$user ) {
			abort( 401, 'Unauthenticated' );
		}

		$this->authorize( 'update', $content );

		$validated = $request->validate( [
			'title'              => 'sometimes|string|max:255',
			'blocks'             => 'sometimes|array',
			'settings'           => 'sometimes|nullable|array',
			'excerpt'            => 'sometimes|nullable|string',
			'template'           => 'sometimes|nullable|string|max:255',
			'template_overrides' => 'sometimes|nullable|array',
			'meta_title'         => 'sometimes|nullable|string|max:255',
			'meta_description'   => 'sometimes|nullable|string',
			'og_image'           => 'sometimes|nullable|string|max:255',
			'featured_media_id'  => 'sometimes|nullable|integer',
		] );

		$content = $this->contentService->saveDraft(
			$content,
			$validated,
			$user->id,
		);

		return response()->json( [
			'message' => __( 'Content saved.' ),
			'content' => $content,
		] );
	}

	/**
	 * Creates an autosave revision.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The HTTP request.
	 * @param Content $content The content to autosave.
	 *
	 * @return JsonResponse
	 */
	public function autosave( Request $request, Content $content ): JsonResponse
	{
		$user = $request->user();
		if ( !$user ) {
			abort( 401, 'Unauthenticated' );
		}

		$this->authorize( 'update', $content );

		$validated = $request->validate( [
			'blocks'   => 'sometimes|array',
			'settings' => 'sometimes|nullable|array',
		] );

		$revision = $this->contentService->autosave(
			$content,
			$validated,
			$user->id,
		);

		return response()->json( [
			'message'     => __( 'Autosave created.' ),
			'revision_id' => $revision->id,
		] );
	}

	/**
	 * Publishes content.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The HTTP request.
	 * @param Content $content The content to publish.
	 *
	 * @return JsonResponse
	 */
	public function publish( Request $request, Content $content ): JsonResponse
	{
		$user = $request->user();
		if ( !$user ) {
			abort( 401, 'Unauthenticated' );
		}

		$this->authorize( 'publish', $content );

		$content = $this->contentService->publish(
			$content,
			$user->id,
		);

		return response()->json( [
			'message' => __( 'Content published.' ),
			'content' => $content,
		] );
	}

	/**
	 * Unpublishes content.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The HTTP request.
	 * @param Content $content The content to unpublish.
	 *
	 * @return JsonResponse
	 */
	public function unpublish( Request $request, Content $content ): JsonResponse
	{
		$user = $request->user();
		if ( !$user ) {
			abort( 401, 'Unauthenticated' );
		}

		$this->authorize( 'unpublish', $content );

		$content = $this->contentService->unpublish(
			$content,
			$user->id,
		);

		return response()->json( [
			'message' => __( 'Content unpublished.' ),
			'content' => $content,
		] );
	}

	/**
	 * Schedules content for future publication.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The HTTP request.
	 * @param Content $content The content to schedule.
	 *
	 * @return JsonResponse
	 */
	public function schedule( Request $request, Content $content ): JsonResponse
	{
		$user = $request->user();
		if ( !$user ) {
			abort( 401, 'Unauthenticated' );
		}

		$this->authorize( 'schedule', $content );

		$validated = $request->validate( [
			'scheduled_at' => 'required|date|after:now',
		] );

		$content = $this->contentService->schedule(
			$content,
			Carbon::parse( $validated['scheduled_at'] ),
			$user->id,
		);

		return response()->json( [
			'message' => __( 'Content scheduled.' ),
			'content' => $content,
		] );
	}
}
