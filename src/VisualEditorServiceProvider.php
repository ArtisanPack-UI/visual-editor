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
use ArtisanPackUI\VisualEditor\Services\Icon\IconSetUploader;
use ArtisanPackUI\VisualEditor\Services\Icon\IconSvgResolver;
use ArtisanPackUI\VisualEditor\Services\Icon\SvgSanitizer;
use ArtisanPackUI\VisualEditor\Services\Icon\UploadedIconSetRegistry;
use ArtisanPackUI\VisualEditor\MediaBridge\GutenbergAttachmentAdapter;
use ArtisanPackUI\VisualEditor\Services\Adapters\CmsFramework\CmsFrameworkQueryResolver;
use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\DenyByDefaultGate;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use ArtisanPackUI\VisualEditor\Models\VisualEditorPost;
use ArtisanPackUI\VisualEditor\Policies\VisualEditorPostPolicy;
use ArtisanPackUI\VisualEditor\Registries\BlockBindingSourceRegistry;
use ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry;
use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingResolver;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\CustomFieldSource;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\DynamicContentSource;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\PostCoreSource;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\RelationSource;
use ArtisanPackUI\VisualEditor\Blocks\DynamicContent\DynamicLoopBlock;
use ArtisanPackUI\VisualEditor\Blocks\DynamicContent\SnippetBlock;
use ArtisanPackUI\VisualEditor\Services\DynamicContent\SnippetCycleGuard;
use ArtisanPackUI\VisualEditor\Console\AuditBreakpointsCommand;
use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use ArtisanPackUI\VisualEditor\Resources\CommentInliner;
use ArtisanPackUI\VisualEditor\Resources\CommentResolver;
use ArtisanPackUI\VisualEditor\Resources\QueryInliner;
use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use ArtisanPackUI\VisualEditor\Ai\Agents\ContentBlockSuggestionAgent;
use ArtisanPackUI\VisualEditor\Ai\Agents\HeadingHierarchyAgent;
use ArtisanPackUI\VisualEditor\Ai\Agents\LayoutSuggestionAgent;
use ArtisanPackUI\VisualEditor\Animations\AnimationAttributeResolver;
use ArtisanPackUI\VisualEditor\Animations\AnimationCssEmitter;
use ArtisanPackUI\VisualEditor\Animations\AnimationRegistry;
use ArtisanPackUI\VisualEditor\Animations\KeyframeRegistry;
use ArtisanPackUI\VisualEditor\Responsive\AttributeMigrator;
use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\Responsive\ResponsiveValueResolver;
use ArtisanPackUI\VisualEditor\States\StateAttributeMigrator;
use ArtisanPackUI\VisualEditor\BoxShadow\BoxShadowEmitter;
use ArtisanPackUI\VisualEditor\States\StateCssEmitter;
use ArtisanPackUI\VisualEditor\States\StateRegistry;
use ArtisanPackUI\VisualEditor\States\StateValueResolver;
use ArtisanPackUI\VisualEditor\Search\BlockTreeSearchExtractor;
use ArtisanPackUI\VisualEditor\Support\HookAliases;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\GlobalStylesResolver as SiteEditorGlobalStylesResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\MenuResolver as SiteEditorMenuResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\PatternResolver as SiteEditorPatternResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplatePartResolver as SiteEditorTemplatePartResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplateResolver as SiteEditorTemplateResolver;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesEmissionTracker;
use ArtisanPackUI\VisualEditor\View\Components\VisualEditorComponent;
use ArtisanPackUI\VisualEditor\Visibility\RuleRegistry as VisibilityRuleRegistry;
use ArtisanPackUI\VisualEditor\Visibility\Rules\BrowserOsDeviceRule;
use ArtisanPackUI\VisualEditor\Visibility\Rules\DateTimeWindowRule;
use ArtisanPackUI\VisualEditor\Visibility\Rules\HideRule;
use ArtisanPackUI\VisualEditor\Visibility\Rules\LoginStateRule;
use ArtisanPackUI\VisualEditor\Visibility\Rules\QueryStringRule;
use ArtisanPackUI\VisualEditor\Visibility\Rules\RecurringScheduleRule;
use ArtisanPackUI\VisualEditor\Visibility\Rules\ReferrerRule;
use ArtisanPackUI\VisualEditor\Visibility\Rules\ScreenSizeRule;
use ArtisanPackUI\VisualEditor\Visibility\Rules\SpecificUserRule;
use ArtisanPackUI\VisualEditor\Visibility\Rules\UserRoleRule;
use ArtisanPackUI\VisualEditor\Visibility\ScheduledBlockCollector;
use ArtisanPackUI\VisualEditor\Visibility\TreePruner as VisibilityTreePruner;
use ArtisanPackUI\VisualEditor\Visibility\UserAgentParser;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityEvaluator;
use ArtisanPackUI\VisualEditor\Console\AuditScheduledBlocksCommand;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class VisualEditorServiceProvider extends ServiceProvider
{
	/**
	 * The full set of AI feature keys the visual editor exposes in its UI.
	 *
	 * Single source of truth: `AiController::features()`, `AiTools::enabledFeatures()`,
	 * and the JS editor bundle (via `/ai/features`) all read from here so a
	 * future 6th feature only lands in one place (see review #6). Two of
	 * the keys (`ai.alt_text`, `ai.content_rewrite`) are cross-cutting —
	 * the visual editor consumes them but the `artisanpack-ui/ai` package
	 * owns their registration.
	 *
	 * @since 1.3.0
	 *
	 * @var array<int, string>
	 */
	public const AI_FEATURE_KEYS = [
		'visual_editor.suggest_next_block',
		'visual_editor.suggest_layout',
		'visual_editor.heading_hierarchy',
		'ai.alt_text',
		'ai.content_rewrite',
	];

	/**
	 * Declare the AI features this package owns.
	 *
	 * Auto-discovered by `artisanpack-ui/ai`'s `FeatureRegistry` (see AI RFC).
	 * When the AI package is absent, this method is simply never called and
	 * has no effect — the visual-editor still boots without AI wiring.
	 *
	 * The three keys below are owned by this package. The cross-cutting
	 * `ai.alt_text` and `ai.content_rewrite` features are consumed via the
	 * ai package's registration; visual-editor's UI checks those toggles
	 * before rendering the corresponding editor affordances (see #612,
	 * #613).
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, array{ agent: class-string, package: string }>
	 */
	public function aiFeatures(): array
	{
		return [
			'visual_editor.suggest_next_block' => [
				'agent'   => ContentBlockSuggestionAgent::class,
				'package' => 'artisanpack-ui/visual-editor',
			],
			'visual_editor.suggest_layout'     => [
				'agent'   => LayoutSuggestionAgent::class,
				'package' => 'artisanpack-ui/visual-editor',
			],
			'visual_editor.heading_hierarchy'  => [
				'agent'   => HeadingHierarchyAgent::class,
				'package' => 'artisanpack-ui/visual-editor',
			],
		];
	}

	public function register(): void
	{
		$this->app->singleton( BlockTypeRegistry::class, function () {
			return new BlockTypeRegistry();
		} );

		$this->app->singleton( DynamicBlockRegistry::class, function () {
			return new DynamicBlockRegistry();
		} );

		// #504 — Block bindings: a single shared registry of source drivers
		// (custom_field, post_core, relation, plus host-registered
		// extensions) and a singleton resolver that the preview controller
		// and the frontend renderers both call into.
		$this->app->singleton( BlockBindingSourceRegistry::class, function () {
			return new BlockBindingSourceRegistry();
		} );

		$this->app->singleton( BindingResolver::class, function ( $app ) {
			return new BindingResolver(
				$app->make( BlockBindingSourceRegistry::class ),
			);
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
		// BEFORE every provider's `addFilter('ap.icons.registerIconSets',
		// …)` has fired. Computing the path map eagerly here would race
		// against those registrations and produce an empty resolver. The
		// closure runs at request time, by which point boot is finished
		// and the filter chain is complete.
		//
		// Issue #587: when `owenvoke/blade-fontawesome` is installed,
		// `FontAwesomeFreeIconSets::register()` defers to it and stops
		// publishing `fas` / `far` / `fab` through the
		// `ap.icons.registerIconSets` filter (so the icons-package
		// service provider doesn't collide on `BladeUI\Icons\Factory::add()`).
		// The resolver still needs those paths to inline bundled FA Free
		// SVGs for the picker and the rendered block, so we always seed
		// it directly from `FontAwesomeFreeIconSets::discover()` — paths
		// that come back from the filter then layer on top for any
		// non-FA sets and uploaded sets.
		$faFreeBaseDir = __DIR__ . '/../resources/icons/font-awesome';
		$this->app->singleton( IconSvgResolver::class, function () use ( $faFreeBaseDir ): IconSvgResolver {
			return new IconSvgResolver( static function () use ( $faFreeBaseDir ): array {
				$paths = FontAwesomeFreeIconSets::discover( $faFreeBaseDir );

				if ( ! class_exists( IconSetRegistration::class ) || ! function_exists( 'applyFilters' ) ) {
					return $paths;
				}

				$registry = applyFilters( 'ap.icons.registerIconSets', new IconSetRegistration() );
				if ( ! $registry instanceof IconSetRegistration ) {
					return $paths;
				}

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
		//
		// Phase 6 (#557): hand the catalog a closure that merges the
		// bundled FA Free manifest with whatever the
		// `UploadedIconSetRegistry` has on disk so admin-uploaded sets
		// show up in the picker without a code change. The closure runs
		// lazily on first `search()` / `sets()` call — at request time,
		// after every provider's boot has finished — so it can safely
		// reach into the container for the registry.
		$this->app->singleton( IconCatalog::class, function ( $app ): IconCatalog {
			return new IconCatalog( function () use ( $app ): array {
				return $this->buildMergedIconManifest( $app );
			} );
		} );

		// Phase 6 (#557): the persisted registry of host-uploaded icon
		// sets. The base directory under `storage/app/...` is created
		// on first write — binding here keeps the path resolution in
		// one place so the uploader and the boot-time registration
		// loop see the exact same location.
		$this->app->singleton( UploadedIconSetRegistry::class, function ( $app ): UploadedIconSetRegistry {
			return new UploadedIconSetRegistry( $this->resolveIconSetsBaseDir( $app ) );
		} );

		$this->app->singleton( IconSetUploader::class, function ( $app ): IconSetUploader {
			return new IconSetUploader(
				$app->make( UploadedIconSetRegistry::class ),
				$app->make( SvgSanitizer::class ),
			);
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
		// `ap.visualEditor.resources` callbacks.
		$this->app->singleton( ResourceResolver::class, function () {
			return new ResourceResolver();
		} );

		// H5 — site-editor resolvers. Same pattern as ResourceResolver
		// above: empty placeholders here; boot() rebinds with the filter-
		// merged data once all providers have registered their
		// `ap.visualEditor.{templates,templateParts,patterns,
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

		// #607: Box-shadow emitter. Parallels GradientBorderEmitter
		// (currently unwired) — bound here so future render-pipeline
		// integration can resolve it from the container.
		$this->app->scoped( BoxShadowEmitter::class, function ( $app ) {
			return new BoxShadowEmitter(
				$app->make( StateRegistry::class ),
				$app->make( BreakpointRegistry::class ),
			);
		} );

		$this->app->singleton( StateAttributeMigrator::class, function () {
			return new StateAttributeMigrator();
		} );

		// #489 — block animations. Scoped per request, same as the
		// responsive and state registries: theme.json overrides can
		// swap between requests, and a singleton would leak the
		// resolved animations across them.
		$this->app->scoped( AnimationRegistry::class, function ( $app ) {
			$config = (array) $app['config']->get( 'artisanpack.visual-editor.animations', [] );

			return AnimationRegistry::fromLayers( $config );
		} );

		$this->app->scoped( KeyframeRegistry::class, function ( $app ) {
			$themeKeyframes = (array) $app['config']->get( 'artisanpack.visual-editor.keyframes', [] );

			// Resolve editor-authored keyframes from the same filter-
			// merged global-styles payload that `SiteEditorGlobalStylesResolver`
			// consumes, so a host that registers global styles through
			// the `ap.visualEditor.globalStyles` filter (cms-framework
			// being the canonical caller) sees its custom keyframes
			// reach the editor and renderer. Reading directly from
			// config would miss the filter contributions.
			try {
				$resolver     = $app->make( SiteEditorGlobalStylesResolver::class );
				$globalStyles = $resolver->raw();
			} catch ( \Throwable $e ) {
				$globalStyles = null;
			}

			$editorKeyframes = [];
			if ( is_array( $globalStyles['styles']['custom']['artisanpack']['keyframes'] ?? null ) ) {
				$editorKeyframes = $globalStyles['styles']['custom']['artisanpack']['keyframes'];
			}

			return KeyframeRegistry::fromLayers( $themeKeyframes, $editorKeyframes );
		} );

		$this->app->scoped( AnimationAttributeResolver::class, function ( $app ) {
			return new AnimationAttributeResolver( $app->make( BreakpointRegistry::class ) );
		} );

		$this->app->scoped( AnimationCssEmitter::class, function ( $app ) {
			return new AnimationCssEmitter(
				$app->make( AnimationRegistry::class ),
				$app->make( KeyframeRegistry::class ),
				$app->make( BreakpointRegistry::class ),
				$app->make( AnimationAttributeResolver::class ),
			);
		} );

		// #491 · #492 · #493 — Block Visibility. `UserAgentParser` is
		// stateless (safe singleton). `ScheduledBlockCollector` is a
		// pure walker (safe singleton). The `VisibilityRuleRegistry`
		// is scoped per request because the built-in rule set is
		// merged with the `ap.visualEditor.visibility.registerRules`
		// filter, and third-party rule providers may register with
		// per-request container state (route parameters, tenant
		// context, etc.). The `VisibilityEvaluator` is also scoped so
		// it captures the request-scoped Request + Guard at resolve
		// time — a singleton would leak stale request objects into
		// subsequent requests under Octane / RoadRunner.
		$this->app->singleton( UserAgentParser::class, function () {
			return new UserAgentParser();
		} );

		$this->app->singleton( ScheduledBlockCollector::class, function () {
			return new ScheduledBlockCollector();
		} );

		// The scoped closure only assembles the built-in rule set —
		// the `ap.visualEditor.visibility.registerRules` filter is
		// applied via `extend()` in `boot()` (see
		// `applyVisibilityRulesFilter()`). Extending inside the closure
		// would freeze the filter chain state at first-resolve time,
		// which fails when a third-party provider registers its
		// `addFilter` in its own `boot()` phase and something resolves
		// the registry before that boot runs.
		$this->app->scoped( VisibilityRuleRegistry::class, function ( $app ) {
			return new VisibilityRuleRegistry( [
				$app->make( HideRule::class ),
				$app->make( ScreenSizeRule::class ),
				$app->make( QueryStringRule::class ),
				$app->make( ReferrerRule::class ),
				$app->make( BrowserOsDeviceRule::class ),
				$app->make( LoginStateRule::class ),
				$app->make( UserRoleRule::class ),
				$app->make( SpecificUserRule::class ),
				$app->make( DateTimeWindowRule::class ),
				$app->make( RecurringScheduleRule::class ),
			] );
		} );

		$this->app->scoped( VisibilityEvaluator::class, function ( $app ) {
			return new VisibilityEvaluator(
				$app->make( VisibilityRuleRegistry::class ),
				$app->make( ConfigRepository::class ),
				$app->bound( 'request' ) ? $app->make( 'request' ) : null,
				$app->bound( 'auth' ) ? $app->make( 'auth' )->guard() : null,
			);
		} );

		// Rules that need injected collaborators — bound explicitly so
		// tests can rebind a fake without patching the registry.
		$this->app->singleton( HideRule::class, fn () => new HideRule() );
		$this->app->singleton( QueryStringRule::class, fn () => new QueryStringRule() );
		$this->app->singleton( ReferrerRule::class, fn () => new ReferrerRule() );
		$this->app->singleton( LoginStateRule::class, fn () => new LoginStateRule() );
		$this->app->singleton( UserRoleRule::class, fn () => new UserRoleRule() );
		$this->app->singleton( SpecificUserRule::class, fn () => new SpecificUserRule() );
		$this->app->singleton( DateTimeWindowRule::class, fn () => new DateTimeWindowRule() );
		$this->app->singleton( RecurringScheduleRule::class, fn () => new RecurringScheduleRule() );

		$this->app->scoped( ScreenSizeRule::class, function ( $app ) {
			return new ScreenSizeRule( $app->make( BreakpointRegistry::class ) );
		} );

		$this->app->singleton( BrowserOsDeviceRule::class, function ( $app ) {
			return new BrowserOsDeviceRule( $app->make( UserAgentParser::class ) );
		} );

		$this->app->scoped( VisibilityTreePruner::class, function ( $app ) {
			return new VisibilityTreePruner( $app->make( VisibilityEvaluator::class ) );
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
	 * Layer the `ap.visualEditor.visibility.registerRules` filter
	 * over every fresh {@see VisibilityRuleRegistry} instance.
	 *
	 * Registered in `boot()` via {@see \Illuminate\Container\Container::extend()}
	 * so the filter chain is guaranteed complete by the time the
	 * registry is resolved — third-party packages can hook their
	 * custom rules from their own `boot()` regardless of provider
	 * order. Firing the filter inside the scoped closure would freeze
	 * the chain state at first-resolve, breaking any late-boot
	 * registration.
	 *
	 * @since 1.4.0
	 */
	protected function applyVisibilityRulesFilter(): void
	{
		if ( ! function_exists( 'applyFilters' ) ) {
			return;
		}

		$this->app->extend(
			VisibilityRuleRegistry::class,
			static function ( VisibilityRuleRegistry $registry ): VisibilityRuleRegistry {
				$filtered = applyFilters( 'ap.visualEditor.visibility.registerRules', $registry );
				return $filtered instanceof VisibilityRuleRegistry ? $filtered : $registry;
			},
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
		// 0. Register hook name aliases (old → new) as early as possible in
		//    boot() so any subsequent hook fire sites route legacy
		//    subscribers to the canonical (camelCase) hook name. See
		//    src/Support/HookAliases.php for the full rename table.
		HookAliases::registerAll();

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

		// 1b. Layer the `ap.visualEditor.visibility.registerRules`
		//     filter over the scoped registry via `extend()`. Runs at
		//     resolve-time (after the closure builds the default set)
		//     which means addFilter calls from any other provider's
		//     `boot()` are visible regardless of provider order — the
		//     failure mode we hit when the filter fired inside the
		//     scoped closure (an intra-boot resolve captured an empty
		//     filter chain).
		$this->applyVisibilityRulesFilter();

		// 2. Load package views, routes, and migrations.
		$this->loadViewsFrom( __DIR__ . '/../resources/views', 'visual-editor' );
		$this->loadRoutesFrom( __DIR__ . '/../routes/web.php' );
		$this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );

		$this->registerApiRoutes();
		$this->registerBladeComponents();
		$this->registerAiLivewireComponents();

		Gate::policy( VisualEditorPost::class, VisualEditorPostPolicy::class );

		// 3. Register all artisanpack/* blocks from their block.json manifests.
		$this->registerForkedBlocks();

		// 4. Register package-native blocks (artisanpack/callout, etc.).
		$this->registerReferenceBlocks();

		// 4.0. #504 — Register the built-in block binding sources. Hosts
		//      and third-party packages register additional sources in
		//      their own provider's boot() phase; the order of
		//      registration does not matter because the resolver consults
		//      the registry per-binding at render time.
		$this->registerBlockBindingSources();

		// 4.0.1. #650 — Register the Dynamic Content dynamic block
		//        renderers (`artisanpack/snippet`, `artisanpack/dynamic-loop`)
		//        and their supporting cycle guard.
		$this->registerDynamicContentBlocks();

		// 4.1. Icon Block Phase 3 (#554) — hand the FA Free SVG sets to
		//      the `artisanpack-ui/icons` registry. The directories are
		//      mirrored by `scripts/sync-fa-icons.mjs` (runs in `prebuild`)
		//      and gitignored; the discovery step no-ops cleanly when the
		//      sync hasn't run yet, so app boot stays robust.
		$this->registerFontAwesomeFreeIconSets();

		// 4.1a. #639 — seed the built-in `page` pattern category with a
		//       single `Blank` starter so the page-pattern-inserter modal
		//       has at least one entry to render before host apps register
		//       their own patterns. Registered against the pre-booted
		//       filter so the boot()-late resolver refresh above picks it
		//       up alongside third-party contributions.
		$this->registerBuiltInPatterns();

		// 4.2. Icon Block Phase 6 (#557) — re-register host-uploaded
		//      icon sets on every boot. Reads the persisted registry
		//      and hooks the same `ap.icons.registerIconSets` filter
		//      the bundled FA sets use, so the picker, the SVG
		//      resolver, and the catalog all surface uploaded icons
		//      without any further wiring.
		$this->registerUploadedIconSets();

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
			// #493 — surface the scheduled-blocks audit command.
			$this->commands( [
				AuditBreakpointsCommand::class,
				AuditScheduledBlocksCommand::class,
			] );
		}
	}

	/**
	 * Registers the built-in block binding source drivers — `custom_field`,
	 * `post_core`, and `relation`. Host applications and third-party
	 * packages register their own drivers by resolving the registry out
	 * of the container in their own provider's boot() phase.
	 *
	 * @since 1.1.0
	 */
	protected function registerBlockBindingSources(): void
	{
		$registry = $this->app->make( BlockBindingSourceRegistry::class );

		$registry->register( new CustomFieldSource() );
		$registry->register( new PostCoreSource() );
		$registry->register( new RelationSource() );

		// #650 — cms-framework Dynamic Content is a soft dependency; the
		// source registers unconditionally and returns null at resolve
		// time when the accessor class is not on the classpath, so the
		// editor UI can still surface the source and its empty catalog
		// consistently.
		$registry->register( new DynamicContentSource() );
	}

	/**
	 * Register the Dynamic Content dynamic blocks and their supporting
	 * services (cycle guard shared with the CRUD controller).
	 *
	 * #650 — snippet + dynamic-loop server-side renderers. Registered
	 * unconditionally: snippet works standalone; dynamic-loop needs
	 * cms-framework at render time but registration is safe without it.
	 *
	 * @since 1.4.0
	 */
	protected function registerDynamicContentBlocks(): void
	{
		$this->app->singleton( SnippetCycleGuard::class, static fn () => new SnippetCycleGuard() );

		$blockRegistry = $this->app->make( DynamicBlockRegistry::class );

		$blockRegistry->register( new SnippetBlock( $this->app->make( SnippetCycleGuard::class ) ) );
		$blockRegistry->register( new DynamicLoopBlock( $this->app->make( BindingResolver::class ) ) );
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
			'post-variant',
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
	 * Hook the FA Free SVG sets into the `ap.icons.registerIconSets`
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
			'ap.icons.registerIconSets',
			static function ( IconSetRegistration $registry ) use ( $baseDir ): IconSetRegistration {
				return FontAwesomeFreeIconSets::register( $registry, $baseDir );
			}
		);
	}

	/**
	 * Seed the built-in `page` pattern category with a `Blank` starter.
	 *
	 * The page-pattern-inserter modal (#639) needs at least one entry to
	 * render even before third-party packages register their own patterns.
	 * Shipping a `Blank` seed with no blocks gives users the "start from
	 * scratch" affordance and keeps the modal from rendering an empty
	 * grid on a fresh install.
	 *
	 * Left unscoped (`post_types => null`) so the seed appears in every
	 * post-type context — the goal is a universally-available empty-canvas
	 * shortcut, not a page-only entry.
	 *
	 * Gated on `addFilter` to keep visual-editor bootable when hooks isn't
	 * on the classpath.
	 *
	 * @since 1.4.0
	 */
	protected function registerBuiltInPatterns(): void
	{
		if ( ! function_exists( 'addFilter' ) ) {
			return;
		}

		addFilter(
			'ap.visualEditor.patterns',
			static function ( mixed $patterns ): array {
				$patterns = is_array( $patterns ) ? $patterns : [];

				// Contributor entries always win — a host that registers
				// its own `page/blank` (e.g. to swap in a themed skeleton)
				// takes precedence over the seed. Merge the seed only when
				// no entry exists for the slug.
				if ( ! array_key_exists( 'page/blank', $patterns ) ) {
					$patterns['page/blank'] = [
						'slug'       => 'page/blank',
						'title'      => __( 'Blank' ),
						'source'     => 'theme',
						'synced'     => false,
						'categories' => [ 'page' ],
						'blocks'     => [],
						'raw_content' => '',
						'post_types' => null,
					];
				}

				return $patterns;
			},
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
	 * Register the AI trigger Livewire component when both Livewire and
	 * the artisanpack-ui/ai foundation are present. Guarded so the visual
	 * editor still boots on hosts that opt out of AI or Livewire.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	protected function registerAiLivewireComponents(): void
	{
		if ( ! class_exists( \Livewire\Livewire::class ) ) {
			return;
		}

		if ( ! class_exists( \ArtisanPackUI\Ai\Agents\ArtisanPackAgent::class ) ) {
			return;
		}

		\Livewire\Livewire::component(
			'artisanpack-visual-editor.ai.tools',
			\ArtisanPackUI\VisualEditor\Livewire\Ai\AiTools::class,
		);
	}


	/**
	 * Builds the slug → model class map for ResourceResolver.
	 *
	 * Pipes the static config through the `ap.visualEditor.resources` filter
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
		$filtered     = applyFilters( 'ap.visualEditor.resources', $staticConfig );
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
		$templatesMerged = applyFilters( 'ap.visualEditor.templates', $templatesStatic );
		$templatesMerged = is_array( $templatesMerged ) ? $templatesMerged : [];
		$templatesMerged = array_merge( $templatesMerged, $templatesStatic );

		$this->app->instance(
			SiteEditorTemplateResolver::class,
			new SiteEditorTemplateResolver( $templatesMerged ),
		);

		// Template parts ─ array<string, array> keyed by slug.
		$partsStatic = (array) config( 'artisanpack.visual-editor.site-editor.template-parts', [] );
		$partsMerged = applyFilters( 'ap.visualEditor.templateParts', $partsStatic );
		$partsMerged = is_array( $partsMerged ) ? $partsMerged : [];
		$partsMerged = array_merge( $partsMerged, $partsStatic );

		$this->app->instance(
			SiteEditorTemplatePartResolver::class,
			new SiteEditorTemplatePartResolver( $partsMerged ),
		);

		// Patterns ─ array<string, array> keyed by slug.
		$patternsStatic = (array) config( 'artisanpack.visual-editor.site-editor.patterns', [] );
		$patternsMerged = applyFilters( 'ap.visualEditor.patterns', $patternsStatic );
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
		$globalStylesMerged = applyFilters( 'ap.visualEditor.globalStyles', $globalStylesStatic );
		$globalStylesMerged = is_array( $globalStylesMerged ) ? $globalStylesMerged : null;
		$globalStylesMerged = $globalStylesStatic ?? $globalStylesMerged;

		$this->app->instance(
			SiteEditorGlobalStylesResolver::class,
			new SiteEditorGlobalStylesResolver( $globalStylesMerged ),
		);

		// Navigation ─ array<string, array> keyed by location.
		$menusStatic = (array) config( 'artisanpack.visual-editor.site-editor.navigation', [] );
		$menusMerged = applyFilters( 'ap.visualEditor.navigation', $menusStatic );
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

	/**
	 * Hand the persisted uploaded icon sets to the
	 * `ap.icons.registerIconSets` filter.
	 *
	 * Phase 6 (#557). The {@see UploadedIconSetRegistry} only carries
	 * metadata; the directories are managed by {@see IconSetUploader}.
	 * We tolerate (and log) a missing directory rather than throwing,
	 * so a stale metadata row left over from a manual rm doesn't break
	 * boot — the admin can delete the dangling row from the settings
	 * screen.
	 *
	 * Prefix collisions raised by the icons-registry are swallowed at
	 * boot time so a host with overlapping uploads (or a config that
	 * also registers a same-prefix set) still boots; the management
	 * controller catches collisions on upload, which is when the admin
	 * can act on the error.
	 *
	 * @since 1.1.0
	 */
	protected function registerUploadedIconSets(): void
	{
		if ( ! class_exists( IconSetRegistration::class ) || ! function_exists( 'addFilter' ) ) {
			return;
		}

		$app = $this->app;

		addFilter(
			'ap.icons.registerIconSets',
			static function ( IconSetRegistration $registry ) use ( $app ): IconSetRegistration {
				$persisted = $app->make( UploadedIconSetRegistry::class );

				foreach ( $persisted->all() as $set ) {
					$path = $persisted->pathFor( $set->prefix );
					if ( ! is_dir( $path ) ) {
						continue;
					}

					try {
						$registry->addSet( $path, $set->prefix );
					} catch ( \InvalidArgumentException $e ) {
						// Mirrors the bundled FA registration loop: keep
						// boot resilient to bad rows. The settings UI is
						// the place to surface the underlying conflict.
						continue;
					}
				}

				return $registry;
			}
		);
	}

	/**
	 * Resolve the absolute filesystem directory under which host-
	 * uploaded icon sets are persisted.
	 *
	 * Defaults to `storage/app/artisanpack/visual-editor/icons/`. Hosts
	 * can override via `artisanpack.visual-editor.icons.uploaded_path`.
	 *
	 * @since 1.1.0
	 */
	protected function resolveIconSetsBaseDir( $app ): string
	{
		$configured = $app['config']->get( 'artisanpack.visual-editor.icons.uploaded_path' );
		if ( is_string( $configured ) && '' !== $configured ) {
			return $configured;
		}

		$storage = method_exists( $app, 'storagePath' )
			? $app->storagePath()
			: ( $app['path.storage'] ?? sys_get_temp_dir() );

		return $storage
			. DIRECTORY_SEPARATOR . 'app'
			. DIRECTORY_SEPARATOR . 'artisanpack'
			. DIRECTORY_SEPARATOR . 'visual-editor'
			. DIRECTORY_SEPARATOR . 'icons';
	}

	/**
	 * Build the catalog manifest by merging the bundled FA Free
	 * `index.json` with whatever the {@see UploadedIconSetRegistry}
	 * tracks. Each uploaded SVG file becomes one catalog entry whose
	 * name is the filename without the `.svg` extension — there is no
	 * separate manifest for uploaded sets, the on-disk layout IS the
	 * manifest.
	 *
	 * Returns the manifest shape {@see IconCatalog} consumes:
	 * `{version, sets, icons}`. An unreadable / missing bundled manifest
	 * does not block the uploaded entries from surfacing in the picker.
	 *
	 * @since 1.1.0
	 *
	 * @return array{
	 *     version?: string,
	 *     sets: list<array{prefix: string, label: string, source?: string}>,
	 *     icons: list<array{name: string, set: string, label: string, terms: list<string>}>
	 * }
	 */
	protected function buildMergedIconManifest( $app ): array
	{
		$bundled = [ 'version' => '', 'sets' => [], 'icons' => [] ];

		$bundledPath = __DIR__ . '/../resources/icons/font-awesome/index.json';
		if ( is_file( $bundledPath ) ) {
			$contents = file_get_contents( $bundledPath );
			if ( false !== $contents ) {
				$decoded = json_decode( $contents, true );
				if ( is_array( $decoded ) ) {
					$bundled['version'] = isset( $decoded['version'] ) ? (string) $decoded['version'] : '';
					$bundled['sets']    = is_array( $decoded['sets'] ?? null ) ? array_values( $decoded['sets'] ) : [];
					$bundled['icons']   = is_array( $decoded['icons'] ?? null ) ? array_values( $decoded['icons'] ) : [];
				}
			}
		}

		try {
			$persisted = $app->make( UploadedIconSetRegistry::class );
		} catch ( \Throwable $e ) {
			return $bundled;
		}

		// Resolve which prefixes the icon-set registry actually accepted
		// so the picker never surfaces an icon whose prefix didn't make
		// it through `registerUploadedIconSets()` — `IconSvgResolver`
		// otherwise can't serve the SVG at click time, and the picker
		// would render a black tile or a 404. Both paths now share one
		// source of truth: the result of `ap.icons.registerIconSets`.
		$registeredPrefixes = [];
		if ( class_exists( IconSetRegistration::class ) && function_exists( 'applyFilters' ) ) {
			$registry = applyFilters( 'ap.icons.registerIconSets', new IconSetRegistration() );
			if ( $registry instanceof IconSetRegistration ) {
				$registeredPrefixes = array_flip( array_keys( $registry->getSets() ) );
			}
		}

		$uploadedSets  = [];
		$uploadedIcons = [];

		foreach ( $persisted->all() as $set ) {
			// When the filter ran cleanly, gate every uploaded set on
			// having survived it. With no filter available (icons
			// package absent, hooks helper missing) we fall back to
			// the pre-filter `is_dir()` check so a working install
			// without those plumbing pieces still surfaces uploads.
			if ( [] !== $registeredPrefixes && ! isset( $registeredPrefixes[ $set->prefix ] ) ) {
				continue;
			}

			$dir = $persisted->pathFor( $set->prefix );
			if ( ! is_dir( $dir ) ) {
				continue;
			}

			$uploadedSets[] = [
				'prefix' => $set->prefix,
				'label'  => $set->label,
				'source' => 'uploaded',
			];

			$entries = scandir( $dir );
			if ( false === $entries ) {
				continue;
			}

			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}
				if ( '.svg' !== strtolower( substr( $entry, -4 ) ) ) {
					continue;
				}

				$name = substr( $entry, 0, -4 );
				if ( '' === $name ) {
					// A bare `.svg` filename would produce an empty
					// catalog entry — drop it so the picker grid
					// never tries to render an unnamed icon.
					continue;
				}
				$uploadedIcons[] = [
					'name'  => $name,
					'set'   => $set->prefix,
					'label' => $this->humanizeIconName( $name ),
					'terms' => [],
				];
			}
		}

		return [
			'version' => $bundled['version'],
			'sets'    => array_merge( $bundled['sets'], $uploadedSets ),
			'icons'   => array_merge( $bundled['icons'], $uploadedIcons ),
		];
	}

	/**
	 * Turn an icon basename (`arrow-up`, `user_circle`) into the label
	 * the picker surfaces (`Arrow Up`, `User Circle`). Used for
	 * uploaded sets that don't ship a labelled manifest.
	 *
	 * @since 1.1.0
	 */
	protected function humanizeIconName( string $name ): string
	{
		$spaced = preg_replace( '/[-_]+/', ' ', $name ) ?? $name;

		return ucwords( trim( $spaced ) );
	}

}
