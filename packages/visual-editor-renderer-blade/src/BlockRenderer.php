<?php

/**
 * Server-side Blade renderer for visual editor block trees.
 *
 * Walks a `{ clientId, name, attributes, innerBlocks[] }` block tree and
 * produces HTML by looking up per-block Blade partials or invoking the
 * registered {@see \ArtisanPackUI\VisualEditor\Blocks\DynamicBlock::render()}
 * for dynamic blocks. Partials receive the block attributes plus a pre-rendered
 * `$innerBlocksHtml` string so containers can splice children into place.
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

use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingResolver;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityDecision;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityEvaluator;
use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\LoginoutResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\SiteMetaResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Stringable;
use Throwable;

class BlockRenderer
{
	/**
	 * Hard cap on inner-block recursion depth. A persisted tree
	 * deeper than this is treated as malformed — the walker stops
	 * recursing and rendering the subtree, and the incident is
	 * `report()`-ed so ops can find the offending content. Prevents
	 * a compromised import or a pathological block payload from
	 * stack-overflowing the PHP process on every subsequent visit.
	 *
	 * @since 1.4.0
	 */
	public const MAX_INNER_DEPTH = 128;

	/**
	 * Block names that consume site-meta `_resolved*` attributes.
	 *
	 * @var array<int, string>
	 */
	protected const SITE_META_BLOCKS = [
		'core/site-title',
		'core/site-tagline',
		'core/site-logo',
		// Phase I5 forks (#413) — same `_resolved*` contract, new namespace.
		'artisanpack/site-title',
		'artisanpack/site-tagline',
		'artisanpack/site-logo',
	];

	/**
	 * Block names that consume loginout `_resolved*` attributes (#522).
	 *
	 * @var array<int, string>
	 */
	protected const LOGINOUT_BLOCKS = [
		'core/loginout',
		'artisanpack/loginout',
	];

	/**
	 * Per-render counter exposed to Blade partials as `$renderIndex`
	 * so templates that need a per-instance suffix (e.g. form-control
	 * `id` ↔ `for` bindings on `artisanpack/search-field` /
	 * `search-filters-taxonomy`) can stay unique even when two
	 * instances carry identical attributes.
	 *
	 * Resets on every public {@see render} call so each request gets a
	 * stable, monotonically-increasing per-tree sequence.
	 *
	 * @since 1.1.0
	 */
	protected int $renderIndex = 0;

	/**
	 * Current depth into `innerBlocks` for the ongoing render call.
	 * Incremented by {@see renderInner()}, reset to 0 by {@see render()}.
	 * Read by {@see renderInner()} to short-circuit past {@see MAX_INNER_DEPTH}.
	 */
	protected int $innerDepth = 0;

	/**
	 * Request-scoped {@see VisibilityContext}, cached for the length of
	 * a single {@see render()} call so a large tree only pays the
	 * user-agent parse + role lookup once. Rebuilt on every top-level
	 * `render()` so long-lived workers (Octane / RoadRunner / queues)
	 * pick up fresh request state.
	 */
	protected ?VisibilityContext $visibilityContext = null;

	/**
	 * Monotonically-increasing counter used to mint unique CSS scope
	 * classes for CSS-hidden blocks, keeping the emitted `@media`
	 * rules from bleeding into other blocks that share a wrapper tag.
	 */
	protected int $visibilityScopeCounter = 0;

	public function __construct(
		protected ViewFactory $views,
		protected DynamicBlockRegistry $dynamicBlocks,
		protected ?SiteMetaResolver $siteMeta = null,
		protected ?LoginoutResolver $loginout = null,
		protected ?VisibilityEvaluator $visibility = null,
		protected ?BindingResolver $bindingResolver = null,
	) {
	}

	/**
	 * Resolve block bindings for the given tree before it hits the
	 * renderer's main loop. When a BindingResolver is wired, the tree is
	 * walked once so every block's `bindings` sidecar is folded into the
	 * static `attrs` — the downstream walker then renders as if the
	 * values had been persisted. Silent no-op when the resolver isn't
	 * bound (VE renderer-blade is installable without the bindings
	 * layer).
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @since 1.4.0
	 */
	public function resolveBindings( array $tree, ?BindingContext $context = null ): array
	{
		if ( null === $this->bindingResolver || [] === $tree ) {
			return $tree;
		}

		try {
			return $this->bindingResolver->resolve( $tree, $context );
		} catch ( Throwable $e ) {
			report( $e );

			return $tree;
		}
	}

	/**
	 * Render the given block tree to an HTML string.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree  Block tree produced by the editor.
	 */
	public function render( array $tree ): string
	{
		$this->renderIndex       = 0;
		$this->innerDepth        = 0;
		$this->visibilityContext = null;

		if ( null !== $this->visibility && $this->visibility->enabled() ) {
			$this->visibilityContext = $this->visibility->contextFromRequest();
		}

		// #650 — resolve bindings (Dynamic Content, custom fields,
		// post_core, relation) once before the walker runs. The
		// resolver only mutates blocks with a `bindings` sidecar; trees
		// without bindings round-trip byte-identically.
		$tree = $this->resolveBindings( $tree );

		$out = '';

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$out .= $this->renderBlock( $block );
		}

		return $out;
	}

	/**
	 * Render a single block, recursing through `innerBlocks` as needed.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $block  A single block node.
	 */
	public function renderBlock( array $block ): string
	{
		$name = isset( $block['name'] ) && is_string( $block['name'] ) ? trim( $block['name'] ) : '';

		if ( '' === $name ) {
			return '';
		}

		// #491 · #492 · #493 — Block Visibility gate. Server-side drop
		// so hidden blocks never emit markup and there is no flash of
		// hidden content. The visibility context is cached per-render
		// call, so a large tree only pays the user-agent parse + role
		// lookup once. Hidden blocks skip the render index bump because
		// they never reach the partial that would consume it, keeping
		// the per-instance suffix stable across visitors.
		//
		// The decision is a LOCAL variable rather than instance state
		// because `renderStatic()` / `renderDynamic()` (below) walk
		// `innerBlocks` recursively via {@see renderInner()} —
		// stashing the decision on `$this` would let each child
		// overwrite its parent's decision before line
		// wrapWithScreenSizeCss reads it.
		$cssHiddenDecision = null;

		if ( null !== $this->visibility && null !== $this->visibilityContext ) {
			$decision = $this->visibility->evaluate( array_merge( $block, [ 'name' => $name ] ), $this->visibilityContext );

			if ( $decision->isHidden() ) {
				return '';
			}

			if ( $decision->isCssHidden() ) {
				$cssHiddenDecision = $decision;
			}
		}

		// Take this block's render index BEFORE walking innerBlocks so
		// the parent's index is stable even though child blocks bump
		// the counter recursively. Partials receive this value via the
		// `$renderIndex` Blade variable so they can mint per-instance
		// IDs without colliding when two blocks share attributes.
		$renderIndex = $this->renderIndex++;

		$attributes      = $this->normalizeAttributes( $block['attributes'] ?? [] );
		$attributes      = $this->stampSiteMeta( $name, $attributes );
		$attributes      = $this->stampLoginout( $name, $attributes );
		$innerBlocks     = $this->normalizeInnerBlocks( $block['innerBlocks'] ?? [] );
		$innerBlocksHtml = $this->renderInner( $innerBlocks );

		$html = $this->dynamicBlocks->has( $name )
			? $this->renderDynamic( $name, $attributes, $innerBlocksHtml, $innerBlocks, $renderIndex )
			: $this->renderStatic( $name, $attributes, $innerBlocksHtml, $innerBlocks, $renderIndex );

		// If the screen-size rule flagged this block as CSS-hidden at
		// certain breakpoints, wrap its markup in a scope class + emit
		// the matching `@media` rules so the client hides it at those
		// widths without any runtime JS. Zero-cost when the rule is
		// inactive.
		if ( null !== $cssHiddenDecision && [] !== $cssHiddenDecision->hiddenBreakpoints ) {
			$html = $this->wrapWithScreenSizeCss( $html, $cssHiddenDecision );
		}

		// `ap.visual-editor.rendered-block` — last-mile hook for packages
		// that need to post-process a rendered block. Runs on every block
		// (static or dynamic) so cross-cutting effects (frosted glass,
		// motion wrappers, contrast overlays, etc.) can decorate output
		// without each host having to modify the renderer. Callbacks
		// receive the final HTML, the block name, and the normalized
		// attributes; they must return an HTML string.
		//
		// RECURSION: `renderInner` walks `innerBlocks` through this same
		// method, so the filter fires once per block AT EVERY LEVEL of
		// the tree — a callback that wraps `$html` on a container block
		// (e.g. `core/group`) will ALSO wrap every descendant block
		// unless it gates on `$name` / `$attributes`. Decorators that
		// mean "wrap the outer container only" should branch on the
		// block name before mutating the HTML.
		//
		// ATTRIBUTES SHAPE: `$attributes` is the post-normalization
		// array, which for site-meta and loginout blocks already carries
		// the internal `_resolved*` keys stamped by {@see stampSiteMeta}
		// / {@see stampLoginout} (`_resolvedSiteTitle`,
		// `_resolvedIsUserLoggedIn`, `_resolvedLoginFormHtml`, etc.).
		// Those keys are a package-internal contract and may change
		// without notice — callbacks should treat them as read-only
		// side-channel data, not stable public attributes.
		if ( function_exists( 'applyFilters' ) ) {
			$filtered = applyFilters( 'ap.visual-editor.rendered-block', $html, $name, $attributes );

			if ( is_string( $filtered ) ) {
				$html = $filtered;
			}
		}

		return $html;
	}

	/**
	 * Recursive sibling of {@see render} that walks `innerBlocks`
	 * without resetting the per-tree {@see $renderIndex} counter.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 */
	protected function renderInner( array $tree ): string
	{
		if ( $this->innerDepth >= self::MAX_INNER_DEPTH ) {
			report( new \RuntimeException( sprintf(
				'BlockRenderer inner-block depth cap (%d) exceeded — skipping remaining subtree. Likely a malformed or attacker-crafted block payload.',
				self::MAX_INNER_DEPTH,
			) ) );

			return '';
		}

		$this->innerDepth++;

		$out = '';

		try {
			foreach ( $tree as $block ) {
				if ( ! is_array( $block ) ) {
					continue;
				}

				$out .= $this->renderBlock( $block );
			}
		} finally {
			$this->innerDepth--;
		}

		return $out;
	}

	/**
	 * Stamps `_resolvedSite*` attributes onto `core/site-*` blocks so the
	 * block partials can read site title / tagline / URL / logo without
	 * each one knowing about cms-framework's settings helper.
	 *
	 * Pre-existing `_resolved*` keys win — a host that has already
	 * resolved values upstream (custom Inertia payload, theme
	 * customizer, etc.) keeps full control. The resolver acts as the
	 * default fallback, not an override.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array<string, mixed>
	 */
	protected function stampSiteMeta( string $name, array $attributes ): array
	{
		if ( null === $this->siteMeta || ! in_array( $name, self::SITE_META_BLOCKS, true ) ) {
			return $attributes;
		}

		$meta = $this->siteMeta->resolve();

		$stamped = [
			'_resolvedSiteTitle'   => $meta['title'],
			'_resolvedSiteTagline' => $meta['description'],
			'_resolvedSiteUrl'     => $meta['url'],
			'_resolvedLogoUrl'     => $meta['logoUrl'],
		];

		// Existing values win — array_merge with the resolver defaults
		// first, host-supplied attributes layered on top.
		return array_merge( $stamped, $attributes );
	}

	/**
	 * Stamps `_resolved*` attributes onto `loginout` blocks so the
	 * partial can emit the right link / form for the current viewer
	 * without each renderer reaching into Laravel's auth stack. The
	 * loggedIn flag, URL, label, wrapper classes, and pre-rendered
	 * login form (when opted in) come from {@see LoginoutResolver}.
	 *
	 * Pre-existing `_resolved*` keys win — hosts that have already
	 * resolved the envelope upstream (Inertia payload, custom auth
	 * middleware, etc.) keep control. The resolver is the default
	 * fallback, not an override. Mirrors {@see stampSiteMeta}.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array<string, mixed>
	 */
	protected function stampLoginout( string $name, array $attributes ): array
	{
		if ( null === $this->loginout || ! in_array( $name, self::LOGINOUT_BLOCKS, true ) ) {
			return $attributes;
		}

		$envelope = $this->loginout->resolve( $attributes );

		$stamped = [
			'_resolvedIsUserLoggedIn' => $envelope['isUserLoggedIn'],
			'_resolvedLoginoutUrl'    => $envelope['url'],
			'_resolvedLoginoutLabel'  => $envelope['label'],
			'_resolvedLoginoutClass'  => $envelope['classes'],
			'_resolvedLoginFormHtml'  => $envelope['loginFormHtml'],
		];

		return array_merge( $stamped, $attributes );
	}

	/**
	 * Resolve the registered {@see DynamicBlock} and coerce its return value
	 * to an HTML string.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>             $attributes
	 * @param  array<int, array<string, mixed>> $innerBlocks  Normalized child tree forwarded to the static
	 *                                                        fallback so partials can read `$innerBlocks` even
	 *                                                        when the dynamic handler is missing or throws.
	 * @param  int                              $renderIndex  Per-tree visit counter forwarded to the static
	 *                                                        fallback so partials still see a stable
	 *                                                        `$renderIndex` value.
	 */
	protected function renderDynamic( string $name, array $attributes, string $innerBlocksHtml, array $innerBlocks = [], int $renderIndex = 0 ): string
	{
		$block = $this->dynamicBlocks->get( $name );

		if ( null === $block ) {
			return $this->renderStatic( $name, $attributes, $innerBlocksHtml, $innerBlocks, $renderIndex );
		}

		try {
			$validated = $block->validateAttrs( $attributes );

			// #650 — dynamic blocks that need their inner tree at render
			// time (e.g. `artisanpack/dynamic-loop` iterating a template)
			// implement WantsInnerBlocks; the renderer forwards the
			// unrendered innerBlocks tree so the block can walk or
			// duplicate it per iteration.
			if ( $block instanceof \ArtisanPackUI\VisualEditor\Blocks\WantsInnerBlocks ) {
				$result = $block->renderWithInner( $validated, $innerBlocks );
			} else {
				$result = $block->render( $validated );
			}

			$html = $this->coerceToString( $result );

			// #490 — dynamic blocks build their own wrapper HTML and
			// don't go through `BlockSupports::compile`, so the
			// gradient-border pipeline never sees them by default.
			// Post-process the rendered HTML to push the rule into the
			// per-request accumulator AND stamp the scope class onto
			// the first opening tag. Use `$validated` (not raw
			// `$attributes`) so the scope class + emitted CSS stay
			// aligned with the input the dynamic block actually
			// rendered from — keeping editor preview and saved markup
			// in lockstep across attribute normalization passes.
			// No-op when the block has no gradient configured.
			$html = BlockSupports::applyGradientBorder( $html, $validated );

			return $html;
		} catch ( Throwable $e ) {
			report( $e );

			return $this->renderStatic( $name, $attributes, $innerBlocksHtml, $innerBlocks, $renderIndex );
		}
	}

	/**
	 * Resolve the block's Blade partial and render it with the block's
	 * attributes + pre-rendered children.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>             $attributes
	 * @param  array<int, array<string, mixed>> $innerBlocks
	 * @param  int                              $renderIndex  Per-tree visit counter exposed to the partial
	 *                                                        as `$renderIndex`. Templates that need a
	 *                                                        per-instance suffix (e.g. form-control ids)
	 *                                                        should mix it into their id strings to keep
	 *                                                        identical-attribute siblings unique.
	 */
	protected function renderStatic( string $name, array $attributes, string $innerBlocksHtml, array $innerBlocks = [], int $renderIndex = 0 ): string
	{
		$partial = $this->resolvePartial( $name );

		if ( null === $partial ) {
			return $this->renderFallback( $name, $innerBlocksHtml );
		}

		$data = [
			'blockName'       => $name,
			'attributes'      => $attributes,
			'attrs'           => $attributes,
			'innerBlocks'     => $innerBlocks,
			'innerBlocksHtml' => $innerBlocksHtml,
			'renderIndex'     => $renderIndex,
		];

		try {
			return $this->coerceToString( $this->views->make( $partial, $data ) );
		} catch ( Throwable $e ) {
			report( $e );

			return $this->renderFallback( $name, $innerBlocksHtml );
		}
	}

	/**
	 * Resolve the Blade view name for a block.
	 *
	 * Tries `visual-editor-renderer-blade::blocks.{namespace}.{block}` first
	 * so host apps can override individual partials via
	 * `resources/views/vendor/visual-editor-renderer-blade/blocks/`.
	 *
	 * @since 1.0.0
	 */
	protected function resolvePartial( string $name ): ?string
	{
		[ $namespace, $block ] = $this->splitBlockName( $name );

		if ( '' === $namespace || '' === $block ) {
			return null;
		}

		$view = sprintf( 'visual-editor-renderer-blade::blocks.%s.%s', $namespace, $block );

		return $this->views->exists( $view ) ? $view : null;
	}

	/**
	 * Fallback markup for blocks that have no registered partial. Wraps the
	 * rendered children in a comment-bracketed `<div>` so editors can tell
	 * which block type is unknown without breaking the surrounding layout.
	 *
	 * @since 1.0.0
	 */
	protected function renderFallback( string $name, string $innerBlocksHtml ): string
	{
		$safeName = htmlspecialchars( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return sprintf(
			'<!-- visual-editor: no partial for %1$s --><div data-ve-unknown-block="%1$s">%2$s</div>',
			$safeName,
			$innerBlocksHtml
		);
	}

	/**
	 * Wrap a rendered block in a CSS scope class + emit `@media`
	 * `display:none` rules so the screen-size visibility rule can hide
	 * a block at named breakpoints without any runtime JavaScript.
	 *
	 * The wrapper is a single `<div>` that inherits its parent's flow
	 * so container styles still apply — the block's own root tag stays
	 * intact for CSS selectors already targeting it. The scope class
	 * is per-block so two blocks hidden at different breakpoints don't
	 * accidentally share their rules.
	 *
	 * @since 1.4.0
	 */
	protected function wrapWithScreenSizeCss( string $html, VisibilityDecision $decision ): string
	{
		$breakpointRegistry = null;

		try {
			$breakpointRegistry = app( BreakpointRegistry::class );
		} catch ( Throwable $e ) {
			// Container binding missing — fall back to a no-op wrap.
			return $html;
		}

		$this->visibilityScopeCounter++;
		$scopeClass = sprintf( 've-vis-%d', $this->visibilityScopeCounter );

		$minWidths = $breakpointRegistry->all();

		// Sort breakpoints ascending by min-width so we can pair each
		// key with the NEXT breakpoint's min-width — 1 as its upper
		// bound. The last (widest) breakpoint has no upper bound and
		// emits a plain `min-width` query. This turns each checkbox
		// into an independent RANGE ("hide at md" = hide 768–1023 only,
		// not "hide from 768 and up forever"), matching the checkbox UI
		// paradigm and letting editors pick non-contiguous ranges.
		asort( $minWidths );
		$orderedKeys      = array_keys( $minWidths );
		$orderedMinWidths = array_values( $minWidths );

		$css = '';

		foreach ( $decision->hiddenBreakpoints as $key ) {
			$position = array_search( $key, $orderedKeys, true );

			if ( false === $position ) {
				continue;
			}

			$min = $orderedMinWidths[ $position ];

			if ( ! is_int( $min ) || $min <= 0 ) {
				continue;
			}

			$nextMin = $orderedMinWidths[ $position + 1 ] ?? null;

			$css .= is_int( $nextMin ) && $nextMin > $min
				? sprintf(
					'@media (min-width:%dpx) and (max-width:%dpx){.%s{display:none !important;}}',
					$min,
					$nextMin - 1,
					$scopeClass
				)
				: sprintf(
					'@media (min-width:%dpx){.%s{display:none !important;}}',
					$min,
					$scopeClass
				);
		}

		if ( '' === $css ) {
			return $html;
		}

		return sprintf(
			'<div class="%s" data-ve-vis-scope>%s<style>%s</style></div>',
			$scopeClass,
			$html,
			$css
		);
	}

	/**
	 * Split a block name into [namespace, block] suitable for view resolution.
	 *
	 * @since 1.0.0
	 *
	 * @return array{0: string, 1: string}
	 */
	protected function splitBlockName( string $name ): array
	{
		$parts = explode( '/', $name, 2 );

		if ( 2 !== count( $parts ) ) {
			return [ '', '' ];
		}

		return [ trim( $parts[0] ), trim( $parts[1] ) ];
	}

	/**
	 * Coerce whatever a render callback returned to a string.
	 *
	 * @since 1.0.0
	 */
	protected function coerceToString( mixed $value ): string
	{
		if ( $value instanceof View ) {
			try {
				return $value->render();
			} catch ( BindingResolutionException $e ) {
				report( $e );

				return '';
			}
		}

		if ( $value instanceof Htmlable ) {
			return $value->toHtml();
		}

		if ( is_string( $value ) ) {
			return $value;
		}

		if ( $value instanceof Stringable || ( is_object( $value ) && method_exists( $value, '__toString' ) ) ) {
			return (string) $value;
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return '';
	}

	/**
	 * Normalize a block's `attributes` value into an array.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function normalizeAttributes( mixed $attributes ): array
	{
		if ( ! is_array( $attributes ) ) {
			return [];
		}

		/** @var array<string, mixed> $attributes */
		return $attributes;
	}

	/**
	 * Normalize a block's `innerBlocks` value into a list of block arrays.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function normalizeInnerBlocks( mixed $innerBlocks ): array
	{
		if ( ! is_array( $innerBlocks ) ) {
			return [];
		}

		$list = [];

		foreach ( $innerBlocks as $child ) {
			if ( is_array( $child ) ) {
				$list[] = $child;
			}
		}

		return $list;
	}
}
