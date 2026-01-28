<?php

declare( strict_types=1 );

/**
 * Visual Editor Service Provider
 *
 * Bootstraps the Visual Editor package by registering configuration,
 * views, migrations, routes, and services.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.0.0
 */

namespace ArtisanPackUI\VisualEditor;

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;
use ArtisanPackUI\VisualEditor\Registries\SectionRegistry;
use ArtisanPackUI\VisualEditor\Registries\TemplateRegistry;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesManager;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Visual Editor package.
 *
 * Bootstraps the Visual Editor by registering configuration, views,
 * database migrations, and routes. Configuration is merged into
 * the main artisanpack.php config file following the ArtisanPack UI
 * package conventions.
 *
 * @since 1.0.0
 */
class VisualEditorServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 *
	 * This method merges the package's local configuration into a temporary key.
	 * The `boot` method will then handle merging this into the main `artisanpack` config.
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

		// Register the main Visual Editor singleton
		$this->app->singleton( 'visual-editor', function () {
			return new VisualEditor();
		} );

		// Register registries as singletons
		$this->app->singleton( BlockRegistry::class );
		$this->app->singleton( SectionRegistry::class );
		$this->app->singleton( TemplateRegistry::class );

		// Register services as singletons
		$this->app->singleton( GlobalStylesManager::class );
	}

	/**
	 * Bootstrap any application services.
	 *
	 * This method publishes the configuration, merges it into the main `artisanpack`
	 * config array, registers views, loads database migrations, and boots registries.
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
		$this->loadMigrations();
		$this->registerRoutes();
		$this->bootRegistries();
	}

	/**
	 * Merges the package's default configuration with the user's customizations.
	 *
	 * This method ensures that the user's settings under the 'visual-editor' key
	 * in `config/artisanpack.php` take precedence over the package's default values.
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
	 * Publish the configuration file to the application's config directory.
	 *
	 * Configuration will be published to config/artisanpack/visual-editor.php to maintain
	 * the unified ArtisanPack UI configuration structure.
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
			], 'artisanpack-package-config' );

			$this->publishes( [
				__DIR__ . '/../config/visual-editor.php' => config_path( 'artisanpack/visual-editor.php' ),
			], 'visual-editor-config' );
		}
	}

	/**
	 * Register the Visual Editor views.
	 *
	 * Publishes views to the application's resources path and loads views
	 * from both the published and package source paths.
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
	 * Load the package's database migrations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function loadMigrations(): void
	{
		$migrationsPath = __DIR__ . '/../database/migrations';

		if ( is_dir( $migrationsPath ) ) {
			$this->loadMigrationsFrom( $migrationsPath );
		}
	}

	/**
	 * Register the Visual Editor routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerRoutes(): void
	{
		$routesPath = __DIR__ . '/../routes/web.php';

		if ( file_exists( $routesPath ) ) {
			$this->loadRoutesFrom( $routesPath );
		}
	}

	/**
	 * Boot the registries with default blocks, sections, and templates.
	 *
	 * After registering defaults, the `ve.blocks.register` hooks filter
	 * is applied (when the artisanpack-ui/hooks package is installed)
	 * to allow third-party packages and applications to register
	 * additional blocks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function bootRegistries(): void
	{
		// Register default blocks
		$blockRegistry = $this->app->make( BlockRegistry::class );
		$blockRegistry->registerDefaults();

		// Allow third-party block registration via hooks filter
		if ( function_exists( 'applyFilters' ) ) {
			applyFilters( 've.blocks.register', $blockRegistry );
		}

		// Register default sections
		$this->app->make( SectionRegistry::class )->registerDefaults();

		// Register default templates
		$this->app->make( TemplateRegistry::class )->registerDefaults();
	}
}
