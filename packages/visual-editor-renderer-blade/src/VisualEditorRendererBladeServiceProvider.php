<?php

/**
 * Service provider for the Blade renderer package.
 *
 * Wires the {@see BlockRenderer} singleton, registers the `<x-ve-blocks>`
 * Blade component, and publishes the partial views so host apps can override
 * any individual block's markup.
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
use ArtisanPackUI\VisualEditorRendererBlade\View\Components\BlocksComponent;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class VisualEditorRendererBladeServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton( BlockRenderer::class, function ( $app ) {
			return new BlockRenderer(
				$app->make( ViewFactory::class ),
				$app->make( DynamicBlockRegistry::class ),
			);
		} );
	}

	public function boot(): void
	{
		$this->loadViewsFrom( __DIR__ . '/../resources/views', 'visual-editor-renderer-blade' );

		Blade::component( BlocksComponent::class, 've-blocks' );

		if ( $this->app->runningInConsole() ) {
			$this->publishes( [
				__DIR__ . '/../resources/views' => resource_path( 'views/vendor/visual-editor-renderer-blade' ),
			], 'visual-editor-blade-views' );
		}
	}
}
