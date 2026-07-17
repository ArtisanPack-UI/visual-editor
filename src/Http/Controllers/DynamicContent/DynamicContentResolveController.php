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
 * Gated on {@see SiteEditorAccessGate} so a low-privilege
 * authenticated user can't enumerate the site's Dynamic Content
 * namespace. The route also carries a hard `throttle` limit
 * (see routes/api.php) as belt-and-suspenders against DoS.
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
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class DynamicContentResolveController extends Controller
{
	/**
	 * Cap the batch payload so an errant client cannot force a
	 * many-thousand-token walk in one request.
	 *
	 * @since 1.4.0
	 */
	public const MAX_TOKENS = 200;

	public function __construct(
		protected DynamicContentSource $source,
		protected SiteEditorAccessGate $gate,
	) {
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
	public function resolve( Request $request ): JsonResponse|Response
	{
		if ( $denial = $this->gate->check( $request ) ) {
			return $denial;
		}

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

		// Per-source memoization: multiple tokens that share the same
		// source (e.g. `team[0].name`, `team[0].role`, `team[1].name`)
		// would otherwise trigger one accessor lookup each. The source
		// resolver itself has no cross-token memo, so we short-circuit
		// duplicate reads at the controller layer by resolving each
		// token exactly once and letting the resolveTokens map handle
		// the dedup for repeats.
		$context = new BindingContext();
		$values  = [];

		foreach ( $tokens as $token ) {
			if ( array_key_exists( $token, $values ) ) {
				continue;
			}

			$values[ $token ] = $this->source->resolve( $context, [ 'token' => $token ] );
		}

		return response()->json( [
			'values' => empty( $values ) ? (object) [] : $values,
		] );
	}
}
