<?php

/**
 * `<x-ve-blocks :tree="...">` Blade component.
 *
 * Renders a saved visual editor block tree into HTML using the
 * {@see \ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer} engine.
 * Resolves any `core/template-part` blocks in the tree inline (via the
 * shared {@see TemplatePartInliner}) and any `core/block`
 * (synced-pattern) references via {@see PatternInliner} so a single
 * render pass produces the final markup. Pass `:resolve-parts="false"`
 * or `:resolve-patterns="false"` to opt out of either resolution step
 * and render the raw tree.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\View\Components;

use ArtisanPackUI\VisualEditor\Resources\PatternInliner;
use ArtisanPackUI\VisualEditor\Resources\QueryInliner;
use ArtisanPackUI\VisualEditor\Resources\TemplatePartInliner;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesEmissionTracker;
use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesEmissionResolver;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class BlocksComponent extends Component
{
	/**
	 * Normalized block tree ready for rendering.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $tree;

	/**
	 * Theme slug forwarded to the global-styles provider so a block tree
	 * rendered against a non-default theme picks up that theme's record.
	 */
	protected ?string $defaultTheme;

	public function __construct(
		protected BlockRenderer $renderer,
		protected TemplatePartInliner $inliner,
		protected PatternInliner $patternInliner,
		protected QueryInliner $queryInliner,
		protected GlobalStylesEmissionResolver $globalStyles,
		protected GlobalStylesEmissionTracker $emissionTracker,
		mixed $tree = null,
		?string $defaultTheme = null,
		bool $resolveParts = true,
		bool $resolvePatterns = true,
		bool $resolveQueries = true,
	) {
		$this->defaultTheme = $defaultTheme;

		$normalized = $this->normalizeTree( $tree );

		$resolved = $resolveParts
			? $this->inliner->inline( $normalized, $defaultTheme )
			: $normalized;

		// Pattern inlining runs after template-part inlining so a part
		// that itself contains a synced-pattern reference resolves in the
		// same pass.
		$resolved = $resolvePatterns
			? $this->patternInliner->inline( $resolved )
			: $resolved;

		// Query inlining runs last so a `core/query` block inside a
		// resolved template part / pattern still gets its loop expanded.
		$this->tree = $resolveQueries
			? $this->queryInliner->inline( $resolved )
			: $resolved;
	}

	public function render(): View
	{
		return view( 'visual-editor-renderer-blade::components.blocks', [
			'html'            => $this->renderer->render( $this->tree ),
			'globalStylesCss' => $this->resolveGlobalStylesCss(),
		] );
	}

	/**
	 * Returns the compiled global-styles CSS the first time a renderer
	 * fires in the current request, then null on every subsequent call —
	 * so a page with multiple `<x-ve-blocks>` / `<x-ve-template>`
	 * instances emits the `<style>` block exactly once.
	 *
	 * @since 1.0.0
	 */
	protected function resolveGlobalStylesCss(): ?string
	{
		if ( $this->emissionTracker->hasEmitted() ) {
			return null;
		}

		$this->emissionTracker->markEmitted();

		$css = $this->globalStyles->emit();

		return '' === $css ? null : $css;
	}

	/**
	 * Accept the `tree` prop in any common Laravel shape (array, JSON string,
	 * Arrayable, stdClass) and coerce it to a list of block arrays.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function normalizeTree( mixed $tree ): array
	{
		if ( null === $tree ) {
			return [];
		}

		if ( is_string( $tree ) ) {
			$decoded = json_decode( $tree, true );

			$tree = is_array( $decoded ) ? $decoded : [];
		}

		if ( is_object( $tree ) && method_exists( $tree, 'toArray' ) ) {
			/** @var array<mixed> $tree */
			$tree = $tree->toArray();
		}

		if ( ! is_array( $tree ) ) {
			return [];
		}

		$normalized = [];

		foreach ( $tree as $block ) {
			if ( is_array( $block ) ) {
				$normalized[] = $block;
			}
		}

		return $normalized;
	}
}
