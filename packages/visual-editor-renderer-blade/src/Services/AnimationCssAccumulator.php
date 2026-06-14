<?php

/**
 * Per-request accumulator for the block-animations CSS (#489).
 *
 * Block partials push their per-block animation CSS rule strings into
 * this service while rendering. The `<x-ve-blocks>` / `<x-ve-template>`
 * components then drain it once and emit a single
 * `<style data-ve-animations>` block at the top of the render output,
 * followed by the runtime-loading hint and the `<noscript>` fallback
 * payload.
 *
 * Mirrors {@see StateCssAccumulator} — same dedupe-by-scope semantics,
 * same scoped-per-request lifetime — so the three accumulators compose
 * naturally in the same render pass.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

use ArtisanPackUI\VisualEditor\Animations\KeyframeRegistry;

class AnimationCssAccumulator
{
	/**
	 * Map of scope class → CSS rule string.
	 *
	 * @var array<string, string>
	 */
	protected array $rules = [];

	/**
	 * Map of scope class → noscript CSS rule string.
	 *
	 * @var array<string, string>
	 */
	protected array $noscript = [];

	/**
	 * Tracks whether any pushed block declared an entrance animation —
	 * so the renderer knows whether to attach the runtime chunk and the
	 * `<noscript>` fallback.
	 */
	protected bool $hasEntrance = false;

	public function __construct( protected KeyframeRegistry $keyframes ) {}

	/**
	 * Records a block scope's animation CSS plus the noscript fallback
	 * CSS that reveals it when JS is unavailable.
	 *
	 * @since 1.1.0
	 *
	 * @param  string  $scope        Scope class (e.g. `.ap-block-abc123`).
	 * @param  string  $css          The animation CSS rule string.
	 * @param  string  $noscriptCss  The noscript fallback CSS rule string.
	 * @param  bool    $hasEntrance  Whether this scope uses an entrance
	 *                               animation. Drives runtime emission.
	 */
	public function push( string $scope, string $css, string $noscriptCss, bool $hasEntrance ): void
	{
		if ( '' === $scope ) {
			return;
		}

		if ( '' !== $css && ! isset( $this->rules[ $scope ] ) ) {
			$this->rules[ $scope ] = $css;
		}

		if ( $hasEntrance && '' !== $noscriptCss && ! isset( $this->noscript[ $scope ] ) ) {
			$this->noscript[ $scope ] = $noscriptCss;
		}

		if ( $hasEntrance ) {
			$this->hasEntrance = true;
		}
	}

	/**
	 * Returns the consolidated emission — `@keyframes` + scoped rules,
	 * the `<noscript>` reveal block, and a boolean indicating whether
	 * the renderer should attach the runtime chunk.
	 *
	 * @since 1.1.0
	 *
	 * @return array{styleTag: string, noscriptTag: string, runtimeNeeded: bool}
	 */
	public function flush(): array
	{
		if ( [] === $this->rules && ! $this->hasEntrance ) {
			$this->reset();

			return [ 'styleTag' => '', 'noscriptTag' => '', 'runtimeNeeded' => false ];
		}

		$keyframesCss = $this->keyframes->emitCss();
		$body         = $keyframesCss . ' ' . implode( ' ', array_values( $this->rules ) );
		$styleTag     = '<style data-ve-animations>' . trim( $body ) . '</style>';

		$noscriptTag = '';
		if ( $this->hasEntrance && [] !== $this->noscript ) {
			$noscriptTag = '<noscript><style>' . implode( ' ', array_values( $this->noscript ) ) . '</style></noscript>';
		}

		$runtimeNeeded = $this->hasEntrance;

		$this->reset();

		return [ 'styleTag' => $styleTag, 'noscriptTag' => $noscriptTag, 'runtimeNeeded' => $runtimeNeeded ];
	}

	/**
	 * @since 1.1.0
	 */
	public function reset(): void
	{
		$this->rules       = [];
		$this->noscript    = [];
		$this->hasEntrance = false;
	}

	/**
	 * Inspect what's currently accumulated without draining. Test-only.
	 *
	 * @since 1.1.0
	 *
	 * @return array{rules: array<string, string>, noscript: array<string, string>, hasEntrance: bool}
	 */
	public function all(): array
	{
		return [
			'rules'       => $this->rules,
			'noscript'    => $this->noscript,
			'hasEntrance' => $this->hasEntrance,
		];
	}
}
