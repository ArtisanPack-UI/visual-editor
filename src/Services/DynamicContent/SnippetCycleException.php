<?php

/**
 * Thrown when a snippet write or render would introduce a reference
 * cycle in the snippet graph.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\DynamicContent;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SnippetCycleException extends RuntimeException
{
	/**
	 * Render as a 422 so the client's form-request error surface picks
	 * it up alongside slug/title validation errors.
	 *
	 * @since 1.4.0
	 */
	public function render( Request $request ): JsonResponse
	{
		return response()->json( [
			'message' => $this->getMessage(),
			'errors'  => [
				'blocks' => [ $this->getMessage() ],
			],
		], 422 );
	}
}
