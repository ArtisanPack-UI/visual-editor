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
use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\SiteMetaResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesEmissionResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\NavigationOverlayTracker;
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
			// which `<x-ve-blocks-styles />` links to by default.
			$this->publishes( [
				__DIR__ . '/../resources/assets/block-library' => public_path( 'vendor/visual-editor-renderer-blade' ),
			], 'visual-editor-renderer-blade-assets' );
		}
	}
}
