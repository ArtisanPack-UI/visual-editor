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
use ArtisanPackUI\VisualEditorRendererBlade\View\Components\BlocksComponent;
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

		$this->app->singleton( BlockRenderer::class, function ( $app ) {
			return new BlockRenderer(
				$app->make( ViewFactory::class ),
				$app->make( DynamicBlockRegistry::class ),
				$app->make( SiteMetaResolver::class ),
			);
		} );

		$this->app->singleton( TemplatePartInliner::class, function () {
			return new TemplatePartInliner();
		} );
	}

	public function boot(): void
	{
		$this->loadViewsFrom( __DIR__ . '/../resources/views', 'visual-editor-renderer-blade' );

		Blade::component( BlocksComponent::class, 've-blocks' );
		Blade::component( TemplateComponent::class, 've-template' );

		if ( $this->app->runningInConsole() ) {
			$this->publishes( [
				__DIR__ . '/../resources/views' => resource_path( 'views/vendor/visual-editor-renderer-blade' ),
			], 'visual-editor-blade-views' );
		}
	}
}
