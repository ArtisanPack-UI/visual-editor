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
 * The visited-set for cycle detection is threaded through Laravel's
 * container (`snippet.visited`) as request-scoped state. Container
 * bindings are per-container-instance, and Laravel FPM builds one
 * container per request, so this is race-free under FPM. Under Octane
 * / RoadRunner / Swoole the framework flushes scoped bindings between
 * requests, so the state is bounded to the current request there
 * too. This avoids mutable state on the singleton block instance that
 * DynamicBlockRegistry hands the renderer.
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
	 * Container scope key. Read/written via `app()->scoped(...)`, so
	 * every request sees an isolated visited-set. Not shared across
	 * requests even under long-lived workers.
	 *
	 * @since 1.4.0
	 */
	protected const SCOPE_KEY = 've.snippet.visited';

	public function __construct( protected SnippetCycleGuard $cycleGuard )
	{
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

		$state   = $this->scopeState();
		$visited = $state['visited'];
		$depth   = $state['depth'];

		$snippet = $this->cycleGuard->checkPlacement( $slug, $visited, $depth );

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

		$this->pushScope( $ownerSlug );

		try {
			$renderer = app( $rendererClass );
			$html     = $renderer->render( $blocks );
		} catch ( Throwable $e ) {
			report( $e );

			return $this->errorPlaceholder();
		} finally {
			$this->popScope( $ownerSlug );
		}

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Read the current request's visited-set + depth, defaulting to an
	 * empty scope. The result is safe to inspect — mutations happen via
	 * push/pop below, not by writing to the returned array.
	 *
	 * @return array{visited: array<string, bool>, depth: int}
	 *
	 * @since 1.4.0
	 */
	protected function scopeState(): array
	{
		$state = app()->bound( self::SCOPE_KEY ) ? app( self::SCOPE_KEY ) : null;

		if ( ! is_array( $state )
			|| ! is_array( $state['visited'] ?? null )
			|| ! is_int( $state['depth'] ?? null )
		) {
			return [ 'visited' => [], 'depth' => 0 ];
		}

		return $state;
	}

	protected function pushScope( string $slug ): void
	{
		$state                       = $this->scopeState();
		$state['visited'][ $slug ]   = true;
		$state['depth']              = $state['depth'] + 1;

		app()->instance( self::SCOPE_KEY, $state );
	}

	protected function popScope( string $slug ): void
	{
		$state = $this->scopeState();

		unset( $state['visited'][ $slug ] );
		$state['depth'] = max( 0, $state['depth'] - 1 );

		if ( [] === $state['visited'] && 0 === $state['depth'] ) {
			app()->forgetInstance( self::SCOPE_KEY );

			return;
		}

		app()->instance( self::SCOPE_KEY, $state );
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
