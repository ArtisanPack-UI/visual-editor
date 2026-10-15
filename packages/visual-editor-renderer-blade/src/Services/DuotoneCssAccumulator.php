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
 * A filter id is emitted once per request, not once per flush: a page
 * that renders `<x-ve-template name="header">` and `<x-ve-blocks>` flushes
 * twice, and a second `<filter id>` with the same id would be a duplicate
 * id in the document. The rules are re-emitted with each flush (repeating
 * a rule is harmless). The accumulator also memoizes the active theme's
 * duotone presets for the request, so N preset-duotone blocks resolve the
 * global styles once.
 *
 * Bound `scoped` in the service provider like its sibling accumulators,
 * so long-running workers (Octane, queues) start each request clean. A
 * caller that renders several separate documents in one request (pattern
 * previews) calls {@see reset()} between them.
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
	 * @since 1.15.0
	 *
	 * @var array<string, string>
	 */
	protected array $rules = [];

	/**
	 * SVG `<filter>` elements keyed by filter id.
	 *
	 * @since 1.15.0
	 *
	 * @var array<string, string>
	 */
	protected array $filters = [];

	/**
	 * Filter ids already flushed this request, kept across flushes so no
	 * id is emitted twice in one document (R-10).
	 *
	 * @since 1.15.0
	 *
	 * @var array<string, true>
	 */
	protected array $emittedFilterIds = [];

	/**
	 * The active theme's `settings.color.duotone` node, memoized for the
	 * request (M-16). Only meaningful once {@see $presetsLoaded} is true.
	 *
	 * @since 1.15.0
	 *
	 * @var array<mixed>|null
	 */
	protected ?array $presets = null;

	/**
	 * Whether {@see $presets} has been resolved this request.
	 *
	 * @since 1.15.0
	 */
	protected bool $presetsLoaded = false;

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

		if ( '' !== $filterId && '' !== $filter && ! isset( $this->filters[ $filterId ] ) && ! isset( $this->emittedFilterIds[ $filterId ] ) ) {
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
			$this->emittedFilterIds += array_fill_keys( array_keys( $this->filters ), true );

			$out .= '<svg data-ve-duotone xmlns="http://www.w3.org/2000/svg" viewBox="0 0 0 0" width="0" height="0" focusable="false" aria-hidden="true" style="visibility: hidden; position: absolute; left: -9999px; overflow: hidden;"><defs>'
				. implode( '', array_values( $this->filters ) )
				. '</defs></svg>';
		}

		$this->rules   = [];
		$this->filters = [];

		return $out;
	}

	/**
	 * Drop everything accumulated so far, including the request's record
	 * of emitted filter ids and the memoized presets. Call it between
	 * renders that end up in separate documents.
	 *
	 * @since 1.15.0
	 */
	public function reset(): void
	{
		$this->rules            = [];
		$this->filters          = [];
		$this->emittedFilterIds = [];
		$this->presets          = null;
		$this->presetsLoaded    = false;
	}

	/**
	 * The active theme's resolved `settings.color.duotone` node, or null.
	 * Resolved once per request: the lookup runs the global-styles
	 * resolver (a database query and a theme load), and the presets can't
	 * change mid-request. {@see flush()} keeps the cache; {@see reset()}
	 * clears it.
	 *
	 * @since 1.15.0
	 *
	 * @param  callable(): (array<mixed>|null)  $resolve  Looks the presets up on a cache miss.
	 *
	 * @return array<mixed>|null
	 */
	public function presets( callable $resolve ): ?array
	{
		if ( ! $this->presetsLoaded ) {
			$this->presets       = $resolve();
			$this->presetsLoaded = true;
		}

		return $this->presets;
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
