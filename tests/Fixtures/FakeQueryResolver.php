<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;

/**
 * Test fake for {@see QueryResolverContract} so the visual-editor test
 * suite can exercise the controller + inliner without depending on
 * cms-framework's `QueryRuntime` at the autoload level.
 *
 * Configure with `setItems()` (the rows the next `resolve()` returns)
 * and inspect `$lastAttributes` to assert on what the consumer passed.
 */
class FakeQueryResolver implements QueryResolverContract
{
	/** @var array<int, mixed> */
	public array $items = [];

	public int $perPage = 10;

	public int $currentPage = 1;

	public ?int $totalOverride = null;

	/** @var array<string, mixed>|null */
	public ?array $lastAttributes = null;

	/** @param array<int, mixed> $items */
	public function setItems( array $items ): self
	{
		$this->items = $items;

		return $this;
	}

	public function resolve( array $attributes ): LengthAwarePaginator
	{
		$this->lastAttributes = $attributes;

		$total = $this->totalOverride ?? count( $this->items );

		return new ConcretePaginator(
			$this->items,
			$total,
			$this->perPage,
			$this->currentPage
		);
	}
}
