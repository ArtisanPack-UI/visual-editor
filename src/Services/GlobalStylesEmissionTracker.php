<?php

/**
 * Per-request flag that records whether the global-styles `<style>`
 * block has already been emitted in the current response.
 *
 * `<x-ve-blocks>` and `<x-ve-template>` both auto-prepend the compiled
 * CSS to their first render; subsequent renders on the same page check
 * this tracker and skip emission so the `<style>` block appears at
 * most once per page even when the same renderer fires multiple times
 * (e.g. a layout that nests a `<x-ve-template name="header">` above a
 * `<x-ve-blocks :tree="...">` post body).
 *
 * Bound via `$this->app->scoped()` in the package container so the
 * tracker is rebuilt at the start of every request scope. That keeps
 * a long-lived worker (Octane, RoadRunner, queue worker rendering
 * blocks per job) from leaking the "already emitted" flag from one
 * request into the next. Tests can also explicitly call `reset()` on
 * the resolved instance to clear state between assertions inside a
 * single PHP process.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services;

class GlobalStylesEmissionTracker
{
	protected bool $emitted = false;

	public function hasEmitted(): bool
	{
		return $this->emitted;
	}

	public function markEmitted(): void
	{
		$this->emitted = true;
	}

	/**
	 * Resets the tracker — used by tests that exercise multiple renders
	 * inside a single process.
	 *
	 * @since 1.0.0
	 */
	public function reset(): void
	{
		$this->emitted = false;
	}
}
