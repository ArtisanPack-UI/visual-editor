<?php

/**
 * Per-request accumulator for the CSS positioning feature (#640).
 *
 * Block partials push their per-scope position rules into this service
 * while rendering. The `<x-ve-blocks>` / `<x-ve-template>` components
 * drain it once and emit a single `<style data-ve-position>` block at
 * the top of the render output — same pattern as
 * {@see BoxShadowCssAccumulator}.
 *
 * Upgrade note: hosts that ran `php artisan vendor:publish
 * --tag=visual-editor-blade-views` on a pre-1.4 version must republish
 * with `--force` after upgrading — the published blade files shadow
 * the package source and pre-1.4 copies don't include the
 * `<style data-ve-position>` output block, so this accumulator
 * flushes into the void on the frontend.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

class PositionCssAccumulator
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
	 * Adds a rule set for the given scope class. Identical pushes
	 * (same scope + same rules) dedup — 50 blocks with the same
	 * position payload emit one rule. When two blocks share a scope
	 * class but ship DIFFERENT rule bodies (a `_positionScopeId`
	 * collision from block duplication before the editor's collision
	 * reclaim runs, or legacy content), keep BOTH entries so neither
	 * block silently inherits the other's position — CSS cascade
	 * (last-declaration-wins at equal specificity) picks the winner,
	 * which beats the previous scope-only dedup that dropped one
	 * block's rules entirely with no signal.
	 *
	 * @since 1.4.0
	 */
	public function push( string $scope, string $css ): void
	{
		if ( '' === $scope || '' === $css ) {
			return;
		}

		$key = $scope . '#' . hash( 'xxh3', $css );

		if ( isset( $this->rules[ $key ] ) ) {
			return;
		}

		$this->rules[ $key ] = $css;
	}

	/**
	 * Returns the consolidated `<style data-ve-position>` block and
	 * clears the accumulator.
	 *
	 * @since 1.4.0
	 */
	public function flush(): string
	{
		if ( [] === $this->rules ) {
			return '';
		}

		$body        = implode( '', array_values( $this->rules ) );
		$this->rules = [];

		return '<style data-ve-position>' . $body . '</style>';
	}

	/**
	 * Resets the accumulator without emitting. Tests use this to clear
	 * state between assertions inside a single PHP process.
	 *
	 * @since 1.4.0
	 */
	public function reset(): void
	{
		$this->rules = [];
	}

	/**
	 * Inspect what's currently accumulated without draining. Test-only.
	 *
	 * @since 1.4.0
	 *
	 * @return array<string, string>
	 */
	public function all(): array
	{
		return $this->rules;
	}
}
