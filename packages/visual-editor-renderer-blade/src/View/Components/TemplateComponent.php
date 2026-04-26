<?php

/**
 * `<x-ve-template :slug="..." :theme="...">` Blade component.
 *
 * Resolves the most-specific {@see VisualEditorTemplate} for the given
 * slug (walking the WordPress-style fallback chain via
 * {@see TemplateResolver}) and renders its block tree to HTML, inlining
 * any `core/template-part` references along the way.
 *
 * Behaviour when no template matches:
 * - In production the component renders an empty wrapper so the surrounding
 *   layout stays intact.
 * - In any non-production environment a visible HTML comment surfaces the
 *   resolution failure so developers see the misconfiguration during a
 *   browser refresh.
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

use ArtisanPackUI\VisualEditor\Resources\TemplatePartInliner;
use ArtisanPackUI\VisualEditor\Resources\TemplateResolver;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesCssProvider;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesEmissionTracker;
use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class TemplateComponent extends Component
{
	public string $slug;

	public ?string $theme;

	/**
	 * @var array<int, string>
	 */
	public array $fallbackChain;

	public ?string $matchedSlug;

	public ?string $resolutionError;

	public string $html;

	public function __construct(
		protected BlockRenderer $renderer,
		protected TemplateResolver $templates,
		protected TemplatePartInliner $inliner,
		protected Application $app,
		protected GlobalStylesCssProvider $globalStyles,
		protected GlobalStylesEmissionTracker $emissionTracker,
		string $slug,
		?string $theme = null,
	) {
		$this->slug          = $slug;
		$this->theme         = $theme;
		$this->fallbackChain = $templates->fallbackChain( $slug );

		$template = $templates->forSlug( $slug, $theme );

		if ( null === $template ) {
			$this->matchedSlug     = null;
			$this->resolutionError = 'no-matching-template';
			$this->html            = '';

			return;
		}

		$this->matchedSlug     = $template->slug;
		$this->resolutionError = null;

		$inlined    = $this->inliner->inline( $template->getBlocks(), $theme ?? $template->theme );
		$this->html = $renderer->render( $inlined );
	}

	public function render(): View
	{
		return view( 'visual-editor-renderer-blade::components.template', [
			'slug'            => $this->slug,
			'theme'           => $this->theme,
			'matchedSlug'     => $this->matchedSlug,
			'fallbackChain'   => $this->fallbackChain,
			'resolutionError' => $this->resolutionError,
			'inDev'           => ! $this->app->environment( 'production' ),
			'html'            => $this->html,
			'globalStylesCss' => $this->resolveGlobalStylesCss(),
		] );
	}

	/**
	 * Returns the compiled global-styles CSS the first time a renderer
	 * fires in the current request, then null on every subsequent call —
	 * matching the dedupe behaviour of `<x-ve-blocks>` so a layout that
	 * combines the two does not emit `<style>` twice.
	 *
	 * @since 1.0.0
	 */
	protected function resolveGlobalStylesCss(): ?string
	{
		if ( $this->emissionTracker->hasEmitted() ) {
			return null;
		}

		$this->emissionTracker->markEmitted();

		return $this->globalStyles->css( $this->theme );
	}
}
