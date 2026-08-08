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
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\View\Components;

use ArtisanPackUI\VisualEditorRendererBlade\Services\ThemeJsonTokensCompiler;
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
	 */
	public function __construct(
		protected ThemeJsonTokensCompiler $compiler,
		?array $themeJson = null,
		?string $assetBase = null,
		bool $bundle = true,
		bool $interactive = true,
	) {
		$base = $this->normaliseBase( $assetBase ?? self::DEFAULT_ASSET_BASE );

		$this->styleHref              = $base . '/style.css';
		$this->themeHref              = $base . '/theme.css';
		$this->accordionStyleHref     = $base . '/frontend/accordion.css';
		$this->tabsStyleHref          = $base . '/frontend/tabs.css';
		$this->gridStyleHref          = $base . '/frontend/grid.css';
		$this->marqueeStyleHref       = $base . '/frontend/marquee.css';
		$this->socialIconsStyleHref   = $base . '/frontend/social-icons.css';
		$this->breadcrumbsStyleHref   = $base . '/frontend/breadcrumbs.css';
		$this->queryPaginationStyleHref = $base . '/frontend/query-pagination.css';
		$this->flexLayoutStyleHref    = $base . '/frontend/flex-layout.css';
		$this->photoGridStyleHref     = $base . '/frontend/photo-grid.css';
		$this->postTemplateStyleHref  = $base . '/frontend/post-template.css';
		$this->postVariantStyleHref   = $base . '/frontend/post-variant.css';
		$this->masonryStyleHref       = $base . '/frontend/masonry.css';
		$this->masonryScriptSrc       = $base . '/frontend/masonry-fallback.js';
		$this->interactivityScriptSrc = $base . '/frontend/interactivity.js';
		$this->emitBlockLibrary       = $bundle;
		$this->emitInteractive        = $interactive;
		$this->themeTokensCss         = null === $themeJson ? '' : $this->compiler->compile( $themeJson );
	}

	public function render(): View
	{
		return view( 'visual-editor-renderer-blade::components.blocks-styles' );
	}

	/**
	 * Strip a single trailing slash from the base URL so concatenation
	 * with `/style.css` doesn't double up.
	 */
	protected function normaliseBase( string $base ): string
	{
		return rtrim( $base, '/' );
	}
}
