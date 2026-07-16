<?php

/**
 * DynamicContent batched resolve controller.
 *
 * Editor endpoint: takes a list of tokens (e.g.
 * `business_info.phone`, `team[0].name`) and returns each token's
 * resolved value in a single response so a page with dozens of
 * bindings doesn't fire one request per token. Values are returned
 * raw (string / int / array / null) — the client renders them into
 * chip previews.
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

use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\DynamicContentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DynamicContentResolveController extends Controller
{
	/**
	 * Cap the batch payload so an errant client cannot force a
	 * many-thousand-token walk in one request.
	 *
	 * @since 1.4.0
	 */
	public const MAX_TOKENS = 200;

	public function __construct( protected DynamicContentSource $source )
	{
	}

	/**
	 * Resolve a batch of dynamic-content tokens.
	 *
	 * Request body:
	 * - `tokens` list<string>  Distinct token strings.
	 *
	 * Response body:
	 * - `values` map<string, mixed>  Token → resolved value. Missing /
	 *   unresolved tokens map to `null`.
	 *
	 * @since 1.4.0
	 */
	public function resolve( Request $request ): JsonResponse
	{
		$tokens = $request->input( 'tokens', [] );

		if ( ! is_array( $tokens ) ) {
			return response()->json( [
				'error'   => 'invalid_payload',
				'message' => 'tokens must be an array.',
			], 422 );
		}

		if ( count( $tokens ) > self::MAX_TOKENS ) {
			return response()->json( [
				'error'   => 'too_many_tokens',
				'message' => sprintf( 'At most %d tokens per batch.', self::MAX_TOKENS ),
			], 422 );
		}

		$tokens = array_values( array_unique( array_filter(
			$tokens,
			static fn ( $value ): bool => is_string( $value ) && '' !== trim( $value )
		) ) );

		$context = new BindingContext();
		$values  = [];

		foreach ( $tokens as $token ) {
			$values[ $token ] = $this->source->resolve( $context, [ 'token' => $token ] );
		}

		return response()->json( [
			'values' => empty( $values ) ? (object) [] : $values,
		] );
	}
}
