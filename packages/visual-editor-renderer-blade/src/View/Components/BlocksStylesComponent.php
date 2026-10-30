<?php

/**
 * `<x-ve-blocks-styles />` Blade component.
 *
 * Emits the public stylesheet bundle the visual editor's `<x-ve-blocks>`
 * output expects:
 *
 *   1. `<link>`s to the bundled `@wordpress/block-library` `style.css`
 *      + `theme.css` — the same CSS the editor uses, copied into this
 *      package's `resources/assets/block-library/` directory. The package
 *      registers a route that serves those files from
 *      `/vendor/visual-editor-renderer-blade/`, where this component
 *      points by default, so no install step is required. Hosts that
 *      prefer static delivery can still run
 *      `vendor:publish --tag=visual-editor-renderer-blade-assets` to copy
 *      the files into `public/`; the web server then serves them and the
 *      route is never reached. `visual-editor:publish-assets` publishes
 *      fingerprinted copies, which this component links to through
 *      {@see FrontendAssetManifest} so they can be cached forever (#1033).
 *   2. A `<style>` block with `--wp--preset--*` CSS custom properties
 *      compiled from a `theme.json` payload, so a block referencing e.g.
 *      `var(--wp--preset--color--primary)` resolves on the public site
 *      against the theme's palette.
 *
 * Consumers wire it into their layout `<head>`:
 *
 *     <head>
 *         <x-ve-blocks-styles :theme-json="$themeJson" />
 *     </head>
 *
 * The `theme.json` source is the consumer's responsibility — different
 * CMSs discover themes differently. Pass an empty array (or omit) and the
 * `<link>`s emit alone.
 *
 * It also prints the global styles (`<style data-ve-global-styles>`) last
 * (#879). Global styles then sit in `<head>` before the theme's own
 * stylesheet, the WordPress order the editor canvas uses, so a `style.css`
 * rule beats the theme.json rule it overrides on both surfaces. When the
 * component renders before any block renderer (a single-file view), it
 * claims the once-per-request emission itself. When the body rendered first
 * (an `@extends` section or a component layout's slot), `<x-ve-blocks>`
 * queued the styles on the `ve-global-styles` stack and this component
 * prints that stack. See {@see GlobalStylesPlacement}.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\View\Components;

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesEmissionTracker;
use ArtisanPackUI\VisualEditorRendererBlade\Services\FrontendAssetManifest;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesEmissionResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesPlacement;
use ArtisanPackUI\VisualEditorRendererBlade\Services\ThemeJsonTokensCompiler;
use ArtisanPackUI\VisualEditorRendererBlade\Support\DefaultStyles;
use ArtisanPackUI\VisualEditorRendererBlade\Support\FlexLayoutStyles;
use ArtisanPackUI\VisualEditorRendererBlade\Support\StackingStyles;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class BlocksStylesComponent extends Component
{
	/**
	 * URL the bundled block-library CSS is served from. The package's
	 * asset route answers this prefix out of the box; publishing to
	 * `public/vendor/visual-editor-renderer-blade/` shadows it with a
	 * static file at the same URL.
	 */
	public const DEFAULT_ASSET_BASE = '/vendor/visual-editor-renderer-blade';

	public string $styleHref;

	public string $themeHref;

	public string $accordionStyleHref;

	public string $tabsStyleHref;

	public string $gridStyleHref;

	public string $marqueeStyleHref;

	public string $socialIconsStyleHref;

	public string $inlineIconStyleHref;

	public string $breadcrumbsStyleHref;

	public string $queryPaginationStyleHref;

	public string $flexLayoutStyleHref;

	/**
	 * Flex Layout token rules built from the host's breakpoints, printed
	 * in place of the static `flex-layout.css` when they differ from the
	 * defaults; `''` otherwise (#895).
	 *
	 * @since 1.15.1
	 */
	public string $flexLayoutCss;

	public string $photoGridStyleHref;

	public string $postTemplateStyleHref;

	public string $postVariantStyleHref;

	public string $masonryStyleHref;

	public string $masonryScriptSrc;

	public string $interactivityScriptSrc;

	public bool $emitBlockLibrary;

	public bool $emitInteractive;

	public string $themeTokensCss;

	public string $spacingPresetsCss;

	public string $defaultTokensCss;

	public string $defaultStylesCss;

	public string $stackingCss;

	/**
	 * Compiled global styles, or empty when another renderer already
	 * emitted them this request or the host opted out (#879).
	 *
	 * @since 1.15.0
	 */
	public string $globalStylesCss = '';

	/**
	 * Whether this instance prints the `ve-global-styles` stack, where a
	 * block renderer that ran before the head queued the global styles
	 * (#879). True for the first instance in a request.
	 *
	 * @since 1.15.0
	 */
	public bool $printGlobalStylesStack = false;

	/**
	 * @param  ThemeJsonTokensCompiler  $compiler  Injected — see provider binding.
	 * @param  array<string, mixed>|null  $themeJson  Optional theme.json payload to compile.
	 *                                                 When null the tokens `<style>` is omitted.
	 * @param  string|null  $assetBase  Override the bundled-CSS URL prefix (e.g. point at a CDN).
	 *                                  Defaults to {@see self::DEFAULT_ASSET_BASE}.
	 * @param  bool  $bundle  Set to false to skip the block-library `<link>` tags (consumers who
	 *                        already load Gutenberg's CSS from elsewhere).
	 * @param  bool  $interactive  Set to false to skip the accordion + tabs front-end stylesheets
	 *                             and interactivity script. The script also upgrades the Social
	 *                             Share Content copy-link chip (#963), which stays a plain link
	 *                             to the post without it. Defaults to on.
	 * @param  bool  $blockStyleVariations  Set to false to skip compiling the theme.json
	 *                                      `styles.blocks.*.variations` into the tokens
	 *                                      `<style>` (#876). They are skipped anyway once the
	 *                                      page's global styles, which already carry every
	 *                                      variation, are emitted (#879).
	 * @param  bool  $globalStyles  Set to false so this component never claims the global
	 *                              styles, leaving them to the first `<x-ve-blocks>` /
	 *                              `<x-ve-template>` render (#879).
	 */
	public function __construct(
		protected ThemeJsonTokensCompiler $compiler,
		?array $themeJson = null,
		?string $assetBase = null,
		bool $bundle = true,
		bool $interactive = true,
		bool $blockStyleVariations = true,
		bool $globalStyles = true,
	) {
		$base = $this->normaliseBase( $assetBase ?? self::DEFAULT_ASSET_BASE );

		// #1033 — published, fingerprinted files where they exist. A custom
		// base stays on plain paths: a CDN mirror may not hold those names.
		$manifest = null === $assetBase ? app( FrontendAssetManifest::class ) : null;
		$url      = static fn ( string $path ): string => $base . '/' . ( $manifest?->resolve( $path ) ?? $path );

		$this->styleHref                = $url( 'style.css' );
		$this->themeHref                = $url( 'theme.css' );
		$this->accordionStyleHref       = $url( 'frontend/accordion.css' );
		$this->tabsStyleHref            = $url( 'frontend/tabs.css' );
		$this->gridStyleHref            = $url( 'frontend/grid.css' );
		$this->marqueeStyleHref         = $url( 'frontend/marquee.css' );
		$this->socialIconsStyleHref     = $url( 'frontend/social-icons.css' );
		$this->inlineIconStyleHref      = $url( 'frontend/inline-icon.css' );
		$this->breadcrumbsStyleHref     = $url( 'frontend/breadcrumbs.css' );
		$this->queryPaginationStyleHref = $url( 'frontend/query-pagination.css' );
		$this->flexLayoutStyleHref      = $url( 'frontend/flex-layout.css' );
		$this->photoGridStyleHref       = $url( 'frontend/photo-grid.css' );
		$this->postTemplateStyleHref    = $url( 'frontend/post-template.css' );
		$this->postVariantStyleHref     = $url( 'frontend/post-variant.css' );
		$this->masonryStyleHref         = $url( 'frontend/masonry.css' );
		$this->masonryScriptSrc         = $url( 'frontend/masonry-fallback.js' );
		$this->interactivityScriptSrc   = $url( 'frontend/interactivity.js' );
		$this->emitBlockLibrary         = $bundle;
		$this->emitInteractive          = $interactive;
		$this->spacingPresetsCss        = $this->compiler->compileSpacingPresets( $themeJson );
		$this->defaultTokensCss         = $this->compiler->compileDefaults( $themeJson );
		$this->defaultStylesCss         = DefaultStyles::enabled( $themeJson ) ? DefaultStyles::CSS : '';
		$this->stackingCss              = StackingStyles::css( app( BreakpointRegistry::class ) );
		$this->flexLayoutCss            = FlexLayoutStyles::css( app( BreakpointRegistry::class ) );

		$tracker   = app( GlobalStylesEmissionTracker::class );
		$placement = app( GlobalStylesPlacement::class );

		$this->globalStylesCss        = $placement->claimForHead( $tracker, app( GlobalStylesEmissionResolver::class ), $globalStyles );
		$this->printGlobalStylesStack = $placement->takeStack();

		// The global styles carry every block style variation
		// (`BlockStyleResolver::css()`), so once they are on the page —
		// printed here, queued on the stack, or printed by a block
		// renderer earlier — compiling the theme's variations into the
		// tokens too would emit each `.is-style-*` rule twice.
		$this->themeTokensCss = null === $themeJson
			? ''
			: $this->compiler->compile( $themeJson, $blockStyleVariations && ! $tracker->hasEmitted() );
	}

	public function render(): View
	{
		return view( 'visual-editor-renderer-blade::components.blocks-styles' );
	}

	/**
	 * Strip a single trailing slash from the base URL so concatenation
	 * with `/style.css` doesn't double up.
	 *
	 * @since 1.1.0
	 */
	protected function normaliseBase( string $base ): string
	{
		return rtrim( $base, '/' );
	}
}
