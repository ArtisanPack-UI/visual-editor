<?php

/**
 * Service provider for the Blade renderer package.
 *
 * Wires the {@see BlockRenderer} singleton, registers the `<x-ve-blocks>`
 * and `<x-ve-template>` Blade components, and publishes the partial views
 * so host apps can override any individual block's markup.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade;

use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use ArtisanPackUI\VisualEditor\Resources\TemplatePartInliner;
use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\Responsive\ResponsiveValueResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\BreadcrumbsResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\LoginoutResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\SiteMetaResolver;
use ArtisanPackUI\VisualEditor\Animations\AnimationCssEmitter;
use ArtisanPackUI\VisualEditor\Animations\KeyframeRegistry;
use ArtisanPackUI\VisualEditorRendererBlade\Animations\AnimationMarkupResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Responsive\ResponsiveClassResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\AnimationCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesEmissionResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\BoxShadowCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GradientBorderCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\PositionCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\NavigationOverlayTracker;
use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\StateCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\ThemeJsonTokensCompiler;
use ArtisanPackUI\VisualEditorRendererBlade\View\Components\BlocksComponent;
use ArtisanPackUI\VisualEditorRendererBlade\View\Components\BlocksStylesComponent;
use ArtisanPackUI\VisualEditorRendererBlade\View\Components\TemplateComponent;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class VisualEditorRendererBladeServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		// Scoped (not singleton) so a long-lived worker (Octane / queue)
		// gets a fresh resolver per request scope. The resolver caches
		// its lookup; a singleton would leak stale settings across
		// requests served by the same worker.
		$this->app->scoped( SiteMetaResolver::class, function () {
			return new SiteMetaResolver();
		} );

		// Scoped to match SiteMetaResolver's lifetime — the resolver
		// reads the current request's auth state and current URL on
		// every call, so a long-lived worker (Octane / queue) must
		// not pin the first request's resolver. #522.
		$this->app->scoped( LoginoutResolver::class, function () {
			return new LoginoutResolver();
		} );

		// Scoped — the breadcrumbs resolver reads the current request's
		// URL when checking for the homepage short-circuit and walks the
		// in-flight post's parent chain. Pinning the first request's
		// resolver in a long-running worker (Octane / queue) would feed
		// stale request context to every subsequent render. #565.
		$this->app->scoped( BreadcrumbsResolver::class, function () {
			return new BreadcrumbsResolver();
		} );

		// Scoped (not singleton) so the BlockRenderer captures the
		// current request's SiteMetaResolver — also scoped — instead of
		// pinning the first request's resolver for the lifetime of a
		// long-running worker (Octane / queue) and serving stale site
		// meta on every subsequent request.
		$this->app->scoped( BlockRenderer::class, function ( $app ) {
			return new BlockRenderer(
				$app->make( ViewFactory::class ),
				$app->make( DynamicBlockRegistry::class ),
				$app->make( SiteMetaResolver::class ),
				$app->make( LoginoutResolver::class ),
			);
		} );

		$this->app->singleton( TemplatePartInliner::class, function () {
			return new TemplatePartInliner();
		} );

		$this->app->singleton( ThemeJsonTokensCompiler::class, function () {
			return new ThemeJsonTokensCompiler();
		} );

		$this->app->singleton( GlobalStylesEmissionResolver::class, function () {
			return new GlobalStylesEmissionResolver();
		} );

		// Scoped so a long-lived worker doesn't carry overlay state
		// across requests (Octane / queue). The tracker hands out
		// per-request DOM ids and gates the inline overlay toggle
		// script to fire at most once per response (Keystone #54).
		$this->app->scoped( NavigationOverlayTracker::class, function () {
			return new NavigationOverlayTracker();
		} );

		// #487 — server-side responsive class / @media emitter. Scoped
		// to match the lifetime of the registry it depends on.
		$this->app->scoped( ResponsiveClassResolver::class, function ( $app ) {
			return new ResponsiveClassResolver(
				$app->make( BreakpointRegistry::class ),
				$app->make( ResponsiveValueResolver::class ),
			);
		} );

		// #595 — flex layout serializer. Scoped so it shares lifetime
		// with the responsive registry / resolver it depends on.
		$this->app->scoped( \ArtisanPackUI\VisualEditorRendererBlade\Support\FlexSupport::class, function ( $app ) {
			return new \ArtisanPackUI\VisualEditorRendererBlade\Support\FlexSupport(
				$app->make( BreakpointRegistry::class ),
				$app->make( ResponsiveValueResolver::class ),
			);
		} );

		// #509 — per-request accumulator that collects every block's
		// responsive CSS into one `<style data-ve-responsive>` block
		// at the top of the render output. Scoped so a long-lived
		// worker (Octane / queue) doesn't leak rules across requests.
		$this->app->scoped( ResponsiveCssAccumulator::class, function () {
			return new ResponsiveCssAccumulator();
		} );

		// #488 — sibling accumulator for the state design tools'
		// `<style data-ve-states>` block. Same lifetime story as the
		// responsive accumulator above.
		$this->app->scoped( StateCssAccumulator::class, function () {
			return new StateCssAccumulator();
		} );

		// #489 — block-animations resolver + accumulator. Same lifetime
		// story as the responsive / state accumulators: scoped per
		// request so worker-runtime hosts don't leak across requests.
		$this->app->scoped( AnimationMarkupResolver::class, function ( $app ) {
			return new AnimationMarkupResolver(
				$app->make( AnimationCssEmitter::class ),
			);
		} );

		$this->app->scoped( AnimationCssAccumulator::class, function ( $app ) {
			return new AnimationCssAccumulator(
				$app->make( KeyframeRegistry::class ),
			);
		} );

		// #490 — sibling accumulator for the gradient border feature's
		// `<style data-ve-gradient-borders>` block. Same scoped lifetime
		// rationale as the others; the rules cover the wrapper +
		// `::before` mask pseudo a gradient border installs.
		$this->app->scoped( GradientBorderCssAccumulator::class, function () {
			return new GradientBorderCssAccumulator();
		} );

		// #607 — sibling accumulator for the box-shadow feature's
		// `<style data-ve-box-shadows>` block. Same scoped lifetime
		// rationale as the others; the rules cover stock `box-shadow`
		// declarations plus the `::before`/`::after` pseudo a gradient
		// shadow installs.
		$this->app->scoped( BoxShadowCssAccumulator::class, function () {
			return new BoxShadowCssAccumulator();
		} );

		// #640 — sibling accumulator for the CSS positioning feature's
		// `<style data-ve-position>` block.
		$this->app->scoped( PositionCssAccumulator::class, function () {
			return new PositionCssAccumulator();
		} );
	}

	public function boot(): void
	{
		$this->loadViewsFrom( __DIR__ . '/../resources/views', 'visual-editor-renderer-blade' );

		Blade::component( BlocksComponent::class, 've-blocks' );
		Blade::component( BlocksStylesComponent::class, 've-blocks-styles' );
		Blade::component( TemplateComponent::class, 've-template' );

		if ( $this->app->runningInConsole() ) {
			$this->publishes( [
				__DIR__ . '/../resources/views' => resource_path( 'views/vendor/visual-editor-renderer-blade' ),
			], 'visual-editor-blade-views' );

			// Asset publish path: copies the bundled `@wordpress/block-library`
			// CSS to the consumer's `public/vendor/visual-editor-renderer-blade/`,
			// which `<x-ve-blocks-styles />` links to by default. Also ships the
			// accordion + tabs front-end stylesheets and interactivity script
			// under `public/vendor/visual-editor-renderer-blade/frontend/`.
			$this->publishes( [
				__DIR__ . '/../resources/assets/block-library' => public_path( 'vendor/visual-editor-renderer-blade' ),
				__DIR__ . '/../resources/assets/frontend'      => public_path( 'vendor/visual-editor-renderer-blade/frontend' ),
			], 'visual-editor-renderer-blade-assets' );
		}
	}
}
