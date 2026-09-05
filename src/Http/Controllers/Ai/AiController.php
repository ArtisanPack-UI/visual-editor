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
use ArtisanPackUI\Ai\Concerns\HandlesAiFeatureResponses;
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
	use HandlesAiFeatureResponses;

	/**
	 * Return the enabled state of the five features the editor cares about.
	 *
	 * @since 1.3.0
	 *
	 * @return JsonResponse
	 */
	public function features(): JsonResponse
	{
		return new JsonResponse( [ 'features' => $this->aiFeatureStateMap( VisualEditorServiceProvider::AI_FEATURE_KEYS ) ] );
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
	 * Tag the shared handler's log line with this surface.
	 *
	 * @since 1.9.0
	 *
	 * @return string
	 */
	protected function aiFeatureLogMessage(): string
	{
		return 'visual-editor AI API call failed';
	}

	/**
	 * Shared wrapper — delegates the exception ladder to the shared
	 * {@see HandlesAiFeatureResponses::handleAiFeature()} trait and folds
	 * the outcome into the JSON envelope.
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
		$outcome = $this->handleAiFeature( $featureKey, $callback );

		if ( $outcome->succeeded ) {
			return new JsonResponse( [
				'feature' => $outcome->feature,
				'output'  => $outcome->output,
			] );
		}

		return new JsonResponse( [
			'feature' => $outcome->feature,
			'error'   => $outcome->errorCode,
			'message' => $outcome->message,
		], $outcome->status );
	}
}
