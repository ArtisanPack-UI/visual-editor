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

use ArtisanPackUI\VisualEditor\Animations\AnimationCssEmitter;
use ArtisanPackUI\VisualEditor\Animations\KeyframeRegistry;
use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use ArtisanPackUI\VisualEditor\Resources\TemplatePartInliner;
use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\Responsive\ResponsiveValueResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Animations\AnimationMarkupResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Console\Commands\PublishAssetsCommand;
use ArtisanPackUI\VisualEditorRendererBlade\Http\Middleware\InjectQueuedGlobalStyles;
use ArtisanPackUI\VisualEditorRendererBlade\Http\Middleware\OptimizeBlockStyles;
use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\BreadcrumbsResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\BusinessInfoResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\LoginoutResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\SiteMetaResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Responsive\ResponsiveClassResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\AnimationCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\BlockTreeReferenceResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\BoxShadowCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\DuotoneCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\ElementsCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\FaqSchemaAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\FrontendAssetManifest;
use ArtisanPackUI\VisualEditorRendererBlade\Services\FrontendAssetPublisher;
use ArtisanPackUI\VisualEditorRendererBlade\Services\FrontendStylesOptimizer;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GeneratedStylesheets;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesEmissionResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesPlacement;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GradientBorderCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\NavigationOverlayTracker;
use ArtisanPackUI\VisualEditorRendererBlade\Services\PositionCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveImages;
use ArtisanPackUI\VisualEditorRendererBlade\Services\StateCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\ThemeJsonTokensCompiler;
use ArtisanPackUI\VisualEditorRendererBlade\View\Components\BlocksComponent;
use ArtisanPackUI\VisualEditorRendererBlade\View\Components\BlocksStylesComponent;
use ArtisanPackUI\VisualEditorRendererBlade\View\Components\TemplateComponent;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Throwable;

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

		// Scoped — the business-info resolver memoizes the filter result
		// per stampTree() call, but a long-lived worker (Octane / queue)
		// must not pin one request's cache across requests. #761.
		$this->app->scoped( BusinessInfoResolver::class, function () {
			return new BusinessInfoResolver();
		} );

		// Scoped (not singleton) so the BlockRenderer captures the
		// current request's SiteMetaResolver — also scoped — instead of
		// pinning the first request's resolver for the lifetime of a
		// long-running worker (Octane / queue) and serving stale site
		// meta on every subsequent request.
		$this->app->scoped( BlockRenderer::class, function ( $app ) {
			// #491 · #492 · #493 — Block Visibility. The evaluator is
			// optional so the renderer keeps working in host apps that
			// have the visibility feature disabled or removed. The
			// binding is resolved through `rescue()` so a missing
			// service (e.g. mid-migration on an older visual-editor
			// version) doesn't take the whole render path down.
			$visibility = null;
			try {
				$visibility = $app->make( \ArtisanPackUI\VisualEditor\Visibility\VisibilityEvaluator::class );
			} catch ( Throwable $e ) {
				// Container binding missing — render without the gate.
			}

			// #650 — bindings layer is optional so the renderer keeps
			// working in host apps that install it standalone. Missing
			// binding renders no-op through BlockRenderer::resolveBindings().
			$bindingResolver = null;
			try {
				$bindingResolver = $app->make( \ArtisanPackUI\VisualEditor\Services\Bindings\BindingResolver::class );
			} catch ( Throwable $e ) {
				// Ignore — bindings layer absent.
			}

			return new BlockRenderer(
				$app->make( ViewFactory::class ),
				$app->make( DynamicBlockRegistry::class ),
				$app->make( SiteMetaResolver::class ),
				$app->make( LoginoutResolver::class ),
				$visibility,
				$bindingResolver,
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

		// Scoped so the theme recorded for the render in progress never
		// outlives the request (Octane / queue). Shares the template-part,
		// pattern and navigation passes between the components and the
		// navigation overlay partial (#973).
		$this->app->scoped( BlockTreeReferenceResolver::class );

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
		$this->app->scoped( Support\FlexSupport::class, function ( $app ) {
			return new Support\FlexSupport(
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

		// #852 — sibling accumulator for the per-block Elements API
		// `<style data-ve-elements>` block (link / heading / button colors).
		$this->app->scoped( ElementsCssAccumulator::class, function () {
			return new ElementsCssAccumulator();
		} );

		// #873 — sibling accumulator for the duotone filter support's
		// `<style data-ve-duotone>` rules + hidden `<svg>` filter defs.
		$this->app->scoped( DuotoneCssAccumulator::class, function () {
			return new DuotoneCssAccumulator();
		} );

		// #1034 — queues FAQ questions so a page prints one `FAQPage` script.
		$this->app->scoped( FaqSchemaAccumulator::class, function () {
			return new FaqSchemaAccumulator();
		} );

		// #1033 — the published asset manifest, re-read per request so a
		// long-lived worker notices a publish.
		$this->app->scoped( FrontendAssetManifest::class, function () {
			return new FrontendAssetManifest();
		} );

		// #1031 — scoped: it memoizes media records and tracks which image
		// on the page takes the priority slot.
		$this->app->scoped( ResponsiveImages::class, function () {
			return new ResponsiveImages();
		} );

		// #1032 — stateless apart from the config they read.
		$this->app->singleton( GeneratedStylesheets::class );
		$this->app->singleton( FrontendStylesOptimizer::class );

		// #879 — where the request's global styles land (head vs body).
		// Scoped with the emission tracker it works alongside.
		$this->app->scoped( GlobalStylesPlacement::class, function () {
			return new GlobalStylesPlacement();
		} );
	}

	public function boot(): void
	{
		$this->loadViewsFrom( __DIR__ . '/../resources/views', 'visual-editor-renderer-blade' );

		// #699 — serves the bundled block-library + `frontend/*` assets from
		// the package so `<x-ve-blocks-styles />` resolves with no publish
		// step. A consumer that has published the assets never reaches this
		// route: the web server hands back the static `public/` file first.
		$this->loadRoutesFrom( __DIR__ . '/../routes/assets.php' );

		// #879 — deliver queued global styles a layout never printed (no
		// `<x-ve-blocks-styles>` or `@stack('ve-global-styles')` in its head).
		// #1032 — trim the stylesheets the finished page doesn't need.
		$this->registerGlobalStylesMiddleware();

		Blade::component( BlocksComponent::class, 've-blocks' );
		Blade::component( BlocksStylesComponent::class, 've-blocks-styles' );
		Blade::component( TemplateComponent::class, 've-template' );

		if ( $this->app->runningInConsole() ) {
			$this->publishes( [
				__DIR__ . '/../resources/views' => resource_path( 'views/vendor/visual-editor-renderer-blade' ),
			], 'visual-editor-blade-views' );

			$this->commands( [ PublishAssetsCommand::class ] );

			// Optional asset publish path: copies the bundled
			// `@wordpress/block-library` CSS to the consumer's
			// `public/vendor/visual-editor-renderer-blade/`, which
			// `<x-ve-blocks-styles />` links to by default. Also ships the
			// accordion + tabs front-end stylesheets and interactivity script
			// under `public/vendor/visual-editor-renderer-blade/frontend/`.
			//
			// Publishing is no longer required — the route registered above
			// serves the same files straight out of the package (#699). It
			// stays available for hosts that prefer the web server to serve
			// the assets statically; those hosts must re-run the command with
			// `--force` after every package upgrade to pick up refreshed CSS.
			$this->publishes( [
				__DIR__ . '/../resources/assets/block-library' => public_path( 'vendor/visual-editor-renderer-blade' ),
				__DIR__ . '/../resources/assets/frontend'      => public_path( 'vendor/visual-editor-renderer-blade/frontend' ),
			], 'visual-editor-renderer-blade-assets' );

			$this->refreshPublishedAssetsAfterComposer();
		}
	}

	/**
	 * Refresh fingerprinted assets when Composer finishes.
	 *
	 * Every `composer install` / `update` in a Laravel app ends with
	 * `package:discover`, so a host that has published once stays current
	 * with nothing added to its `composer.json`. A host that never
	 * published gets nothing written to `public/`. A failure here must not
	 * fail the Composer run: until the next publish the manifest stays
	 * stale, which sends pages back to the asset route.
	 *
	 * @since 1.19.0
	 */
	protected function refreshPublishedAssetsAfterComposer(): void
	{
		$this->app['events']->listen( CommandFinished::class, function ( CommandFinished $event ): void {
			if ( 'package:discover' !== $event->command ) {
				return;
			}

			try {
				$publisher = $this->app->make( FrontendAssetPublisher::class );

				if ( $publisher->isPublished() ) {
					$publisher->publish();
				}
			} catch ( Throwable $e ) {
				report( $e );
			}
		} );
	}

	/**
	 * Add the response middleware to the `web` group, each on its own.
	 *
	 * {@see OptimizeBlockStyles} goes first so it wraps
	 * {@see InjectQueuedGlobalStyles} and sees the injected global styles.
	 *
	 * @since 1.15.0
	 * @since 1.19.0 Adds {@see OptimizeBlockStyles}.
	 */
	protected function registerGlobalStylesMiddleware(): void
	{
		$this->appendToWebGroup( OptimizeBlockStyles::class );
		$this->appendToWebGroup( InjectQueuedGlobalStyles::class );
	}

	/**
	 * Append a middleware to the `web` group.
	 *
	 * Registered on the HTTP kernel, not only the router: the kernel copies
	 * its own middleware groups into the router when it handles a request,
	 * which would drop a group entry pushed onto the router alone. Falls
	 * back to the router when no HTTP kernel (or no `web` group on it) is
	 * available.
	 *
	 * @since 1.19.0
	 *
	 * @param  class-string  $middleware  Middleware class.
	 */
	protected function appendToWebGroup( string $middleware ): void
	{
		$kernelClass = \Illuminate\Contracts\Http\Kernel::class;

		if ( $this->app->bound( $kernelClass ) ) {
			try {
				$kernel = $this->app->make( $kernelClass );

				if ( method_exists( $kernel, 'getMiddlewareGroups' )
					&& method_exists( $kernel, 'appendMiddlewareToGroup' )
					&& isset( $kernel->getMiddlewareGroups()['web'] )
				) {
					$kernel->appendMiddlewareToGroup( 'web', $middleware );

					return;
				}
			} catch ( Throwable ) {
				// Fall through to the router.
			}
		}

		$this->app['router']->pushMiddlewareToGroup( 'web', $middleware );
	}
}
