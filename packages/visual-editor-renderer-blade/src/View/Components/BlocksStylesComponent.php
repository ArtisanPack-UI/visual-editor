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
 *      route is never reached.
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
 * It also prints the global styles (`<style data-ve-global-styles>`) last,
 * claiming the once-per-request emission `<x-ve-blocks>` would otherwise
 * make in the body (#879). Global styles then sit in `<head>` before the
 * theme's own stylesheet, the WordPress order the editor canvas uses, so a
 * `style.css` rule beats the theme.json rule it overrides on both surfaces.
 * The emission goes to whichever renders first: a layout whose body (a
 * slot or section) renders before its `<head>` keeps the old body placement.
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
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesEmissionResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\ThemeJsonTokensCompiler;
use ArtisanPackUI\VisualEditorRendererBlade\Support\DefaultStyles;
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
	 * @param  ThemeJsonTokensCompiler  $compiler  Injected — see provider binding.
	 * @param  array<string, mixed>|null  $themeJson  Optional theme.json payload to compile.
	 *                                                 When null the tokens `<style>` is omitted.
	 * @param  string|null  $assetBase  Override the bundled-CSS URL prefix (e.g. point at a CDN).
	 *                                  Defaults to {@see self::DEFAULT_ASSET_BASE}.
	 * @param  bool  $bundle  Set to false to skip the block-library `<link>` tags (consumers who
	 *                        already load Gutenberg's CSS from elsewhere).
	 * @param  bool  $interactive  Set to false to skip the accordion + tabs front-end stylesheets
	 *                             and interactivity script. Defaults to on.
	 * @param  bool  $globalStyles  Set to false to leave the global styles to the first
	 *                              `<x-ve-blocks>` / `<x-ve-template>` render (#879).
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

		$this->styleHref                = $base . '/style.css';
		$this->themeHref                = $base . '/theme.css';
		$this->accordionStyleHref       = $base . '/frontend/accordion.css';
		$this->tabsStyleHref            = $base . '/frontend/tabs.css';
		$this->gridStyleHref            = $base . '/frontend/grid.css';
		$this->marqueeStyleHref         = $base . '/frontend/marquee.css';
		$this->socialIconsStyleHref     = $base . '/frontend/social-icons.css';
		$this->inlineIconStyleHref      = $base . '/frontend/inline-icon.css';
		$this->breadcrumbsStyleHref     = $base . '/frontend/breadcrumbs.css';
		$this->queryPaginationStyleHref = $base . '/frontend/query-pagination.css';
		$this->flexLayoutStyleHref      = $base . '/frontend/flex-layout.css';
		$this->photoGridStyleHref       = $base . '/frontend/photo-grid.css';
		$this->postTemplateStyleHref    = $base . '/frontend/post-template.css';
		$this->postVariantStyleHref     = $base . '/frontend/post-variant.css';
		$this->masonryStyleHref         = $base . '/frontend/masonry.css';
		$this->masonryScriptSrc         = $base . '/frontend/masonry-fallback.js';
		$this->interactivityScriptSrc   = $base . '/frontend/interactivity.js';
		$this->emitBlockLibrary         = $bundle;
		$this->emitInteractive          = $interactive;
		$this->themeTokensCss           = null === $themeJson ? '' : $this->compiler->compile( $themeJson, $blockStyleVariations );
		$this->spacingPresetsCss        = $this->compiler->compileSpacingPresets( $themeJson );
		$this->defaultTokensCss         = $this->compiler->compileDefaults( $themeJson );
		$this->defaultStylesCss         = DefaultStyles::enabled( $themeJson ) ? DefaultStyles::CSS : '';
		$this->stackingCss              = StackingStyles::css( app( BreakpointRegistry::class ) );

		if ( $globalStyles ) {
			$this->globalStylesCss = $this->claimGlobalStyles();
		}
	}

	public function render(): View
	{
		return view( 'visual-editor-renderer-blade::components.blocks-styles' );
	}

	/**
	 * Strip a single trailing slash from the base URL so concatenation
	 * with `/style.css` doesn't double up.
	 */
	/**
	 * Take the request's single global-styles emission, unless a renderer
	 * already printed it (#879).
	 *
	 * @since 1.15.0
	 */
	protected function claimGlobalStyles(): string
	{
		$tracker = app( GlobalStylesEmissionTracker::class );

		if ( $tracker->hasEmitted() ) {
			return '';
		}

		$tracker->markEmitted();

		return app( GlobalStylesEmissionResolver::class )->emit();
	}

	protected function normaliseBase( string $base ): string
	{
		return rtrim( $base, '/' );
	}
}
