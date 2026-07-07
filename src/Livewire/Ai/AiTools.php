<?php

/**
 * Livewire trigger surface for the visual-editor AI features.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Livewire\Ai;

use ArtisanPackUI\Ai\Agents\AltTextGenerationAgent;
use ArtisanPackUI\Ai\Agents\ContentRewriteAgent;
use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Ai\Exceptions\MissingCredentialsException;
use ArtisanPackUI\VisualEditor\Ai\Agents\ContentBlockSuggestionAgent;
use ArtisanPackUI\VisualEditor\Ai\Agents\HeadingHierarchyAgent;
use ArtisanPackUI\VisualEditor\Ai\Agents\LayoutSuggestionAgent;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Thin Livewire wrapper that runs any of the five AI features the visual
 * editor exposes and dispatches the shaped result via a browser event.
 * Editor React code (or any host Blade view) listens for the event on
 * `ap-ve-ai:{featureKey}:{status}` and folds the payload into the UI.
 *
 * The component intentionally holds no persistent state — a Livewire
 * class was picked so:
 *   - CSRF, auth, and rate limiting come "for free" via the standard
 *     Livewire endpoint
 *   - Hosts embedding a Blade admin surface can wire the same triggers
 *     without stamping five bespoke controllers
 *   - Feature-toggle checks live in one place
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */
class AiTools extends Component
{
	/**
	 * Suggest the next block given the current block list + caret offset.
	 *
	 * @since 1.3.0
	 *
	 * @param  array<int, mixed>  $existingBlocks   Ordered block list.
	 * @param  int                $cursorPosition   Insertion offset (0-indexed).
	 * @param  string|null        $documentType     Optional shape hint.
	 *
	 * @return void
	 */
	#[On( 'ap-ve-ai:suggest-next-block' )]
	public function suggestNextBlock( array $existingBlocks, int $cursorPosition, ?string $documentType = null ): void
	{
		$this->run(
			'visual_editor.suggest_next_block',
			fn () => ContentBlockSuggestionAgent::for( [
				'existing_blocks' => $existingBlocks,
				'cursor_position' => $cursorPosition,
				'document_type'   => $documentType,
			] )->run(),
		);
	}

	/**
	 * Suggest a section layout from the caller's pattern library.
	 *
	 * @since 1.3.0
	 *
	 * @param  array<int, mixed>   $sectionContent      Block payloads inside the section.
	 * @param  array<int, string>  $availablePatterns   Whitelist of pattern slugs.
	 *
	 * @return void
	 */
	#[On( 'ap-ve-ai:suggest-layout' )]
	public function suggestLayout( array $sectionContent, array $availablePatterns ): void
	{
		$this->run(
			'visual_editor.suggest_layout',
			fn () => LayoutSuggestionAgent::for( [
				'section_content'    => $sectionContent,
				'available_patterns' => $availablePatterns,
			] )->run(),
		);
	}

	/**
	 * Generate alt text for a newly added image block (see #612).
	 *
	 * @since 1.3.0
	 *
	 * @param  string|array<string, mixed>  $image  Image reference — see AltTextGenerationAgent for accepted shapes.
	 *
	 * @return void
	 */
	#[On( 'ap-ve-ai:generate-alt-text' )]
	public function generateAltText( string|array $image ): void
	{
		$this->run(
			'ai.alt_text',
			fn () => AltTextGenerationAgent::for( $image )->run(),
		);
	}

	/**
	 * Rewrite a piece of content according to an intent (see #613).
	 *
	 * @since 1.3.0
	 *
	 * @param  string  $content  Original content (Markdown, HTML, or plain text).
	 * @param  string  $intent   Rewrite intent ("shorter", "more formal", "reading level 6", ...).
	 *
	 * @return void
	 */
	#[On( 'ap-ve-ai:rewrite-content' )]
	public function rewriteContent( string $content, string $intent ): void
	{
		$this->run(
			'ai.content_rewrite',
			fn () => ContentRewriteAgent::for( [
				'content' => $content,
				'intent'  => $intent,
			] )->run(),
		);
	}

	/**
	 * Audit the document's heading hierarchy (see #614).
	 *
	 * @since 1.3.0
	 *
	 * @param  array<int, mixed>  $blocks  Ordered block payloads.
	 *
	 * @return void
	 */
	#[On( 'ap-ve-ai:check-headings' )]
	public function checkHeadings( array $blocks ): void
	{
		$this->run(
			'visual_editor.heading_hierarchy',
			fn () => HeadingHierarchyAgent::for( [ 'blocks' => $blocks ] )->run(),
		);
	}

	/**
	 * Return the currently-enabled feature toggle map so the front-end
	 * knows which affordances to render.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, bool>
	 */
	public function enabledFeatures(): array
	{
		/** @var FeatureRegistry $registry */
		$registry = app( FeatureRegistry::class );

		$state = [];
		foreach ( VisualEditorServiceProvider::AI_FEATURE_KEYS as $key ) {
			$state[ $key ] = null !== $registry->get( $key ) && $registry->isToggleOn( $key );
		}
		return $state;
	}

	/**
	 * Blade shell — hosts can override the view or inline the component
	 * without body. The default view is intentionally empty; this is a
	 * transport component, not a UI.
	 *
	 * @since 1.3.0
	 *
	 * @return string
	 */
	public function render(): string
	{
		return '<div class="ap-ve-ai-tools" data-testid="ap-ve-ai-tools"></div>';
	}

	/**
	 * Shared run-and-emit path. Kept private so callers can only reach
	 * agents through the five public entry points, each of which
	 * pre-shapes its input.
	 *
	 * @since 1.3.0
	 *
	 * @param  string   $featureKey  Feature key (for status events).
	 * @param  callable $callback    Callable that runs the agent and returns its output.
	 *
	 * @return void
	 */
	private function run( string $featureKey, callable $callback ): void
	{
		try {
			$output = $callback();

			$this->dispatch(
				sprintf( 'ap-ve-ai:%s:success', $featureKey ),
				feature: $featureKey,
				output: $output,
			);
		} catch ( FeatureDisabledException $e ) {
			$this->dispatch(
				sprintf( 'ap-ve-ai:%s:disabled', $featureKey ),
				feature: $featureKey,
				message: $e->getMessage(),
			);
		} catch ( MissingCredentialsException $e ) {
			$this->dispatch(
				sprintf( 'ap-ve-ai:%s:missing-credentials', $featureKey ),
				feature: $featureKey,
				message: $e->getMessage(),
			);
		} catch ( FeatureError $e ) {
			// FeatureError is a user-fixable validation problem (bad
			// input, empty required field, etc.). Emit under a distinct
			// event name so front-end listeners can route it to a
			// form-level warning instead of a generic error toast
			// (review #7). Mirrors the HTTP surface's 422 status.
			$this->dispatch(
				sprintf( 'ap-ve-ai:%s:invalid-input', $featureKey ),
				feature: $featureKey,
				message: $e->getMessage(),
			);
		} catch ( Throwable $e ) {
			Log::error( 'visual-editor AI trigger failed', [
				'feature' => $featureKey,
				'error'   => $e->getMessage(),
			] );

			$this->dispatch(
				sprintf( 'ap-ve-ai:%s:error', $featureKey ),
				feature: $featureKey,
				message: 'Unexpected error running AI feature.',
			);
		}
	}
}
