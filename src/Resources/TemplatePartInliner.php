<?php

/**
 * TemplatePartInliner service.
 *
 * Walks a saved Gutenberg block tree and replaces every `core/template-part`
 * block with the same block carrying its resolved part's blocks as
 * `innerBlocks`. Front-end renderers (Blade, React, Vue) consume the
 * post-inlining tree so a single recursive renderer pass produces the
 * final HTML — there is no per-renderer template-part lookup.
 *
 * Recursion guard: an in-flight stack of `theme/slug` keys catches cycles
 * (header → nav → header) and a depth counter caps how deep the resolution
 * chain can go (default 10). Either guard converts the offending block
 * into a marker whose `attributes._resolutionError` records the reason —
 * the renderers translate that into a graceful empty render in production
 * and a visible warning in dev.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Resources;

class TemplatePartInliner
{
	/**
	 * Default cap on how many levels deep the template-part chain can
	 * resolve before the renderer renders the fallback marker. Real
	 * sites rarely nest parts more than two or three deep; ten leaves
	 * generous headroom while still terminating before PHP exhausts
	 * its stack.
	 */
	public const DEFAULT_MAX_DEPTH = 10;

	public const ERROR_MISSING_SLUG  = 'missing-slug';
	public const ERROR_NOT_FOUND     = 'not-found';
	public const ERROR_CYCLE         = 'cycle';
	public const ERROR_DEPTH_LIMIT   = 'depth-limit';

	public function __construct(
		protected int $maxDepth = self::DEFAULT_MAX_DEPTH
	) {
	}

	/**
	 * Walks `$tree` and returns a copy with every `core/template-part`
	 * block carrying its resolved part's blocks under `innerBlocks`.
	 *
	 * The returned shape matches the input — the same `clientId`, `name`,
	 * and `attributes` keys are preserved so existing renderers can render
	 * the inlined tree without special-casing template parts. Blocks the
	 * inliner cannot resolve (missing slug, missing part, cycle, depth
	 * overflow) keep the `core/template-part` name and gain a synthetic
	 * `_resolutionError` attribute the renderers detect.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree            Parsed block tree.
	 * @param  ?string                           $defaultTheme    Theme to assume when a `core/template-part` block omits the `theme` attribute.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function inline( array $tree, ?string $defaultTheme = null ): array
	{
		return $this->walk( $tree, $defaultTheme, [], 0 );
	}

	/**
	 * Recursive walker shared by the public entry point and inner-block
	 * descent. Keeps the cycle stack and depth counter on the stack frame
	 * (rather than instance state) so concurrent calls into the same
	 * inliner instance don't leak resolution context across trees.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 * @param  array<int, string>                $stack
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function walk( array $tree, ?string $defaultTheme, array $stack, int $depth ): array
	{
		$out = [];

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';

			if ( 'core/template-part' === $name ) {
				$out[] = $this->resolvePart( $block, $defaultTheme, $stack, $depth );

				continue;
			}

			$innerBlocks          = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : [];
			$block['innerBlocks'] = $this->walk( $innerBlocks, $defaultTheme, $stack, $depth );
			$out[]                = $block;
		}

		return $out;
	}

	/**
	 * Looks the part up by slug + theme and returns the block with the
	 * resolved blocks attached as `innerBlocks`.
	 *
	 * Resolution failures (missing slug, missing record, cycle, depth
	 * overflow) return the original block stamped with a
	 * `_resolutionError` attribute so the renderer can react without
	 * hard-failing the whole template.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $block
	 * @param  array<int, string>    $stack
	 *
	 * @return array<string, mixed>
	 */
	protected function resolvePart( array $block, ?string $defaultTheme, array $stack, int $depth ): array
	{
		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];
		$slug       = isset( $attributes['slug'] ) && is_string( $attributes['slug'] ) ? trim( $attributes['slug'] ) : '';
		$theme      = isset( $attributes['theme'] ) && is_string( $attributes['theme'] ) ? trim( $attributes['theme'] ) : '';

		if ( '' === $theme && null !== $defaultTheme ) {
			$theme = $defaultTheme;
		}

		if ( '' === $slug ) {
			return $this->markUnresolved( $block, self::ERROR_MISSING_SLUG, $slug, $theme );
		}

		if ( $depth >= $this->maxDepth ) {
			return $this->markUnresolved( $block, self::ERROR_DEPTH_LIMIT, $slug, $theme );
		}

		$key = $theme . '/' . $slug;

		if ( in_array( $key, $stack, true ) ) {
			return $this->markUnresolved( $block, self::ERROR_CYCLE, $slug, $theme );
		}

		$blocks = $this->findPartBlocks( $slug );

		if ( null === $blocks ) {
			return $this->markUnresolved( $block, self::ERROR_NOT_FOUND, $slug, $theme );
		}

		$childStack       = $stack;
		$childStack[]     = $key;
		$resolvedChildren = $this->walk( $blocks, '' !== $theme ? $theme : $defaultTheme, $childStack, $depth + 1 );

		return [
			'clientId'    => $block['clientId'] ?? null,
			'name'        => 'core/template-part',
			'attributes'  => array_merge( $attributes, [
				'slug'  => $slug,
				'theme' => $theme,
			] ),
			'innerBlocks' => $resolvedChildren,
		];
	}

	/**
	 * cms-framework's TemplatePartResolver class. Lookups go through it
	 * when the package is installed; without cms-framework no part
	 * resolves (Phase H install gate is the user-facing surface).
	 */
	protected const RESOLVER_CLASS = '\\ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplatePartResolver';

	/**
	 * Looks the part up via cms-framework's resolver. Returns the part's
	 * block tree or null when the resolver returns no entity, the
	 * resolver isn't installed, or anything goes wrong reading the
	 * resolved entity's blocks.
	 *
	 * The resolver scopes by the host's active theme; the inliner used
	 * to constrain by an explicit theme attribute, but cms-framework's
	 * design treats theme scope as implicit. Block-markup `theme`
	 * attributes are preserved on the returned block for documentation,
	 * but they no longer drive the lookup.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>|null
	 */
	protected function findPartBlocks( string $slug ): ?array
	{
		if ( ! class_exists( self::RESOLVER_CLASS ) ) {
			return null;
		}

		$resolved = app( self::RESOLVER_CLASS )->resolve( $slug );

		if ( null === $resolved ) {
			return null;
		}

		$blocks = $resolved->blocks ?? null;

		return is_array( $blocks ) ? $blocks : null;
	}

	/**
	 * Returns the block stamped with the resolution-failure reason so
	 * downstream renderers can short-circuit to their fallback markup.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	protected function markUnresolved( array $block, string $reason, string $slug, string $theme ): array
	{
		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];

		return [
			'clientId'    => $block['clientId'] ?? null,
			'name'        => 'core/template-part',
			'attributes'  => array_merge( $attributes, [
				'slug'              => $slug,
				'theme'             => $theme,
				'_resolutionError' => $reason,
			] ),
			'innerBlocks' => [],
		];
	}
}
