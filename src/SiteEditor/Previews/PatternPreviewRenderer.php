<?php

/**
 * Pattern preview renderer.
 *
 * Renders a site-editor pattern to the same front-end HTML the public site
 * would produce (#832), for the scaled, non-interactive previews on pattern
 * cards. The pattern runs through the bundled Blade renderer's
 * `<x-ve-blocks>` component, so template parts, synced patterns, Query loops
 * and the other dynamic blocks resolve against real site content exactly as
 * they do on a published page.
 *
 * The stylesheets every preview shares — the block library, theme.json
 * tokens, global styles, Font Library faces and the theme's `style.css` —
 * come from {@see styles()} once per batch instead of being repeated in
 * every pattern's HTML.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.13.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SiteEditor\Previews;

use ArtisanPackUI\VisualEditor\Fonts\Services\FontsCssGenerator;
use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor\PatternAdapter;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesEmissionTracker;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedPattern;
use ArtisanPackUI\VisualEditor\Support\BlockMarkupHydrator;
use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesEmissionResolver;
use Illuminate\Support\Facades\Blade;
use RuntimeException;

class PatternPreviewRenderer
{
	/**
	 * cms-framework's theme manager, resolved by name so visual-editor
	 * stays installable without cms-framework.
	 */
	protected const THEME_MANAGER_FQCN = 'ArtisanPackUI\\CMSFramework\\Modules\\Themes\\Managers\\ThemeManager';

	/**
	 * cms-framework's theme stylesheet reader (2.5+).
	 */
	protected const STYLESHEET_READER_FQCN = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Support\\ThemeStylesheetReader';

	/**
	 * Memoized active theme manifest; `false` until first read.
	 *
	 * @var array<string, mixed>|null|false
	 */
	protected array|null|false $activeTheme = false;

	/**
	 * @since 1.13.0
	 */
	public function __construct(
		protected BlockMarkupHydrator $hydrator,
		protected PatternPreviewCache $cache,
	) {
	}

	/**
	 * Whether the Blade renderer is registered in this install. Hosts that
	 * render with the React or Vue renderers only don't load it, and
	 * previews then fall back to the client's block-name tree.
	 *
	 * @since 1.13.0
	 */
	public function available(): bool
	{
		return class_exists( BlockRenderer::class ) && app()->bound( BlockRenderer::class );
	}

	/**
	 * Render a pattern to front-end HTML, served from the preview cache
	 * when possible.
	 *
	 * @since 1.13.0
	 *
	 * @throws RuntimeException When the Blade renderer isn't registered.
	 */
	public function render( ResolvedPattern $pattern ): string
	{
		if ( ! $this->available() ) {
			throw new RuntimeException( 'The visual-editor Blade renderer is not registered.' );
		}

		$rawContent = $this->renderableRawContent( $pattern );
		$theme      = $this->activeThemeSlug();
		$content    = $rawContent . "\n" . json_encode( $pattern->blocks );

		return $this->cache->remember(
			$pattern,
			$content,
			$theme ?? '',
			fn (): string => $this->renderUncached( $pattern, $rawContent, $theme ),
		);
	}

	/**
	 * The `<head>` markup every preview in a batch shares: the block
	 * library stylesheets and layout baseline (`<x-ve-blocks-styles>`),
	 * the global styles, the Font Library faces and the theme's front-end
	 * `style.css`. `<script>` tags are stripped — previews never run
	 * scripts.
	 *
	 * @since 1.13.0
	 */
	public function styles(): string
	{
		if ( ! $this->available() ) {
			return '';
		}

		$parts = [
			Blade::render( '<x-ve-blocks-styles :theme-json="$themeJson" />', [
				'themeJson' => $this->activeThemeJson(),
			] ),
		];

		$globalStyles = app( GlobalStylesEmissionResolver::class )->emit();

		if ( '' !== $globalStyles ) {
			$parts[] = '<style data-ve-global-styles>' . $globalStyles . '</style>';
		}

		$fonts = app( FontsCssGenerator::class )->read();

		if ( '' !== $fonts ) {
			$parts[] = '<style data-ve-fonts>' . $fonts . '</style>';
		}

		$themeCss = $this->themeStylesheet();

		if ( '' !== $themeCss ) {
			$parts[] = '<style data-ve-theme-stylesheet>' . $themeCss . '</style>';
		}

		return (string) preg_replace( '#<script\b[^>]*>.*?</script>#is', '', implode( "\n", $parts ) );
	}

	/**
	 * Render the pattern's block tree through `<x-ve-blocks>` — the same
	 * component, and so the same resolver passes, a published page uses.
	 *
	 * @since 1.13.0
	 */
	protected function renderUncached( ResolvedPattern $pattern, string $rawContent, ?string $theme ): string
	{
		// Theme patterns ship serialized markup (and a parse-shape tree
		// with no recovered text), so hydrate the markup; user patterns
		// store the editor-shape tree and no markup (#667).
		$tree = '' !== trim( $rawContent )
			? $this->hydrator->hydrate( $rawContent )
			: $pattern->blocks;

		// Global styles ship once per batch through styles(); keep the
		// component from inlining them into every cached pattern.
		app( GlobalStylesEmissionTracker::class )->markEmitted();

		return Blade::render( '<x-ve-blocks :tree="$tree" :default-theme="$theme" />', [
			'tree'  => $tree,
			'theme' => $theme,
		] );
	}

	/**
	 * The raw markup the editor would insert for this pattern, after the
	 * `ap.visualEditor.patternRender` filter — so the preview matches what
	 * a click inserts.
	 *
	 * @since 1.13.0
	 */
	protected function renderableRawContent( ResolvedPattern $pattern ): string
	{
		$payload = ( new PatternAdapter() )->toArray( $pattern );

		return $payload['content']['raw'];
	}

	/**
	 * The active theme's front-end `style.css`, read through cms-framework's
	 * path-contained reader.
	 *
	 * @since 1.13.0
	 */
	protected function themeStylesheet(): string
	{
		if ( ! class_exists( self::STYLESHEET_READER_FQCN ) || ! app()->bound( self::STYLESHEET_READER_FQCN ) ) {
			return '';
		}

		$css = app( self::STYLESHEET_READER_FQCN )->read( 'style.css' );

		return is_string( $css ) ? $css : '';
	}

	/**
	 * The active theme's `theme.json` settings + styles, the shape
	 * `<x-ve-blocks-styles>` compiles tokens from. Null without an active
	 * theme.
	 *
	 * @since 1.13.0
	 *
	 * @return array<string, mixed>|null
	 */
	protected function activeThemeJson(): ?array
	{
		$theme = $this->activeTheme();

		if ( null === $theme ) {
			return null;
		}

		return [
			'settings' => is_array( $theme['settings'] ?? null ) ? $theme['settings'] : [],
			'styles'   => is_array( $theme['styles'] ?? null ) ? $theme['styles'] : [],
		];
	}

	/**
	 * @since 1.13.0
	 */
	protected function activeThemeSlug(): ?string
	{
		$slug = $this->activeTheme()['slug'] ?? null;

		return is_string( $slug ) && '' !== $slug ? $slug : null;
	}

	/**
	 * @since 1.13.0
	 *
	 * @return array<string, mixed>|null
	 */
	protected function activeTheme(): ?array
	{
		if ( false !== $this->activeTheme ) {
			return $this->activeTheme;
		}

		$this->activeTheme = null;

		if ( class_exists( self::THEME_MANAGER_FQCN ) && app()->bound( self::THEME_MANAGER_FQCN ) ) {
			$theme = app( self::THEME_MANAGER_FQCN )->getActiveTheme();

			if ( is_array( $theme ) && ! empty( $theme['slug'] ) ) {
				$this->activeTheme = $theme;
			}
		}

		return $this->activeTheme;
	}
}
