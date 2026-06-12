<?php

/**
 * `<x-ve-blocks :tree="...">` Blade component.
 *
 * Renders a saved visual editor block tree into HTML using the
 * {@see \ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer} engine.
 * Resolves any `core/template-part` blocks in the tree inline (via the
 * shared {@see TemplatePartInliner}) and any `core/block`
 * (synced-pattern) references via {@see PatternInliner} so a single
 * render pass produces the final markup. Pass `:resolve-parts="false"`
 * or `:resolve-patterns="false"` to opt out of either resolution step
 * and render the raw tree.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\View\Components;

use ArtisanPackUI\VisualEditor\Resources\CommentInliner;
use ArtisanPackUI\VisualEditor\Resources\PatternInliner;
use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use ArtisanPackUI\VisualEditor\Resources\QueryInliner;
use ArtisanPackUI\VisualEditor\Resources\TemplatePartInliner;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesEmissionTracker;
use ArtisanPackUI\VisualEditor\SiteEditor\NavigationBlockRefResolver;
use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesEmissionResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Services\StateCssAccumulator;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class BlocksComponent extends Component
{
	/**
	 * Normalized block tree ready for rendering.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $tree;

	/**
	 * Theme slug forwarded to the global-styles provider so a block tree
	 * rendered against a non-default theme picks up that theme's record.
	 */
	protected ?string $defaultTheme;

	public function __construct(
		protected BlockRenderer $renderer,
		protected TemplatePartInliner $inliner,
		protected PatternInliner $patternInliner,
		protected QueryInliner $queryInliner,
		protected CommentInliner $commentInliner,
		protected PostResolver $postResolver,
		protected NavigationBlockRefResolver $navigationResolver,
		protected GlobalStylesEmissionResolver $globalStyles,
		protected GlobalStylesEmissionTracker $emissionTracker,
		protected ResponsiveCssAccumulator $responsiveAccumulator,
		protected StateCssAccumulator $stateAccumulator,
		mixed $tree = null,
		?string $defaultTheme = null,
		mixed $post = null,
		bool $resolveParts = true,
		bool $resolvePatterns = true,
		bool $resolveQueries = true,
		bool $resolveComments = true,
		bool $resolvePost = true,
		bool $resolveNavigation = true,
	) {
		$this->defaultTheme = $defaultTheme;

		$normalized = $this->normalizeTree( $tree );

		$resolved = $resolveParts
			? $this->inliner->inline( $normalized, $defaultTheme )
			: $normalized;

		// Pattern inlining runs after template-part inlining so a part
		// that itself contains a synced-pattern reference resolves in the
		// same pass.
		$resolved = $resolvePatterns
			? $this->patternInliner->inline( $resolved )
			: $resolved;

		// Query inlining runs before nav-block resolution so a `core/query`
		// loop with a navigation block inside it (rare but legal) still
		// gets each cloned nav block resolved to its menu items.
		$resolved = $resolveQueries
			? $this->queryInliner->inline( $resolved, is_object( $post ) ? $post : null )
			: $resolved;

		// Comment inlining runs after query inlining so a `core/query` loop
		// of posts where each iteration includes an `artisanpack/comments`
		// block still works — though in that nested scenario the inliner
		// will mark the comments block as unresolved unless the iteration
		// stamped `$post` through a follow-up pass (tracked separately).
		// For the common case (single-post template with a top-level
		// `comments` block + the host post in scope), the supplied `$post`
		// drives per-comment expansion via CommentResolver.
		$resolved = ( $resolveComments && is_object( $post ) )
			? $this->commentInliner->inline( $resolved, $post )
			: $resolved;

		// Top-level post resolution. When `$post` is supplied (e.g. a
		// single-post or single-page template), `PostResolver` stamps the
		// `_resolved*` keys on every `post-*` block in the saved tree —
		// post-title, post-content, post-author-name, post-featured-image,
		// post-comments-count, post-comments-link, post-comments-title,
		// etc. — so they render against the current entity without each
		// host having to wire a separate resolver pass. QueryInliner has
		// already stamped per-iteration posts inside `post-template`
		// expansions; this pass handles everything outside those loops.
		$resolved = ( $resolvePost && is_object( $post ) )
			? $this->postResolver->stampTree( $resolved, $post )
			: $resolved;

		// Navigation resolution mirrors the editor's read path
		// (Keystone #48 → #51): `core/navigation` blocks ship with
		// `__unstableLocation` and/or `ref` but empty `innerBlocks`.
		// The resolver stamps `ref` from a `(theme, location)` lookup,
		// strips the legacy attr, and projects the menu's `menu_items`
		// into the block's `innerBlocks` so the existing
		// `core/navigation` Blade view renders the items as
		// `<li><a>` markup without any per-renderer menu-lookup glue.
		// No-ops cleanly when `$defaultTheme` is null or cms-framework
		// isn't installed.
		$this->tree = ( $resolveNavigation && null !== $defaultTheme )
			? $this->navigationResolver->resolve( $resolved, $defaultTheme )
			: $resolved;
	}

	public function render(): View
	{
		// Render the block tree FIRST so every partial's
		// `BlockSupports::pushResponsive()` side-effect has happened
		// by the time we drain the accumulator. The drained block
		// is then prepended to the output by the view template.
		$html          = $this->renderer->render( $this->tree );
		$responsiveCss = $this->responsiveAccumulator->flush();
		$statesCss     = $this->stateAccumulator->flush();

		return view( 'visual-editor-renderer-blade::components.blocks', [
			'html'            => $html,
			'globalStylesCss' => $this->resolveGlobalStylesCss(),
			'responsiveCss'   => $responsiveCss,
			'statesCss'       => $statesCss,
		] );
	}

	/**
	 * Returns the compiled global-styles CSS the first time a renderer
	 * fires in the current request, then null on every subsequent call —
	 * so a page with multiple `<x-ve-blocks>` / `<x-ve-template>`
	 * instances emits the `<style>` block exactly once.
	 *
	 * @since 1.0.0
	 */
	protected function resolveGlobalStylesCss(): ?string
	{
		if ( $this->emissionTracker->hasEmitted() ) {
			return null;
		}

		$this->emissionTracker->markEmitted();

		$css = $this->globalStyles->emit();

		return '' === $css ? null : $css;
	}

	/**
	 * Accept the `tree` prop in any common Laravel shape (array, JSON string,
	 * Arrayable, stdClass) and coerce it to a list of block arrays.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function normalizeTree( mixed $tree ): array
	{
		if ( null === $tree ) {
			return [];
		}

		if ( is_string( $tree ) ) {
			$decoded = json_decode( $tree, true );

			$tree = is_array( $decoded ) ? $decoded : [];
		}

		if ( is_object( $tree ) && method_exists( $tree, 'toArray' ) ) {
			/** @var array<mixed> $tree */
			$tree = $tree->toArray();
		}

		if ( ! is_array( $tree ) ) {
			return [];
		}

		$normalized = [];

		foreach ( $tree as $block ) {
			if ( is_array( $block ) ) {
				$normalized[] = $block;
			}
		}

		return $normalized;
	}
}
