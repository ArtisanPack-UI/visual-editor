<?php

/**
 * Per-request accumulator for the responsive design tools' CSS (#487, #509).
 *
 * Block partials push their per-breakpoint CSS strings into this
 * service while rendering. The `<x-ve-blocks>` / `<x-ve-template>`
 * components then drain it once and emit a single `<style data-ve-responsive>`
 * block at the top of the render output.
 *
 * This replaces the previous v1.0 approach of inlining a `<style>`
 * block immediately before each block's wrapper, which had two
 * problems:
 *
 *  - The `<style>` tags ended up interleaved with sibling block
 *    children inside flex / grid containers. Per spec `<style>` is
 *    `display: none`, but the placement was fragile and made the
 *    cascade hard to reason about.
 *  - Duplicate scope classes (the same payload on N columns) emitted
 *    N identical `<style>` blocks. Now they collapse to one.
 *
 * Storage is keyed by scope class so the same payload pushed by
 * multiple block instances only contributes one rule set to the
 * consolidated output. Insertion order is preserved for stable diffs.
 *
 * Bound via `$this->app->scoped()` so a long-lived worker (Octane,
 * RoadRunner, queued job rendering many blocks per run) gets a fresh
 * accumulator per request scope. A singleton would leak rules from
 * one request into the next.
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

class ResponsiveCssAccumulator
{
	/**
	 * Map of scope class → CSS rule string. Keyed so duplicate pushes
	 * collapse to one entry; iteration order preserves first-push order
	 * since PHP arrays remember insertion order.
	 *
	 * @var array<string, string>
	 */
	protected array $rules = [];

	/**
	 * Adds a rule set for the given scope class. Subsequent calls with
	 * the same scope are ignored — block partials are expected to use
	 * content-derived scope classes so an identical payload always
	 * hashes to the same key.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $scope  The scope class (e.g. `ve-r-abc123`).
	 * @param  string  $css    The CSS rule string (no surrounding
	 *                         `<style>` tag).
	 */
	public function push( string $scope, string $css ): void
	{
		if ( '' === $scope || '' === $css ) {
			return;
		}

		if ( isset( $this->rules[ $scope ] ) ) {
			return;
		}

		$this->rules[ $scope ] = $css;
	}

	/**
	 * Returns the consolidated `<style data-ve-responsive>` block and
	 * clears the accumulator. Called once per render at the top of the
	 * `<x-ve-blocks>` / `<x-ve-template>` output. Returns an empty
	 * string when no rules were pushed.
	 *
	 * @since 1.0.0
	 */
	public function flush(): string
	{
		if ( [] === $this->rules ) {
			return '';
		}

		$body = implode( '', array_values( $this->rules ) );
		$this->rules = [];

		return '<style data-ve-responsive>' . $body . '</style>';
	}

	/**
	 * Resets the accumulator without emitting. Tests use this to clear
	 * state between assertions inside a single PHP process.
	 *
	 * @since 1.0.0
	 */
	public function reset(): void
	{
		$this->rules = [];
	}

	/**
	 * Inspect what's currently accumulated without draining. Test-only.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function all(): array
	{
		return $this->rules;
	}
}
