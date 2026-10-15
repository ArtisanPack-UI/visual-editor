<?php

/**
 * Per-request accumulator for duotone filters (#873).
 *
 * The duotone filter support references an SVG `<filter>` by id
 * (`filter: url(#wp-duotone-…)`), so every filter a rendered block uses
 * has to exist exactly once somewhere in the document. Block partials
 * push the filter id, the scoped CSS rule that applies it, and the
 * `<filter>` element; `<x-ve-blocks>` / `<x-ve-template>` drain the
 * accumulator into one `<style data-ve-duotone>` block plus one hidden
 * `<svg data-ve-duotone>` holding every filter definition.
 *
 * Bound `scoped` in the service provider like its sibling accumulators,
 * so long-running workers (Octane, queues) start each request clean.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

class DuotoneCssAccumulator
{
	/**
	 * Scoped CSS rules keyed by scope class.
	 *
	 * @var array<string, string>
	 */
	protected array $rules = [];

	/**
	 * SVG `<filter>` elements keyed by filter id.
	 *
	 * @var array<string, string>
	 */
	protected array $filters = [];

	/**
	 * Register a scope's CSS rule and the filter it references. Both are
	 * deduped by key, so the same duotone on N blocks emits once.
	 *
	 * @since 1.15.0
	 */
	public function push( string $scope, string $css, string $filterId = '', string $filter = '' ): void
	{
		if ( '' !== $scope && '' !== $css && ! isset( $this->rules[ $scope ] ) ) {
			$this->rules[ $scope ] = $css;
		}

		if ( '' !== $filterId && '' !== $filter && ! isset( $this->filters[ $filterId ] ) ) {
			$this->filters[ $filterId ] = $filter;
		}
	}

	/**
	 * Drain the accumulator into its `<style>` + hidden `<svg>` markup.
	 *
	 * @since 1.15.0
	 */
	public function flush(): string
	{
		if ( [] === $this->rules && [] === $this->filters ) {
			return '';
		}

		$out = '';

		if ( [] !== $this->rules ) {
			$out .= '<style data-ve-duotone>' . implode( '', array_values( $this->rules ) ) . '</style>';
		}

		if ( [] !== $this->filters ) {
			$out .= '<svg data-ve-duotone xmlns="http://www.w3.org/2000/svg" viewBox="0 0 0 0" width="0" height="0" focusable="false" aria-hidden="true" style="visibility: hidden; position: absolute; left: -9999px; overflow: hidden;"><defs>'
				. implode( '', array_values( $this->filters ) )
				. '</defs></svg>';
		}

		$this->reset();

		return $out;
	}

	/**
	 * Drop everything accumulated so far.
	 *
	 * @since 1.15.0
	 */
	public function reset(): void
	{
		$this->rules   = [];
		$this->filters = [];
	}

	/**
	 * Inspect the accumulated rules (test seam).
	 *
	 * @since 1.15.0
	 *
	 * @return array<string, string>
	 */
	public function all(): array
	{
		return $this->rules;
	}
}
