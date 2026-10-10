<?php

/**
 * Per-request bookkeeping for nav-block overlay rendering.
 *
 * `core/navigation` blocks with `overlayMenu` set to `mobile` or `always`
 * render a hamburger-toggle wrapper + a responsive-container `<div>`
 * that the editor canvas already exposes (Keystone #54). The toggle
 * needs:
 *
 *   - A unique DOM id per overlay so multiple nav blocks on the same
 *     page don't collide. {@see nextOverlayId()} hands out monotonically
 *     increasing ids, scoped to the request.
 *   - A small JS controller that runs once per page regardless of how
 *     many nav blocks the page has. {@see hasEmittedScript()} /
 *     {@see markScriptEmitted()} gate the inline `<script>` block so
 *     it appears at most once per response.
 *   - A guard against overlay recursion. An overlay template part can
 *     itself contain a `core/navigation` whose `overlay` points back at
 *     the same part (or an A → B → A cycle). {@see enterOverlay()} /
 *     {@see leaveOverlay()} keep a stack of the overlay slugs currently
 *     being rendered so a cycle (or nesting deeper than
 *     {@see MAX_OVERLAY_DEPTH}) falls back to the inline menu instead
 *     of recursing until PHP dies.
 *
 * Bound via `$this->app->scoped()` in the renderer-blade service
 * provider so the tracker is rebuilt at the start of every request
 * scope. That keeps a long-lived worker (Octane, RoadRunner, queue
 * worker rendering blocks per job) from carrying overlay state across
 * requests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

class NavigationOverlayTracker
{
	/**
	 * Maximum number of overlay template parts that may be nested
	 * inside one another before further overlays are skipped.
	 *
	 * @since 1.13.0
	 */
	public const MAX_OVERLAY_DEPTH = 8;

	protected int $counter = 0;

	protected bool $scriptEmitted = false;

	/**
	 * Slugs of the overlay template parts currently being rendered,
	 * outermost first.
	 *
	 * @since 1.13.0
	 *
	 * @var array<int, string>
	 */
	protected array $overlayStack = [];

	/**
	 * Mint a new overlay id. Format mirrors WordPress core's
	 * `modal-nav-{n}` convention so the editor canvas and front-end
	 * resolve identical selectors.
	 *
	 * @since 1.0.0
	 */
	public function nextOverlayId(): string
	{
		$this->counter++;

		return 'ap-modal-nav-' . $this->counter;
	}

	public function hasEmittedScript(): bool
	{
		return $this->scriptEmitted;
	}

	public function markScriptEmitted(): void
	{
		$this->scriptEmitted = true;
	}

	/**
	 * Push an overlay slug onto the render stack.
	 *
	 * Returns `false` (and pushes nothing) when the slug is already being
	 * rendered further up the stack — a self-referencing overlay or an
	 * A → B → A cycle — or when the stack is already
	 * {@see MAX_OVERLAY_DEPTH} deep. Callers must only render the overlay
	 * when this returns `true`, and must pair that with
	 * {@see leaveOverlay()} in a `finally` block.
	 *
	 * @since 1.13.0
	 *
	 * @param  string  $slug  The overlay template-part slug about to be rendered.
	 *
	 * @return bool Whether the overlay may be rendered.
	 */
	public function enterOverlay( string $slug ): bool
	{
		if ( in_array( $slug, $this->overlayStack, true ) || count( $this->overlayStack ) >= self::MAX_OVERLAY_DEPTH ) {
			return false;
		}

		$this->overlayStack[] = $slug;

		return true;
	}

	/**
	 * Pop the most recently entered overlay slug off the render stack.
	 *
	 * @since 1.13.0
	 */
	public function leaveOverlay(): void
	{
		array_pop( $this->overlayStack );
	}

	/**
	 * Resets all per-response state: the id counter, the script-emitted
	 * flag, and the overlay render stack. Used by tests that exercise
	 * multiple renders inside a single process, and by callers that
	 * render several independent documents in one request (e.g. pattern
	 * previews, each shown in its own iframe).
	 *
	 * @since 1.0.0
	 * @since 1.13.0 Also clears the overlay render stack.
	 */
	public function reset(): void
	{
		$this->counter       = 0;
		$this->scriptEmitted = false;
		$this->overlayStack  = [];
	}
}
