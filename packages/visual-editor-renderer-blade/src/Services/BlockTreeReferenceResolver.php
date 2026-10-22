<?php

/**
 * Reference-resolution passes shared by every Blade render entry point.
 *
 * A saved block tree points at content stored elsewhere: template parts
 * (`core/template-part`), synced patterns (`core/block`) and menus
 * (`core/navigation` with `__unstableLocation` or `ref`). The
 * `<x-ve-blocks>` and `<x-ve-template>` components expand those
 * references before rendering, and a navigation overlay part rendered
 * from inside the `core/navigation` partial needs the very same
 * expansion (#973). This service is the one place those passes live.
 *
 * Menu resolution needs the theme the page is rendered against. The
 * components run their render inside {@see withTheme()}, which records
 * that theme for the duration of the render so a nested render (the
 * overlay part) resolves menu locations against the same theme via
 * {@see resolveStandalone()}.
 *
 * Bound via `$this->app->scoped()` so a long-lived worker (Octane,
 * queue) never carries a theme across requests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.2
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

use ArtisanPackUI\VisualEditor\Resources\PatternInliner;
use ArtisanPackUI\VisualEditor\Resources\TemplatePartInliner;
use ArtisanPackUI\VisualEditor\SiteEditor\NavigationBlockRefResolver;
use ArtisanPackUI\VisualEditor\Support\BlockShape;

class BlockTreeReferenceResolver
{
	/**
	 * Theme slug of the render currently in progress, or null outside a
	 * render (or when the render has no theme).
	 *
	 * @since 1.15.2
	 */
	protected ?string $currentTheme = null;

	/**
	 * Creates the resolver.
	 *
	 * @since 1.15.2
	 *
	 * @param  TemplatePartInliner         $templatePartInliner  Inlines `core/template-part` references.
	 * @param  PatternInliner              $patternInliner       Inlines `core/block` synced-pattern references.
	 * @param  NavigationBlockRefResolver  $navigationResolver   Projects menu items into `core/navigation` blocks.
	 */
	public function __construct(
		protected TemplatePartInliner $templatePartInliner,
		protected PatternInliner $patternInliner,
		protected NavigationBlockRefResolver $navigationResolver,
	) {
	}

	/**
	 * Inlines template parts, then synced patterns, and re-normalizes the
	 * block shape of whatever they spliced in.
	 *
	 * Pattern inlining runs after template-part inlining so a part that
	 * itself contains a synced-pattern reference resolves in the same
	 * pass. Parts and patterns splice in stored subtrees that may be in
	 * the legacy `attrs` shape, hence the second normalization.
	 *
	 * @since 1.15.2
	 *
	 * @param  array<int, array<string, mixed>>  $tree             Normalized block tree.
	 * @param  ?string                           $theme            Theme assumed by template parts without a `theme` attribute.
	 * @param  bool                              $resolveParts     Whether to inline template parts.
	 * @param  bool                              $resolvePatterns  Whether to inline synced patterns.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function inlineReferences( array $tree, ?string $theme, bool $resolveParts = true, bool $resolvePatterns = true ): array
	{
		$resolved = $resolveParts
			? $this->templatePartInliner->inline( $tree, $theme )
			: $tree;

		$resolved = $resolvePatterns
			? $this->patternInliner->inline( $resolved )
			: $resolved;

		return ( $resolveParts || $resolvePatterns )
			? BlockShape::normalizeTree( $resolved )
			: $resolved;
	}

	/**
	 * Resolves every `core/navigation` block's menu: stamps `ref` from a
	 * `(theme, location)` lookup and projects the menu's items into the
	 * block's `innerBlocks`. Returns the tree untouched without a theme,
	 * since a menu location only means something within one.
	 *
	 * @since 1.15.2
	 *
	 * @param  array<int, array<string, mixed>>  $tree   Block tree.
	 * @param  ?string                           $theme  Theme the menu locations belong to.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function resolveNavigation( array $tree, ?string $theme ): array
	{
		return null !== $theme
			? $this->navigationResolver->resolve( $tree, $theme )
			: $tree;
	}

	/**
	 * Runs every reference pass on a tree rendered outside the page tree,
	 * against the theme of the render in progress.
	 *
	 * Used for a navigation overlay part, whose blocks are fetched while
	 * the page is already rendering and so never went through the page's
	 * passes (#973).
	 *
	 * @since 1.15.2
	 *
	 * @param  array<int, mixed>  $tree  Block tree in either the `attributes` or legacy `attrs` shape.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function resolveStandalone( array $tree ): array
	{
		$blocks = array_values( array_filter( $tree, 'is_array' ) );

		return $this->resolveNavigation(
			$this->inlineReferences( BlockShape::normalizeTree( $blocks ), $this->currentTheme ),
			$this->currentTheme,
		);
	}

	/**
	 * Runs `$callback` with `$theme` recorded as the theme of the render
	 * in progress, restoring the previous theme afterwards (also when the
	 * callback throws), so nested renders against different themes unwind
	 * correctly.
	 *
	 * @since 1.15.2
	 *
	 * @template TReturn
	 *
	 * @param  ?string                $theme     Theme slug of the render.
	 * @param  callable(): TReturn    $callback  The render.
	 *
	 * @return TReturn
	 */
	public function withTheme( ?string $theme, callable $callback ): mixed
	{
		$previous           = $this->currentTheme;
		$this->currentTheme = $theme;

		try {
			return $callback();
		} finally {
			$this->currentTheme = $previous;
		}
	}

	/**
	 * Theme slug of the render in progress, or null.
	 *
	 * @since 1.15.2
	 */
	public function currentTheme(): ?string
	{
		return $this->currentTheme;
	}
}
