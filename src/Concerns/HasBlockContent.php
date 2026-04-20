<?php

/**
 * HasBlockContent trait.
 *
 * Opt-in trait that marks an Eloquent model as editable by the visual editor.
 * Declares the column that stores the block tree JSON and an optional scope
 * that the editor's REST controller applies when resolving models. Also
 * exposes a Scout helper — {@see self::toBlockContentSearchableArray()} — that
 * renders the block tree to plain text so a host model's `toSearchableArray()`
 * can index editor content alongside its own fields.
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

use ArtisanPackUI\VisualEditor\Search\BlockTreeSearchExtractor;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Traversable;

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
	 * Uses `isset()` rather than `property_exists()` so an uninitialized typed
	 * property falls back to the default instead of throwing.
	 *
	 * @since 1.0.0
	 */
	public function getBlockContentColumn(): string
	{
		return isset( $this->blockContentColumn ) && is_string( $this->blockContentColumn )
			? $this->blockContentColumn
			: 'content';
	}

	/**
	 * Returns the optional query scope applied when resolving models for the editor.
	 *
	 * Override by setting `protected $blockContentScope = 'published';` on the
	 * model. Returns null when no scope is configured. Uses `isset()` so
	 * uninitialized typed properties fall back to null rather than throwing.
	 *
	 * @since 1.0.0
	 */
	public function getBlockContentScope(): ?string
	{
		return isset( $this->blockContentScope ) && is_string( $this->blockContentScope )
			? $this->blockContentScope
			: null;
	}

	/**
	 * Returns the current block tree for the model.
	 *
	 * Accepts native arrays, `Arrayable` (e.g. Collection / `AsCollection`
	 * casts), and `Traversable` so a consumer's custom cast on the block
	 * content column is preserved on read instead of collapsing to `[]`.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getBlockContent(): array
	{
		$value = $this->getAttribute( $this->getBlockContentColumn() );

		if ( is_array( $value ) ) {
			return $value;
		}

		if ( $value instanceof Arrayable ) {
			return $value->toArray();
		}

		if ( $value instanceof Traversable ) {
			return iterator_to_array( $value );
		}

		return [];
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
	 * Walks the saved block tree, joins the extracted text from every static
	 * and dynamic block into a single string, and returns it keyed by
	 * `block_content`. Host models merge this into their own
	 * `toSearchableArray()` so editor-authored copy lands in the index
	 * alongside their native columns:
	 *
	 *     public function toSearchableArray(): array
	 *     {
	 *         return array_merge(
	 *             $this->only( ['title', 'excerpt'] ),
	 *             $this->toBlockContentSearchableArray()
	 *         );
	 *     }
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function toBlockContentSearchableArray(): array
	{
		return [
			'block_content' => $this->blockContentSearchableText(),
		];
	}

	/**
	 * Return the plain-text rendering of this model's block tree.
	 *
	 * Use when you need the raw string (e.g. to combine with a custom
	 * separator or to pipe into a separate search field) without the
	 * `block_content` array wrapper.
	 *
	 * @since 1.0.0
	 */
	public function blockContentSearchableText(): string
	{
		return app( BlockTreeSearchExtractor::class )->extract( $this->getBlockContent() );
	}
}
