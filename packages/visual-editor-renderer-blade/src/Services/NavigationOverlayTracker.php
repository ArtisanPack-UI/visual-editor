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
	protected int $counter = 0;

	protected bool $scriptEmitted = false;

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
	 * Resets both the counter and the script-emitted flag. Used by
	 * tests that exercise multiple renders inside a single process.
	 *
	 * @since 1.0.0
	 */
	public function reset(): void
	{
		$this->counter       = 0;
		$this->scriptEmitted = false;
	}
}
