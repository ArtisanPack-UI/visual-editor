<?php

/**
 * Visual Editor Service Provider.
 *
 * Bootstraps the Visual Editor package by registering configuration,
 * views, and Blade components.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor;

use ArtisanPackUI\VisualEditor\View\Components;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Visual Editor package.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.0.0
 */
class VisualEditorServiceProvider extends ServiceProvider
{
	/**
	 * Blade components to register.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, class-string>
	 */
	protected array $bladeComponents = [
		'unit-control'      => Components\UnitControl::class,
		'box-control'       => Components\BoxControl::class,
		'alignment-control' => Components\AlignmentControl::class,
		'color-system'      => Components\ColorSystem::class,
		'link-control'      => Components\LinkControl::class,
		'range-control'     => Components\RangeControl::class,
		'angle-control'     => Components\AngleControl::class,
		'font-size-picker'  => Components\FontSizePicker::class,
		'border-control'    => Components\BorderControl::class,
	];

	/**
	 * Register any application services.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void
	{
		$this->mergeConfigFrom(
			__DIR__ . '/../config/visual-editor.php',
			'artisanpack-visual-editor-temp',
		);

		$this->app->singleton( 'visual-editor', function () {
			return new VisualEditor();
		} );
	}

	/**
	 * Bootstrap any application services.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function boot(): void
	{
		$this->mergeConfiguration();
		$this->publishConfiguration();
		$this->registerViews();
		$this->registerBladeComponents();
	}

	/**
	 * Merges the package's default configuration with the user's customizations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function mergeConfiguration(): void
	{
		$packageDefaults = config( 'artisanpack-visual-editor-temp', [] );
		$userConfig      = config( 'artisanpack.visual-editor', [] );
		$mergedConfig    = array_replace_recursive( $packageDefaults, $userConfig );

		config( [ 'artisanpack.visual-editor' => $mergedConfig ] );
	}

	/**
	 * Publish the configuration file.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function publishConfiguration(): void
	{
		if ( $this->app->runningInConsole() ) {
			$this->publishes( [
				__DIR__ . '/../config/visual-editor.php' => config_path( 'artisanpack/visual-editor.php' ),
			], 'artisanpack-visual-editor-config' );
		}
	}

	/**
	 * Register the package views.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerViews(): void
	{
		$this->loadViewsFrom( __DIR__ . '/../resources/views', 'visual-editor' );

		if ( $this->app->runningInConsole() ) {
			$this->publishes( [
				__DIR__ . '/../resources/views' => resource_path( 'views/vendor/visual-editor' ),
			], 'visual-editor-views' );
		}
	}

	/**
	 * Register all Blade components with the ve- prefix.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerBladeComponents(): void
	{
		$prefix = 've';

		foreach ( $this->bladeComponents as $alias => $class ) {
			Blade::component( $class, $prefix . '-' . $alias );
		}
	}
}
