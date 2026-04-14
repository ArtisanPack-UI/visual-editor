<?php

/**
 * VisualEditorPosts controller.
 *
 * Serves GET/PUT JSON endpoints for the block-tree document backing a post.
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

use ArtisanPackUI\VisualEditor\Http\Requests\UpdatePostBlocksRequest;
use ArtisanPackUI\VisualEditor\Models\VisualEditorPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class VisualEditorPostsController extends Controller
{
	/**
	 * Returns the block tree for the given post.
	 *
	 * @since 1.0.0
	 */
	public function show( VisualEditorPost $post ): JsonResponse
	{
		Gate::authorize( 'view', $post );

		return response()->json( $this->transform( $post ) );
	}

	/**
	 * Persists an updated block tree for the given post.
	 *
	 * @since 1.0.0
	 */
	public function update( UpdatePostBlocksRequest $request, VisualEditorPost $post ): JsonResponse
	{
		Gate::authorize( 'update', $post );

		$post->blocks = $request->validated( 'blocks' );
		$post->save();

		return response()->json( $this->transform( $post ) );
	}

	/**
	 * Shapes a post for the JSON response.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function transform( VisualEditorPost $post ): array
	{
		return [
			'id'         => $post->id,
			'title'      => $post->title,
			'blocks'     => $post->blocks ?? [],
			'updated_at' => $post->updated_at?->toIso8601String(),
		];
	}
}
