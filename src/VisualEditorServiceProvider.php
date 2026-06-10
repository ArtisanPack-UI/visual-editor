<?php

namespace ArtisanPackUI\VisualEditor;

use ArtisanPackUI\CMSFramework\Modules\Blog\Managers\BlogManager;
use ArtisanPackUI\VisualEditor\Blocks\Core\ArchivesBlock;
use ArtisanPackUI\VisualEditor\Blocks\Core\CategoriesBlock;
use ArtisanPackUI\VisualEditor\Blocks\Core\LatestPostsBlock;
use ArtisanPackUI\VisualEditor\Blocks\Core\TagCloudBlock;
use ArtisanPackUI\VisualEditor\Blocks\Forms\FormBlock;
use ArtisanPackUI\Icons\Registries\IconSetRegistration;
use ArtisanPackUI\VisualEditor\Blocks\Icon\IconBlock;
use ArtisanPackUI\VisualEditor\Services\Icon\FontAwesomeFreeIconSets;
use ArtisanPackUI\VisualEditor\Services\Icon\IconCatalog;
use ArtisanPackUI\VisualEditor\Services\Icon\IconSvgResolver;
use ArtisanPackUI\VisualEditor\Services\Icon\SvgSanitizer;
use ArtisanPackUI\VisualEditor\MediaBridge\GutenbergAttachmentAdapter;
use ArtisanPackUI\VisualEditor\Services\Adapters\CmsFramework\CmsFrameworkQueryResolver;
use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\DenyByDefaultGate;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use ArtisanPackUI\VisualEditor\Models\VisualEditorPost;
use ArtisanPackUI\VisualEditor\Policies\VisualEditorPostPolicy;
use ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry;
use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use ArtisanPackUI\VisualEditor\Console\AuditBreakpointsCommand;
use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use ArtisanPackUI\VisualEditor\Resources\CommentInliner;
use ArtisanPackUI\VisualEditor\Resources\CommentResolver;
use ArtisanPackUI\VisualEditor\Resources\QueryInliner;
use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use ArtisanPackUI\VisualEditor\Responsive\AttributeMigrator;
use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\Responsive\ResponsiveValueResolver;
use ArtisanPackUI\VisualEditor\States\StateAttributeMigrator;
use ArtisanPackUI\VisualEditor\States\StateCssEmitter;
use ArtisanPackUI\VisualEditor\States\StateRegistry;
use ArtisanPackUI\VisualEditor\States\StateValueResolver;
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

		// Icon Block Phase 1 (#552): the sanitizer is stateless, so bind
		// it as a shared singleton — IconBlock and any future consumers
		// (the admin-upload pipeline in Phase 6 #557) can reuse one copy.
		$this->app->singleton( SvgSanitizer::class, function () {
			return new SvgSanitizer();
		} );

		// Icon Block Phase 3 (#554): defer the icon-sets-registry walk
		// until the first `resolve()` call. IconBlock is constructed
		// inside boot() (via registerReferenceBlocks), which happens
		// BEFORE every provider's `addFilter('ap.icons.register-icon-sets',
		// …)` has fired. Computing the path map eagerly here would race
		// against those registrations and produce an empty resolver. The
		// closure runs at request time, by which point boot is finished
		// and the filter chain is complete.
		$this->app->singleton( IconSvgResolver::class, function (): IconSvgResolver {
			return new IconSvgResolver( static function (): array {
				if ( ! class_exists( IconSetRegistration::class ) || ! function_exists( 'applyFilters' ) ) {
					return [];
				}

				$registry = applyFilters( 'ap.icons.register-icon-sets', new IconSetRegistration() );
				if ( ! $registry instanceof IconSetRegistration ) {
					return [];
				}

				$paths = [];
				foreach ( $registry->getSets() as $prefix => $details ) {
					$path = $details['path'] ?? null;
					if ( is_string( $path ) && '' !== $path ) {
						$paths[ (string) $prefix ] = $path;
					}
				}

				return $paths;
			} );
		} );

		// Icon Block Phase 4 (#555): the picker's search + sets endpoints
		// resolve the catalog out of the container so host apps can swap
		// in a custom manifest (e.g. extending the bundled FA Free set
		// with their own brand icons) via `$app->extend()`.
		$this->app->singleton( IconCatalog::class, function (): IconCatalog {
			return new IconCatalog();
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

		// #519 — `CommentResolver` stamps `_resolved*` keys on
		// `comment-*` blocks; `CommentInliner` orchestrates the
		// per-comment expansion of `artisanpack/comments` blocks.
		// Both are stateless so binding as singletons is safe.
		$this->app->singleton( CommentResolver::class, function () {
			return new CommentResolver();
		} );

		$this->app->singleton( CommentInliner::class, function ( $app ) {
			return new CommentInliner(
				$app->make( CommentResolver::class ),
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

		// #487 — responsive design tools. The registry is scoped per
		// request because theme.json overrides can change between
		// requests when the host swaps themes; singletons would leak
		// the resolved registry across requests. The resolver and
		// migrator are stateless wrappers and could be singletons, but
		// scoping them keeps lifetimes uniform with the registry they
		// depend on.
		$this->app->scoped( BreakpointRegistry::class, function ( $app ) {
			$config = (array) $app['config']->get( 'artisanpack.visual-editor.breakpoints', [] );

			return BreakpointRegistry::fromLayers( $config );
		} );

		$this->app->scoped( ResponsiveValueResolver::class, function ( $app ) {
			return new ResponsiveValueResolver( $app->make( BreakpointRegistry::class ) );
		} );

		$this->app->singleton( AttributeMigrator::class, function () {
			return new AttributeMigrator();
		} );

		// #488 — state design tools. Same lifetime story as the
		// breakpoint registry: scoped per request because theme.json
		// can swap a state set between requests, and singletons would
		// leak the resolved registry across them.
		$this->app->scoped( StateRegistry::class, function ( $app ) {
			$config = (array) $app['config']->get( 'artisanpack.visual-editor.states', [] );

			return StateRegistry::fromLayers( $config );
		} );

		$this->app->scoped( StateValueResolver::class, function ( $app ) {
			return new StateValueResolver( $app->make( StateRegistry::class ) );
		} );

		$this->app->scoped( StateCssEmitter::class, function ( $app ) {
			return new StateCssEmitter(
				$app->make( StateRegistry::class ),
				$app->make( StateValueResolver::class ),
			);
		} );

		$this->app->singleton( StateAttributeMigrator::class, function () {
			return new StateAttributeMigrator();
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

		// 3. Register all artisanpack/* blocks from their block.json manifests.
		$this->registerForkedBlocks();

		// 4. Register package-native blocks (artisanpack/callout, etc.).
		$this->registerReferenceBlocks();

		// 4.1. Icon Block Phase 3 (#554) — hand the FA Free SVG sets to
		//      the `artisanpack-ui/icons` registry. The directories are
		//      mirrored by `scripts/sync-fa-icons.mjs` (runs in `prebuild`)
		//      and gitignored; the discovery step no-ops cleanly when the
		//      sync hasn't run yet, so app boot stays robust.
		$this->registerFontAwesomeFreeIconSets();

		// 4a. Register taxonomy/feed dynamic blocks against cms-framework's
		//     term + post APIs. Gated on the package's presence so
		//     visual-editor still boots when cms-framework is absent.
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

			// #487 — surface the breakpoint audit command.
			$this->commands( [
				AuditBreakpointsCommand::class,
			] );
		}
	}

	/**
	 * Registers the forked block types from their block.json manifests.
	 *
	 * I7 (#415): all blocks now live under `resources/js/visual-editor/blocks/`
	 * using the `artisanpack/*` namespace. The legacy `core-blocks/` directory
	 * is no longer the canonical source.
	 *
	 * @since 1.0.0
	 */
	protected function registerForkedBlocks(): void
	{
		$editor    = $this->app->make( VisualEditor::class );
		$blocksDir = __DIR__ . '/../resources/js/visual-editor/blocks';

		$forkedBlocks = [
			// Content (I0/I1)
			'paragraph',
			'heading',
			'list',
			'list-item',
			'quote',
			'code',
			'preformatted',
			'pullquote',
			'verse',
			'table',
			// Media (I2)
			'image',
			'gallery',
			'video',
			'audio',
			'file',
			'embed',
			'cover',
			'media-text',
			// Layout (I3)
			'group',
			'columns',
			'column',
			'buttons',
			'button',
			'separator',
			'spacer',
			'details',
			// Widgets (I4)
			'search',
			// Entity (I5)
			'template-part',
			'post-title',
			'post-content',
			'post-excerpt',
			'post-date',
			'post-author',
			'post-featured-image',
			'site-title',
			'site-tagline',
			'site-logo',
			'navigation',
			// Loop / feed (I6)
			'archives',
			'categories',
			'tag-cloud',
			'query',
			'post-template',
		];

		foreach ( $forkedBlocks as $block ) {
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
			'icon',
		];

		foreach ( $referenceBlocks as $block ) {
			$blockJsonPath = $blocksDir . '/' . $block . '/block.json';

			if ( file_exists( $blockJsonPath ) ) {
				$editor->registerBlock( $blockJsonPath );
			}
		}

		// Phase 1 of the Icon Block (#552/#494): the block.json above gives
		// the inserter its metadata; this line wires the server-side renderer
		// so the preview endpoint can produce real markup. Phase 3 (#554)
		// adds the FA Free registry that turns iconRefs into inline SVG.
		$editor->registerDynamicBlock( IconBlock::class );
	}

	/**
	 * Hook the FA Free SVG sets into the `ap.icons.register-icon-sets`
	 * filter.
	 *
	 * Gated on the icons package being present so visual-editor still
	 * boots in setups that haven't pulled `artisanpack-ui/icons` (the
	 * filter would never fire there anyway, but skipping the registration
	 * keeps the boot trace clean). Gated on `IconSetRegistration` rather
	 * than a service-container key so a partial install doesn't NPE.
	 *
	 * @since 1.1.0
	 */
	protected function registerFontAwesomeFreeIconSets(): void
	{
		if ( ! class_exists( IconSetRegistration::class ) || ! function_exists( 'addFilter' ) ) {
			return;
		}

		$baseDir = __DIR__ . '/../resources/icons/font-awesome';

		addFilter(
			'ap.icons.register-icon-sets',
			static function ( IconSetRegistration $registry ) use ( $baseDir ): IconSetRegistration {
				return FontAwesomeFreeIconSets::register( $registry, $baseDir );
			}
		);
	}

	/**
	 * Registers the taxonomy/feed dynamic blocks against cms-framework's
	 * term + post APIs. The `artisanpack/latest-posts` block also loads its
	 * bundled `block.json` for the inserter.
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
