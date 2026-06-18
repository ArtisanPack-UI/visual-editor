<?php

/**
 * Per-request accumulator for the box-shadow feature's CSS (#607).
 *
 * Block partials push their per-scope box-shadow rules into this
 * service while rendering. The `<x-ve-blocks>` / `<x-ve-template>`
 * components drain it once and emit a single
 * `<style data-ve-box-shadows>` block at the top of the render
 * output — same pattern as {@see GradientBorderCssAccumulator}.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

class BoxShadowCssAccumulator
{
	/**
	 * Map of scope class → CSS rule string. Keyed so duplicate pushes
	 * collapse to one entry; PHP arrays preserve insertion order so
	 * iteration produces deterministic output.
	 *
	 * @var array<string, string>
	 */
	protected array $rules = [];

	/**
	 * Adds a rule set for the given scope class. Subsequent calls with
	 * the same scope are ignored — block partials are expected to use
	 * content-derived scope classes so identical payloads land in the
	 * same bucket.
	 *
	 * @since 1.2.0
	 *
	 * @param  string  $scope  The scope class (e.g. `ve-bs-abc123`).
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
	 * Returns the consolidated `<style data-ve-box-shadows>` block
	 * and clears the accumulator. Called once per render at the top of
	 * the `<x-ve-blocks>` / `<x-ve-template>` output. Returns an empty
	 * string when no rules were pushed.
	 *
	 * @since 1.2.0
	 */
	public function flush(): string
	{
		if ( [] === $this->rules ) {
			return '';
		}

		$body        = implode( '', array_values( $this->rules ) );
		$this->rules = [];

		return '<style data-ve-box-shadows>' . $body . '</style>';
	}

	/**
	 * Resets the accumulator without emitting. Tests use this to clear
	 * state between assertions inside a single PHP process.
	 *
	 * @since 1.2.0
	 */
	public function reset(): void
	{
		$this->rules = [];
	}

	/**
	 * Inspect what's currently accumulated without draining. Test-only.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, string>
	 */
	public function all(): array
	{
		return $this->rules;
	}
}
