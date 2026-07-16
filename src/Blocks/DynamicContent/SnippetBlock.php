<?php

/**
 * `artisanpack/snippet` server-side renderer.
 *
 * Resolves the referenced snippet by slug and renders its block tree
 * through the visual-editor-renderer-blade `BlockRenderer` (soft-dep).
 * Cycles are caught by {@see SnippetCycleGuard}; a cycle renders a
 * warning HTML placeholder instead of the tree so the incident is
 * visible in preview/prod without stack-overflowing.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\DynamicContent;

use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditor\Models\Snippet;
use ArtisanPackUI\VisualEditor\Services\DynamicContent\SnippetCycleGuard;
use Throwable;

class SnippetBlock extends DynamicBlock
{
	public const BLOCK_NAME = 'artisanpack/snippet';

	/**
	 * @param  array<string, bool>  $visited  Slug set of enclosing snippets — used
	 *                                        only during recursive expansion.
	 */
	public function __construct(
		protected SnippetCycleGuard $cycleGuard,
		protected array $visited = [],
		protected int $depth = 0,
	) {
	}

	public function name(): string
	{
		return self::BLOCK_NAME;
	}

	public function render( array $attrs )
	{
		$slug = is_string( $attrs['slug'] ?? null ) ? trim( $attrs['slug'] ) : '';

		if ( '' === $slug ) {
			return '';
		}

		$snippet = $this->cycleGuard->checkPlacement( $slug, $this->visited, $this->depth );

		if ( null === $snippet ) {
			return $this->cyclePlaceholder( $slug );
		}

		$blocks = is_array( $snippet->blocks ) ? $snippet->blocks : [];

		if ( [] === $blocks ) {
			return '';
		}

		return $this->renderTree( $blocks, $slug );
	}

	/**
	 * @param  array<int, array<string, mixed>>  $blocks
	 *
	 * @since 1.4.0
	 */
	protected function renderTree( array $blocks, string $ownerSlug ): string
	{
		$rendererClass = '\\ArtisanPackUI\\VisualEditorRendererBlade\\BlockRenderer';

		if ( ! class_exists( $rendererClass ) ) {
			return '';
		}

		try {
			// A fresh renderer resolution is safe — the class is stateless
			// beyond per-call bookkeeping and the container returns the
			// singleton binding. Nested snippet placements resolve
			// through the DynamicBlockRegistry with an updated `$visited`
			// set — see `nestedInstance()` below and its wire-up in
			// `VisualEditorServiceProvider::registerDynamicContentBlocks()`.
			$renderer = app( $rendererClass );

			$this->pushVisitedInRegistry( $ownerSlug );

			$html = $renderer->render( $blocks );
		} catch ( Throwable $e ) {
			report( $e );

			return $this->errorPlaceholder();
		} finally {
			$this->popVisitedInRegistry( $ownerSlug );
		}

		return is_string( $html ) ? $html : '';
	}

	/**
	 * The DynamicBlockRegistry stores a single SnippetBlock instance, so
	 * nested placements need a way to see the outer snippet's slug in
	 * their visited-set. We push/pop through the registry — safe because
	 * PHP is single-threaded per request.
	 *
	 * @since 1.4.0
	 */
	protected function pushVisitedInRegistry( string $slug ): void
	{
		$this->visited[ $slug ] = true;
		$this->depth++;
	}

	protected function popVisitedInRegistry( string $slug ): void
	{
		unset( $this->visited[ $slug ] );
		$this->depth = max( 0, $this->depth - 1 );
	}

	protected function cyclePlaceholder( string $slug ): string
	{
		$safe = htmlspecialchars( $slug, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return sprintf(
			'<div class="ve-snippet-cycle" role="note" aria-label="Snippet cycle">'
				. '<strong>Snippet cycle detected:</strong> "%s" cannot reference itself.</div>',
			$safe
		);
	}

	protected function errorPlaceholder(): string
	{
		return '<div class="ve-snippet-error" role="note">Snippet failed to render.</div>';
	}
}
