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

	public function __construct(
		protected ViewFactory $views,
		protected DynamicBlockRegistry $dynamicBlocks,
		protected ?SiteMetaResolver $siteMeta = null,
		protected ?LoginoutResolver $loginout = null,
	) {
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
		$this->renderIndex = 0;

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

		// `ap.visual-editor.rendered-block` — last-mile hook for packages
		// that need to post-process a rendered block. Runs on every block
		// (static or dynamic) so cross-cutting effects (frosted glass,
		// motion wrappers, contrast overlays, etc.) can decorate output
		// without each host having to modify the renderer. Callbacks
		// receive the final HTML, the block name, and the normalized
		// attributes; they must return an HTML string.
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
			$result    = $block->render( $validated );
			$html      = $this->coerceToString( $result );

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
