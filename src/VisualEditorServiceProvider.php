<?php

namespace ArtisanPackUI\VisualEditor;

use ArtisanPackUI\CMSFramework\Modules\Blog\Managers\BlogManager;
use ArtisanPackUI\VisualEditor\Blocks\Core\ArchivesBlock;
use ArtisanPackUI\VisualEditor\Blocks\Core\CategoriesBlock;
use ArtisanPackUI\VisualEditor\Blocks\Core\LatestPostsBlock;
use ArtisanPackUI\VisualEditor\Blocks\Core\TagCloudBlock;
use ArtisanPackUI\VisualEditor\Blocks\Forms\FormBlock;
use ArtisanPackUI\VisualEditor\MediaBridge\GutenbergAttachmentAdapter;
use ArtisanPackUI\VisualEditor\Services\Adapters\CmsFramework\CmsFrameworkQueryResolver;
use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\DenyByDefaultGate;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use ArtisanPackUI\VisualEditor\Models\VisualEditorPost;
use ArtisanPackUI\VisualEditor\Policies\VisualEditorPostPolicy;
use ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry;
use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use ArtisanPackUI\VisualEditor\Resources\QueryInliner;
use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use ArtisanPackUI\VisualEditor\Search\BlockTreeSearchExtractor;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\GlobalStylesResolver as SiteEditorGlobalStylesResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\MenuResolver as SiteEditorMenuResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\PatternResolver as SiteEditorPatternResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplatePartResolver as SiteEditorTemplatePartResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplateResolver as SiteEditorTemplateResolver;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesEmissionTracker;
use ArtisanPackUI\VisualEditor\View\Components\VisualEditorComponent;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
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

		// Bound here as a placeholder so other providers' register() phases
		// can typehint ResourceResolver. The boot() phase rebinds with the
		// final filter-merged map once all providers have registered their
		// `ap.visual-editor.resources` callbacks.
		$this->app->singleton( ResourceResolver::class, function () {
			return new ResourceResolver();
		} );

		// H5 — site-editor resolvers. Same pattern as ResourceResolver
		// above: empty placeholders here; boot() rebinds with the filter-
		// merged data once all providers have registered their
		// `ap.visual-editor.{templates,template-parts,patterns,
		// global-styles,navigation}` callbacks.
		$this->app->singleton( SiteEditorTemplateResolver::class, function () {
			return new SiteEditorTemplateResolver();
		} );

		$this->app->singleton( SiteEditorTemplatePartResolver::class, function () {
			return new SiteEditorTemplatePartResolver();
		} );

		$this->app->singleton( SiteEditorPatternResolver::class, function () {
			return new SiteEditorPatternResolver();
		} );

		$this->app->singleton( SiteEditorGlobalStylesResolver::class, function () {
			return new SiteEditorGlobalStylesResolver();
		} );

		$this->app->singleton( SiteEditorMenuResolver::class, function () {
			return new SiteEditorMenuResolver();
		} );

		// G4c-2 — `PostResolver` stamps `_resolved*` keys on `core/post-*`
		// blocks; `QueryInliner` orchestrates per-result expansion of
		// `core/query` blocks. Both are stateless so binding as singletons
		// is safe and lets host apps swap implementations cleanly.
		$this->app->singleton( PostResolver::class, function () {
			return new PostResolver();
		} );

		$this->app->singleton( QueryInliner::class, function ( $app ) {
			return new QueryInliner(
				$app,
				$app->make( PostResolver::class ),
			);
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

		// Scoped (not singleton) so a long-lived worker (Octane,
		// RoadRunner, queue) gets a fresh tracker per request scope.
		// A singleton would leak the "already emitted" flag across
		// requests and suppress the <style> block on every page after
		// the first one served by the worker.
		$this->app->scoped( GlobalStylesEmissionTracker::class, function () {
			return new GlobalStylesEmissionTracker();
		} );

		// #434: `GlobalStylesCssProvider` + `GlobalStylesCacheInvalidator`
		// were deleted with the rest of the plan-11 Phase D legacy.
		// The renderer-blade package's `<x-ve-blocks>` /
		// `<x-ve-template>` now delegate global-styles CSS emission to
		// cms-framework's `GlobalStylesEmitter` via a thin resolver in
		// the renderer-blade package; cache busting is event-driven
		// inside cms-framework itself.

		// Legacy alias for backward compatibility
		$this->app->alias( VisualEditor::class, 'visualEditor' );

		// G4c-2 — bind `QueryResolverContract` to cms-framework's
		// `QueryRuntime` adapter when the package is installed. Hosts
		// without cms-framework (or that ship a custom runtime) can
		// override this binding from their own service provider; the
		// `QueryResolveController` and `QueryInliner` only require that
		// *something* be bound.
		$this->registerQueryResolverBinding();

		// H7 (#432) — bind the fail-closed default access gate for
		// the site-editor shell route. `bindIf` is deliberate: a
		// consuming app that binds its own `SiteEditorAccessGate`
		// implementation (or one of the package-bundled gates such as
		// `CmsFrameworkInstallGate`) earlier in the boot order wins.
		// See `docs/site-editor-access-gate.md`.
		$this->app->bindIf( SiteEditorAccessGate::class, DenyByDefaultGate::class );

		$this->mergeConfigFrom(
			__DIR__ . '/../config/visual-editor.php', 'artisanpack-visual-editor-temp'
		);

	}

	/**
	 * Register the cms-framework adapter for {@see QueryResolverContract}
	 * when the upstream class is autoloadable.
	 *
	 * @since 1.0.0
	 */
	protected function registerQueryResolverBinding(): void
	{
		$cmsRuntime = '\\ArtisanPackUI\\CMSFramework\\Modules\\Blog\\Services\\QueryRuntime';

		if ( ! class_exists( $cmsRuntime ) ) {
			return;
		}

		$this->app->singleton( QueryResolverContract::class, function ( $app ) use ( $cmsRuntime ): QueryResolverContract {
			return new CmsFrameworkQueryResolver( $app->make( $cmsRuntime ) );
		} );
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

		// 1a. Build the resource map after every provider has finished
		//     booting so filter callbacks registered in another provider's
		//     boot() phase are visible regardless of provider ordering.
		//     Static config wins on key collision; host overrides always
		//     take precedence over filter contributions. See
		//     docs/plans/12-cms-framework-integration.md §4.1 for the full
		//     filter contract.
		$this->app->booted( function (): void {
			$this->registerResourceResolver();
			$this->registerSiteEditorResolvers();
		} );

		// 2. Load package views, routes, and migrations.
		$this->loadViewsFrom( __DIR__ . '/../resources/views', 'visual-editor' );
		$this->loadRoutesFrom( __DIR__ . '/../routes/web.php' );
		$this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );

		$this->registerApiRoutes();
		$this->registerBladeComponents();

		Gate::policy( VisualEditorPost::class, VisualEditorPostPolicy::class );

		// 3. Register core blocks from their block.json manifests.
		$this->registerCoreBlocks();

		// 4. Register the bundled reference custom block
		//    (`artisanpack/callout`). Demonstrates the auto-discovered
		//    block pattern for host apps and exercises the `artisanpack`
		//    category end-to-end.
		$this->registerReferenceBlocks();

		// 4a. G4b — register the three taxonomy/feed core blocks against
		//     cms-framework's term + post APIs. Gated on the package's
		//     presence so visual-editor still boots when cms-framework is
		//     absent; without it these blocks stay deferred and the
		//     deny-list keeps them out of the inserter.
		$this->registerTaxonomyAndArchiveBlocks();

		// 4b. Forms — register the `artisanpack/form` block against the
		//     artisanpack-ui/forms package. Gated on `Form::class` so
		//     visual-editor still boots when forms is absent; the block
		//     simply does not appear in the inserter in that case.
		$this->registerFormBlock();

		// 5. Tag the config file for the scaffold command.
		if ( $this->app->runningInConsole() ) {
			$this->publishes( [
								  __DIR__ . '/../config/visual-editor.php' => config_path( 'artisanpack/visual-editor.php' ),
							  ], 'artisanpack-package-config' );
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
		$blocksDir = __DIR__ . '/../resources/js/visual-editor/core-blocks';

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
	 * Registers the G4b dynamic blocks (`core/categories`, `core/tag-cloud`,
	 * `core/archives`) plus the I4 widgets-cluster fork
	 * (`artisanpack/latest-posts`) against cms-framework's term + post APIs.
	 *
	 * The forked `artisanpack/latest-posts` block also loads its bundled
	 * `block.json` so the inserter knows about it (the editor JS registers
	 * the edit component via auto-discovery; the PHP registry owns the
	 * server-side render + attribute schema).
	 *
	 * @since 1.0.0
	 */
	protected function registerTaxonomyAndArchiveBlocks(): void
	{
		if ( ! class_exists( BlogManager::class ) ) {
			return;
		}

		$editor = $this->app->make( VisualEditor::class );

		$editor->registerDynamicBlock( CategoriesBlock::class );
		$editor->registerDynamicBlock( TagCloudBlock::class );
		$editor->registerDynamicBlock( ArchivesBlock::class );

		$latestPostsBlockJson = __DIR__ . '/../resources/js/visual-editor/blocks/latest-posts/block.json';

		if ( file_exists( $latestPostsBlockJson ) ) {
			$editor->registerBlock( $latestPostsBlockJson );
		}

		$editor->registerDynamicBlock( LatestPostsBlock::class );
	}

	/**
	 * Registers the `artisanpack/form` dynamic block against the
	 * artisanpack-ui/forms package. Loads the bundled block.json so the
	 * inserter knows about it and the registry can hand attributes to
	 * the FormBlock's render() at publish time.
	 *
	 * @since 1.1.0
	 */
	protected function registerFormBlock(): void
	{
		if ( ! class_exists( \ArtisanPackUI\Forms\Models\Form::class ) ) {
			return;
		}

		$editor = $this->app->make( VisualEditor::class );

		$blockJsonPath = __DIR__ . '/../resources/js/visual-editor/blocks/form/block.json';

		if ( file_exists( $blockJsonPath ) ) {
			$editor->registerBlock( $blockJsonPath );
		}

		$editor->registerDynamicBlock( FormBlock::class );
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
	 * Builds the slug → model class map for ResourceResolver.
	 *
	 * Pipes the static config through the `ap.visual-editor.resources` filter
	 * (so packages like cms-framework can register their models at runtime),
	 * then merges static config back on top so host-app entries always win on
	 * key collision. ResourceResolver itself does not validate at construction
	 * — invalid classes only surface on first resolve, which keeps a
	 * standalone install of a contributor (cms-framework without visual-editor
	 * loaded) from tripping host boot.
	 *
	 * Public so tests (and edge cases that mutate config or hook callbacks at
	 * runtime) can re-trigger the rebind without going through reflection.
	 *
	 * @since 1.0.0
	 */
	public function registerResourceResolver(): void
	{
		$staticConfig = (array) config( 'artisanpack.visual-editor.resources', [] );
		$filtered     = applyFilters( 'ap.visual-editor.resources', $staticConfig );
		$filtered     = is_array( $filtered ) ? $filtered : [];

		// Static config wins on key collision: host app entries take
		// precedence over filter contributions.
		$resources = array_merge( $filtered, $staticConfig );

		$this->app->instance( ResourceResolver::class, new ResourceResolver( $resources ) );
	}

	/**
	 * Builds the five site-editor resolvers from filter-merged data.
	 *
	 * Mirrors {@see self::registerResourceResolver()}: each filter receives the
	 * static config, contributors (cms-framework H1–H4) merge their data in,
	 * static config wins on key collision. Resolvers store the merged shape
	 * verbatim — validation is deferred to first read so a misconfigured
	 * contributor surfaces an exception on the editor's first request, not at
	 * boot.
	 *
	 * Standalone visual-editor (no cms-framework, no host registrations) ends
	 * up with empty resolvers — the editor's site-editor surface boots clean
	 * and the editor renders with zero entities until something registers.
	 *
	 * Public so tests (and edge cases that mutate config or hook callbacks at
	 * runtime) can re-trigger the rebind without going through reflection.
	 *
	 * @since 1.0.0
	 */
	public function registerSiteEditorResolvers(): void
	{
		// Templates ─ array<string, array> keyed by slug.
		$templatesStatic = (array) config( 'artisanpack.visual-editor.site-editor.templates', [] );
		$templatesMerged = applyFilters( 'ap.visual-editor.templates', $templatesStatic );
		$templatesMerged = is_array( $templatesMerged ) ? $templatesMerged : [];
		$templatesMerged = array_merge( $templatesMerged, $templatesStatic );

		$this->app->instance(
			SiteEditorTemplateResolver::class,
			new SiteEditorTemplateResolver( $templatesMerged ),
		);

		// Template parts ─ array<string, array> keyed by slug.
		$partsStatic = (array) config( 'artisanpack.visual-editor.site-editor.template-parts', [] );
		$partsMerged = applyFilters( 'ap.visual-editor.template-parts', $partsStatic );
		$partsMerged = is_array( $partsMerged ) ? $partsMerged : [];
		$partsMerged = array_merge( $partsMerged, $partsStatic );

		$this->app->instance(
			SiteEditorTemplatePartResolver::class,
			new SiteEditorTemplatePartResolver( $partsMerged ),
		);

		// Patterns ─ array<string, array> keyed by slug.
		$patternsStatic = (array) config( 'artisanpack.visual-editor.site-editor.patterns', [] );
		$patternsMerged = applyFilters( 'ap.visual-editor.patterns', $patternsStatic );
		$patternsMerged = is_array( $patternsMerged ) ? $patternsMerged : [];
		$patternsMerged = array_merge( $patternsMerged, $patternsStatic );

		$this->app->instance(
			SiteEditorPatternResolver::class,
			new SiteEditorPatternResolver( $patternsMerged ),
		);

		// Global styles ─ singleton (?array). Static-config null-coalesces over
		// filter return so a host that sets static config wins outright; with no
		// static config, the filter result is authoritative.
		$globalStylesStatic = config( 'artisanpack.visual-editor.site-editor.global-styles', null );
		$globalStylesStatic = is_array( $globalStylesStatic ) ? $globalStylesStatic : null;
		$globalStylesMerged = applyFilters( 'ap.visual-editor.global-styles', $globalStylesStatic );
		$globalStylesMerged = is_array( $globalStylesMerged ) ? $globalStylesMerged : null;
		$globalStylesMerged = $globalStylesStatic ?? $globalStylesMerged;

		$this->app->instance(
			SiteEditorGlobalStylesResolver::class,
			new SiteEditorGlobalStylesResolver( $globalStylesMerged ),
		);

		// Navigation ─ array<string, array> keyed by location.
		$menusStatic = (array) config( 'artisanpack.visual-editor.site-editor.navigation', [] );
		$menusMerged = applyFilters( 'ap.visual-editor.navigation', $menusStatic );
		$menusMerged = is_array( $menusMerged ) ? $menusMerged : [];
		$menusMerged = array_merge( $menusMerged, $menusStatic );

		$this->app->instance(
			SiteEditorMenuResolver::class,
			new SiteEditorMenuResolver( $menusMerged ),
		);
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
