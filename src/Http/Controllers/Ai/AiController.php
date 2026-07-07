<?php

/**
 * JSON API controller for the visual-editor's AI trigger surface.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers\Ai;

use ArtisanPackUI\Ai\Agents\AltTextGenerationAgent;
use ArtisanPackUI\Ai\Agents\ContentRewriteAgent;
use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Ai\Exceptions\MissingCredentialsException;
use ArtisanPackUI\VisualEditor\Ai\Agents\ContentBlockSuggestionAgent;
use ArtisanPackUI\VisualEditor\Ai\Agents\HeadingHierarchyAgent;
use ArtisanPackUI\VisualEditor\Ai\Agents\LayoutSuggestionAgent;
use ArtisanPackUI\VisualEditor\Http\Requests\Ai\AltTextRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\Ai\HeadingHierarchyRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\Ai\RewriteContentRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\Ai\SuggestLayoutRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\Ai\SuggestNextBlockRequest;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * REST surface used by the React editor. Each endpoint runs one agent
 * against a validated body and returns the shaped output, plus token
 * accounting when present. Feature-toggle enforcement lives inside the
 * agents themselves — this controller only wraps errors in a consistent
 * JSON envelope.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */
class AiController
{
	/**
	 * Return the enabled state of the five features the editor cares about.
	 *
	 * @since 1.3.0
	 *
	 * @return JsonResponse
	 */
	public function features(): JsonResponse
	{
		/** @var FeatureRegistry $registry */
		$registry = app( FeatureRegistry::class );

		$state = [];
		foreach ( VisualEditorServiceProvider::AI_FEATURE_KEYS as $key ) {
			$state[ $key ] = null !== $registry->get( $key ) && $registry->isToggleOn( $key );
		}

		return new JsonResponse( [ 'features' => $state ] );
	}

	/**
	 * POST /suggest-next-block.
	 *
	 * @since 1.3.0
	 *
	 * @param  SuggestNextBlockRequest  $request  Incoming request.
	 *
	 * @return JsonResponse
	 */
	public function suggestNextBlock( SuggestNextBlockRequest $request ): JsonResponse
	{
		return $this->runAgent(
			'visual_editor.suggest_next_block',
			fn () => ContentBlockSuggestionAgent::for( $request->validated() )->run(),
		);
	}

	/**
	 * POST /suggest-layout.
	 *
	 * @since 1.3.0
	 *
	 * @param  SuggestLayoutRequest  $request  Incoming request.
	 *
	 * @return JsonResponse
	 */
	public function suggestLayout( SuggestLayoutRequest $request ): JsonResponse
	{
		return $this->runAgent(
			'visual_editor.suggest_layout',
			fn () => LayoutSuggestionAgent::for( $request->validated() )->run(),
		);
	}

	/**
	 * POST /alt-text.
	 *
	 * @since 1.3.0
	 *
	 * @param  AltTextRequest  $request  Incoming request.
	 *
	 * @return JsonResponse
	 */
	public function altText( AltTextRequest $request ): JsonResponse
	{
		return $this->runAgent(
			'ai.alt_text',
			fn () => AltTextGenerationAgent::for( $request->validated()['image'] )->run(),
		);
	}

	/**
	 * POST /rewrite.
	 *
	 * @since 1.3.0
	 *
	 * @param  RewriteContentRequest  $request  Incoming request.
	 *
	 * @return JsonResponse
	 */
	public function rewrite( RewriteContentRequest $request ): JsonResponse
	{
		return $this->runAgent(
			'ai.content_rewrite',
			fn () => ContentRewriteAgent::for( $request->validated() )->run(),
		);
	}

	/**
	 * POST /heading-hierarchy.
	 *
	 * @since 1.3.0
	 *
	 * @param  HeadingHierarchyRequest  $request  Incoming request.
	 *
	 * @return JsonResponse
	 */
	public function headingHierarchy( HeadingHierarchyRequest $request ): JsonResponse
	{
		return $this->runAgent(
			'visual_editor.heading_hierarchy',
			fn () => HeadingHierarchyAgent::for( $request->validated() )->run(),
		);
	}

	/**
	 * Shared wrapper — normalizes the four agent-exception categories into
	 * consistent status codes + JSON envelopes.
	 *
	 * @since 1.3.0
	 *
	 * @param  string    $featureKey  Feature key (for logging + envelope).
	 * @param  callable  $callback    Callable returning the agent output.
	 *
	 * @return JsonResponse
	 */
	private function runAgent( string $featureKey, callable $callback ): JsonResponse
	{
		try {
			$output = $callback();
			return new JsonResponse( [
				'feature' => $featureKey,
				'output'  => $output,
			] );
		} catch ( FeatureDisabledException $e ) {
			return new JsonResponse( [
				'feature' => $featureKey,
				'error'   => 'feature_disabled',
				'message' => $e->getMessage(),
			], 403 );
		} catch ( MissingCredentialsException $e ) {
			return new JsonResponse( [
				'feature' => $featureKey,
				'error'   => 'missing_credentials',
				'message' => $e->getMessage(),
			], 503 );
		} catch ( FeatureError $e ) {
			return new JsonResponse( [
				'feature' => $featureKey,
				'error'   => 'invalid_input',
				'message' => $e->getMessage(),
			], 422 );
		} catch ( Throwable $e ) {
			Log::error( 'visual-editor AI API call failed', [
				'feature' => $featureKey,
				'error'   => $e->getMessage(),
			] );
			return new JsonResponse( [
				'feature' => $featureKey,
				'error'   => 'internal_error',
				'message' => 'Unexpected error running AI feature.',
			], 500 );
		}
	}
}
