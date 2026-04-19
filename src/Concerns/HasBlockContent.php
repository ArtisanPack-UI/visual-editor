<?php

/**
 * HasBlockContent trait.
 *
 * Opt-in trait that marks an Eloquent model as editable by the visual editor.
 * Declares the column that stores the block tree JSON and an optional scope
 * that the editor's REST controller applies when resolving models. Includes a
 * Scout hook stub (real implementation in M12) so consuming apps can start
 * using the trait today without needing the full search pipeline.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Concerns;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasBlockContent
{
	/**
	 * Initializes the trait on each model instance by registering an `array`
	 * cast for the block content column if the model hasn't declared one.
	 *
	 * @since 1.0.0
	 */
	public function initializeHasBlockContent(): void
	{
		$column = $this->getBlockContentColumn();

		if ( ! array_key_exists( $column, $this->getCasts() ) ) {
			$this->mergeCasts( [ $column => 'array' ] );
		}
	}

	/**
	 * Returns the column that stores the block tree JSON.
	 *
	 * Override by setting `protected $blockContentColumn = 'body';` on the model.
	 *
	 * @since 1.0.0
	 */
	public function getBlockContentColumn(): string
	{
		return property_exists( $this, 'blockContentColumn' ) && is_string( $this->blockContentColumn )
			? $this->blockContentColumn
			: 'content';
	}

	/**
	 * Returns the optional query scope applied when resolving models for the editor.
	 *
	 * Override by setting `protected $blockContentScope = 'published';` on the
	 * model. Returns null when no scope is configured.
	 *
	 * @since 1.0.0
	 */
	public function getBlockContentScope(): ?string
	{
		return property_exists( $this, 'blockContentScope' ) && is_string( $this->blockContentScope )
			? $this->blockContentScope
			: null;
	}

	/**
	 * Returns the current block tree for the model.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getBlockContent(): array
	{
		$value = $this->getAttribute( $this->getBlockContentColumn() );

		return is_array( $value ) ? $value : [];
	}

	/**
	 * Persists a new block tree for the model.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<string, mixed>>  $blocks  The block tree to store.
	 */
	public function setBlockContent( array $blocks ): void
	{
		$this->setAttribute( $this->getBlockContentColumn(), $blocks );
	}

	/**
	 * Scopes the query to models the editor can resolve.
	 *
	 * Applies the optional `$blockContentScope` if it's configured. Missing
	 * scope methods throw loudly — silently ignoring a typo'd scope would
	 * leak otherwise-hidden content (e.g. drafts) through the editor API.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
	 *
	 * @return Builder<\Illuminate\Database\Eloquent\Model>
	 *
	 * @throws InvalidArgumentException When the configured scope method doesn't exist on the model.
	 */
	public function scopeForVisualEditor( Builder $query ): Builder
	{
		$scope = $this->getBlockContentScope();

		if ( null === $scope ) {
			return $query;
		}

		$method = 'scope' . ucfirst( $scope );

		if ( ! method_exists( $this, $method ) ) {
			throw new InvalidArgumentException( sprintf(
				'HasBlockContent: scope "%s" (expected method %s::%s) is not defined on the model.',
				$scope,
				static::class,
				$method
			) );
		}

		$query->{$scope}();

		return $query;
	}

	/**
	 * Returns the Scout-indexable payload for block content.
	 *
	 * Stub for M12. Consuming apps that use Laravel Scout can override
	 * `toSearchableArray()` on their model and call this helper to include a
	 * plain-text rendering of the block tree once the Scout indexer lands.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function toBlockContentSearchableArray(): array
	{
		return [
			'block_content' => $this->getBlockContent(),
		];
	}
}
