<?php

/**
 * Per-request accumulator for the Elements API CSS (#852).
 *
 * {@see \ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports::compile()}
 * pushes each block's `style.elements.*` rules (link / heading / button
 * colors, scoped under its `wp-elements-{hash}` class) into this
 * service while rendering. The `<x-ve-blocks>` / `<x-ve-template>`
 * components then drain it once and emit a single
 * `<style data-ve-elements>` block at the top of the render output.
 *
 * Mirrors {@see StateCssAccumulator} — same dedupe-by-scope semantics,
 * same scoped-per-request lifetime.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.13.1
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

class ElementsCssAccumulator
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
	 * @since 1.13.1
	 *
	 * @param  string  $scope  The scope class (e.g. `wp-elements-1a2b3c4d`).
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
	 * Returns the consolidated `<style data-ve-elements>` block and clears
	 * the accumulator. Called once per render at the top of the
	 * `<x-ve-blocks>` / `<x-ve-template>` output. Returns an empty
	 * string when no rules were pushed.
	 *
	 * @since 1.13.1
	 */
	public function flush(): string
	{
		if ( [] === $this->rules ) {
			return '';
		}

		$body        = implode( '', array_values( $this->rules ) );
		$this->rules = [];

		return '<style data-ve-elements>' . $body . '</style>';
	}

	/**
	 * Resets the accumulator without emitting. Tests use this to clear
	 * state between assertions inside a single PHP process.
	 *
	 * @since 1.13.1
	 */
	public function reset(): void
	{
		$this->rules = [];
	}

	/**
	 * Inspect what's currently accumulated without draining. Test-only.
	 *
	 * @since 1.13.1
	 *
	 * @return array<string, string>
	 */
	public function all(): array
	{
		return $this->rules;
	}
}
