<?php

namespace ArtisanPackUI\VisualEditor;

use ArtisanPackUI\VisualEditor\Console\Commands\SeedSampleContentCommand;
use ArtisanPackUI\VisualEditor\MediaBridge\GutenbergAttachmentAdapter;
use ArtisanPackUI\VisualEditor\Models\VisualEditorGlobalStyles;
use ArtisanPackUI\VisualEditor\Models\VisualEditorNavigation;
use ArtisanPackUI\VisualEditor\Models\VisualEditorPattern;
use ArtisanPackUI\VisualEditor\Models\VisualEditorPost;
use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;
use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplatePart;
use ArtisanPackUI\VisualEditor\Policies\VisualEditorGlobalStylesPolicy;
use ArtisanPackUI\VisualEditor\Policies\VisualEditorNavigationPolicy;
use ArtisanPackUI\VisualEditor\Policies\VisualEditorPatternPolicy;
use ArtisanPackUI\VisualEditor\Policies\VisualEditorPostPolicy;
use ArtisanPackUI\VisualEditor\Policies\VisualEditorTemplatePolicy;
use ArtisanPackUI\VisualEditor\Policies\VisualEditorTemplatePartPolicy;
use ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry;
use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use ArtisanPackUI\VisualEditor\Search\BlockTreeSearchExtractor;
use ArtisanPackUI\VisualEditor\Services\MenuLocationResolver;
use ArtisanPackUI\VisualEditor\View\Components\VisualEditorComponent;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class VisualEditorServiceProvider extends ServiceProvider
{

	public function register(): void
	{
		$this->app->singleton( BlockTypeRegistry::class, function () {
			return new BlockTypeRegistry();
		} );

		$this->app->singleton( DynamicBlockRegistry::class, function () {
			return new DynamicBlockRegistry();
		} );

		$this->app->singleton( VisualEditor::class, function ( $app ) {
			return new VisualEditor(
				$app->make( BlockTypeRegistry::class ),
				$app->make( DynamicBlockRegistry::class ),
			);
		} );

		$this->app->singleton( ResourceResolver::class, function () {
			return new ResourceResolver();
		} );

		$this->app->singleton( BlockTreeSearchExtractor::class, function ( $app ) {
			return new BlockTreeSearchExtractor(
				$app->make( DynamicBlockRegistry::class )
			);
		} );

		// The adapter is stateless and safe to share. Hosts that need a
		// subclass (e.g. to append custom fields to the attachment shape)
		// can rebind it via the container.
		$this->app->singleton( GutenbergAttachmentAdapter::class, function () {
			return new GutenbergAttachmentAdapter();
		} );

		$this->app->singleton( MenuLocationResolver::class, function ( $app ) {
			return new MenuLocationResolver( $app['config'] );
		} );

		// Legacy alias for backward compatibility
		$this->app->alias( VisualEditor::class, 'visualEditor' );

		$this->mergeConfigFrom(
			__DIR__ . '/../config/visual-editor.php', 'artisanpack-visual-editor-temp'
		);

	}

	/**
	 * Perform post-registration booting of services.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void
	{
		// 1. Merge the configuration correctly.
		$this->mergeConfiguration();

		// 2. Load package views, routes, and migrations.
		$this->loadViewsFrom( __DIR__ . '/../resources/views', 'visual-editor' );
		$this->loadRoutesFrom( __DIR__ . '/../routes/web.php' );
		$this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );

		$this->registerApiRoutes();
		$this->registerBladeComponents();

		Gate::policy( VisualEditorPost::class, VisualEditorPostPolicy::class );
		Gate::policy( VisualEditorTemplate::class, VisualEditorTemplatePolicy::class );
		Gate::policy( VisualEditorTemplatePart::class, VisualEditorTemplatePartPolicy::class );
		Gate::policy( VisualEditorGlobalStyles::class, VisualEditorGlobalStylesPolicy::class );
		Gate::policy( VisualEditorNavigation::class, VisualEditorNavigationPolicy::class );
		Gate::policy( VisualEditorPattern::class, VisualEditorPatternPolicy::class );

		// 3. Register core blocks from their block.json manifests.
		$this->registerCoreBlocks();

		// 4. Register the bundled reference custom block
		//    (`artisanpack/callout`). Demonstrates the auto-discovered
		//    block pattern for host apps and exercises the `artisanpack`
		//    category end-to-end.
		$this->registerReferenceBlocks();

		// 5. Tag the config file for the scaffold command.
		if ( $this->app->runningInConsole() ) {
			$this->publishes( [
								  __DIR__ . '/../config/visual-editor.php' => config_path( 'artisanpack/visual-editor.php' ),
							  ], 'artisanpack-package-config' );

			$this->commands( [
				SeedSampleContentCommand::class,
			] );
		}
	}

	/**
	 * Registers the core block types from their block.json manifest files.
	 *
	 * @since 1.0.0
	 */
	protected function registerCoreBlocks(): void
	{
		$editor    = $this->app->make( VisualEditor::class );
		// Legacy block.json manifests — retained under _legacy/ during the
		// Gutenberg adoption (see docs/gutenberg-adoption.md and issue #309).
		// M5 will revisit which of these survive as enabled-by-default.
		$blocksDir = __DIR__ . '/../resources/js/visual-editor/_legacy/editor/blocks';

		$coreBlocks = [
			'paragraph',
			'heading',
			'list',
			'quote',
			'code',
			'preformatted',
		];

		foreach ( $coreBlocks as $block ) {
			$blockJsonPath = $blocksDir . '/' . $block . '/block.json';

			if ( file_exists( $blockJsonPath ) ) {
				$editor->registerBlock( $blockJsonPath );
			}
		}
	}

	/**
	 * Registers the bundled reference blocks from the
	 * `resources/js/visual-editor/blocks/` directory.
	 *
	 * Keeping the path discovery in PHP (rather than trusting the JS
	 * auto-discovery glob alone) means the server-side registry knows
	 * about the block — its metadata flows into `getEnabledBlockNames()`
	 * and into anything that reads the registry — even before the editor
	 * bundle loads.
	 *
	 * @since 1.0.0
	 */
	protected function registerReferenceBlocks(): void
	{
		$editor    = $this->app->make( VisualEditor::class );
		$blocksDir = __DIR__ . '/../resources/js/visual-editor/blocks';

		$referenceBlocks = [
			'callout',
		];

		foreach ( $referenceBlocks as $block ) {
			$blockJsonPath = $blocksDir . '/' . $block . '/block.json';

			if ( file_exists( $blockJsonPath ) ) {
				$editor->registerBlock( $blockJsonPath );
			}
		}
	}

	/**
	 * Registers the package API routes under the `/visual-editor/api` prefix.
	 *
	 * @since 1.0.0
	 */
	protected function registerApiRoutes(): void
	{
		$middleware = (array) config(
			'artisanpack.visual-editor.api.middleware',
			['api', 'auth']
		);

		Route::middleware( $middleware )
			->prefix( 'visual-editor/api' )
			->group( __DIR__ . '/../routes/api.php' );
	}

	/**
	 * Registers package Blade components.
	 *
	 * @since 1.0.0
	 */
	protected function registerBladeComponents(): void
	{
		Blade::component( VisualEditorComponent::class, 'visual-editor' );
	}


	/**
	 * Merges the package's default configuration with the user's customizations.
	 *
	 * This method ensures that the user's settings in `config/artisanpack.php`
	 * take precedence over the package's default values.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function mergeConfiguration(): void
	{
		// Get the package's default configuration.
		$packageDefaults = config( 'artisanpack-visual-editor-temp', [] );

		// Get the user's custom configuration from config/artisanpack.php.
		$userConfig = config( 'artisanpack.visual-editor', [] );

		// Merge them, with the user's config overwriting the defaults.
		$mergedConfig = array_replace_recursive( $packageDefaults, $userConfig );

		// Set the final, correctly merged configuration.
		config( [ 'artisanpack.visual-editor' => $mergedConfig ] );
	}

}
