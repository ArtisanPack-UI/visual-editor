<?php

/**
 * `<x-ve-template :slug="..." :theme="...">` Blade component.
 *
 * Resolves the most-specific template entity for the given slug,
 * walking a WordPress-style fallback chain (`single-page-home` →
 * `single-page` → `single` → `index`) against cms-framework's
 * `TemplateResolver`. Renders the resolved entity's block tree to
 * HTML through the visual-editor block renderer, inlining any
 * `core/template-part` or `core/block` references along the way.
 *
 * Behaviour when no template matches:
 * - In production the component renders an empty wrapper so the
 *   surrounding layout stays intact.
 * - In any non-production environment a visible HTML comment surfaces
 *   the resolution failure so developers see the misconfiguration
 *   during a browser refresh.
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
use ArtisanPackUI\VisualEditor\Resources\TemplatePartInliner;
use ArtisanPackUI\VisualEditor\SiteEditor\NavigationBlockRefResolver;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesEmissionTracker;
use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use ArtisanPackUI\VisualEditorRendererBlade\Services\AnimationCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesEmissionResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GradientBorderCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\StateCssAccumulator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class TemplateComponent extends Component
{
	/**
	 * cms-framework's TemplateResolver. Lookups go through it when the
	 * package is installed; without cms-framework the component
	 * resolves to no template at all (Phase H install gate is the user-
	 * facing surface).
	 */
	protected const RESOLVER_CLASS = '\\ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplateResolver';

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
		protected TemplatePartInliner $inliner,
		protected PatternInliner $patternInliner,
		protected NavigationBlockRefResolver $navigationResolver,
		protected Application $app,
		protected GlobalStylesEmissionResolver $globalStyles,
		protected GlobalStylesEmissionTracker $emissionTracker,
		protected ResponsiveCssAccumulator $responsiveAccumulator,
		protected StateCssAccumulator $stateAccumulator,
		protected AnimationCssAccumulator $animationAccumulator,
		protected GradientBorderCssAccumulator $gradientBorderAccumulator,
		string $slug,
		?string $theme = null,
	) {
		$this->slug          = $slug;
		$this->theme         = $theme;
		$this->fallbackChain = $this->buildFallbackChain( $slug );

		[ $matchedSlug, $blocks ] = $this->resolveTemplate( $this->fallbackChain );

		if ( null === $matchedSlug || null === $blocks ) {
			$this->matchedSlug     = null;
			$this->resolutionError = 'no-matching-template';
			$this->html            = '';

			return;
		}

		$this->matchedSlug     = $matchedSlug;
		$this->resolutionError = null;

		$inlinedParts    = $this->inliner->inline( $blocks, $theme );
		$inlinedPatterns = $this->patternInliner->inline( $inlinedParts );

		// Navigation resolution — see `BlocksComponent` for the
		// full rationale. Keystone #51 (the front-end pair to #48).
		$resolvedNav = null !== $theme
			? $this->navigationResolver->resolve( $inlinedPatterns, $theme )
			: $inlinedPatterns;

		$this->html = $renderer->render( $resolvedNav );
	}

	public function render(): View
	{
		// Drain the per-request responsive CSS accumulator. The
		// constructor already invoked `$renderer->render()` (line
		// where `$this->html` is assigned), so every block partial
		// has had a chance to push its responsive rules in by now.
		// See BlocksComponent::render() for the same pattern.
		$responsiveCss      = $this->responsiveAccumulator->flush();
		$statesCss          = $this->stateAccumulator->flush();
		$animationOutput    = $this->animationAccumulator->flush();
		$gradientBordersCss = $this->gradientBorderAccumulator->flush();

		return view( 'visual-editor-renderer-blade::components.template', [
			'slug'                    => $this->slug,
			'theme'                   => $this->theme,
			'matchedSlug'             => $this->matchedSlug,
			'fallbackChain'           => $this->fallbackChain,
			'resolutionError'         => $this->resolutionError,
			'inDev'                   => ! $this->app->environment( 'production' ),
			'html'                    => $this->html,
			'globalStylesCss'         => $this->resolveGlobalStylesCss(),
			'responsiveCss'           => $responsiveCss,
			'statesCss'               => $statesCss,
			'animationsCss'           => $animationOutput['styleTag'],
			'animationsNoscript'      => $animationOutput['noscriptTag'],
			'animationsRuntimeNeeded' => $animationOutput['runtimeNeeded'],
			'gradientBordersCss'      => $gradientBordersCss,
		] );
	}

	/**
	 * Build the ordered slug chain the resolver walks. Mirrors the
	 * legacy visual-editor `TemplateResolver::fallbackChain()`
	 * behaviour so existing call sites see no surface change.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function buildFallbackChain( string $slug ): array
	{
		$chain = [ $slug ];

		if ( 'single' !== $slug && str_starts_with( $slug, 'single-' ) ) {
			$chain[] = 'single';
		} elseif ( 'page' !== $slug && str_starts_with( $slug, 'page-' ) ) {
			$chain[] = 'page';
		}

		if ( 'index' !== $slug ) {
			$chain[] = 'index';
		}

		return $chain;
	}

	/**
	 * Walk the fallback chain and return the first matched slug + its
	 * resolved block tree. Returns `[null, null]` when nothing matches
	 * or cms-framework isn't installed.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, string>  $chain
	 * @return array{0: ?string, 1: ?array<int, array<string, mixed>>}
	 */
	protected function resolveTemplate( array $chain ): array
	{
		if ( ! class_exists( self::RESOLVER_CLASS ) ) {
			return [ null, null ];
		}

		$resolver = app( self::RESOLVER_CLASS );

		foreach ( $chain as $candidate ) {
			$entity = $resolver->resolve( $candidate );

			if ( null === $entity ) {
				continue;
			}

			$blocks = $entity->blocks ?? null;

			if ( ! is_array( $blocks ) ) {
				continue;
			}

			return [ (string) ( $entity->slug ?? $candidate ), $blocks ];
		}

		return [ null, null ];
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

		$css = $this->globalStyles->emit();

		return '' === $css ? null : $css;
	}
}
