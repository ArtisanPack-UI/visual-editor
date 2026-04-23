<?php

/**
 * VisualEditorPattern model.
 *
 * Eloquent backing for the `wp_block` entity exposed at
 * `/visual-editor/api/patterns`. Stores the Gutenberg block tree inside
 * the `{ raw, blocks }` envelope the B1 core-data shim expects, along
 * with the `synced` flag and category relationships documented in
 * `docs/core-data-shim.md` §Patterns and `docs/plans/11-v1-expansion.md`
 * §2.2.
 *
 * A synced pattern (`synced: true`) is stored once and referenced by id
 * from every instance; editing the pattern propagates everywhere. An
 * unsynced pattern (`synced: false`) is a one-shot insert — the editor
 * copies its block tree into the target on insert, and the two diverge
 * from there. The backend stores the flag faithfully — the editor
 * decides reference-vs-copy on insert.
 *
 * Categories are a many-to-many relationship used by both the
 * post-editor inserter's category list and the site-editor's pattern
 * library. Authoring with `categories: ["featured"]` auto-creates the
 * category on the first use, matching the B2 fixture shape.
 *
 * The model intentionally does not use the `HasBlockContent` trait —
 * patterns hold the raw serialized payload alongside the parsed block
 * tree (same rationale as {@see VisualEditorTemplate}).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VisualEditorPattern extends Model
{
	public const STATUS_PUBLISH = 'publish';
	public const STATUS_DRAFT   = 'draft';
	public const STATUS_PRIVATE = 'private';

	protected $table = 'visual_editor_patterns';

	/**
	 * @var array<int, string>
	 */
	protected $fillable = [
		'slug',
		'title',
		'content',
		'synced',
		'status',
	];

	/**
	 * @var array<string, string>
	 */
	protected $casts = [
		'content' => 'array',
		'synced'  => 'boolean',
	];

	/**
	 * The categories this pattern belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsToMany<VisualEditorPatternCategory>
	 */
	public function categories(): BelongsToMany
	{
		return $this->belongsToMany(
			VisualEditorPatternCategory::class,
			'visual_editor_pattern_category',
			'pattern_id',
			'pattern_category_id'
		);
	}

	/**
	 * Returns the canonical content envelope stored on the record.
	 *
	 * Always returns the `{ raw, blocks }` shape — callers never have to
	 * null-check the raw column or the blocks key separately.
	 *
	 * @since 1.0.0
	 *
	 * @return array{raw: string, blocks: array<int, array<string, mixed>>}
	 */
	public function getContentEnvelope(): array
	{
		$value = $this->getAttribute( 'content' );

		$raw    = '';
		$blocks = [];

		if ( is_array( $value ) ) {
			$raw    = isset( $value['raw'] ) && is_string( $value['raw'] ) ? $value['raw'] : '';
			$blocks = isset( $value['blocks'] ) && is_array( $value['blocks'] ) ? array_values( $value['blocks'] ) : [];
		}

		return [
			'raw'    => $raw,
			'blocks' => $blocks,
		];
	}

	/**
	 * Returns just the parsed block tree.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getBlocks(): array
	{
		return $this->getContentEnvelope()['blocks'];
	}

	/**
	 * Returns just the raw Gutenberg serialization.
	 *
	 * @since 1.0.0
	 */
	public function getRawContent(): string
	{
		return $this->getContentEnvelope()['raw'];
	}

	/**
	 * Persists an updated content envelope on the record.
	 *
	 * @since 1.0.0
	 *
	 * @param  array{raw?: string, blocks?: array<int, array<string, mixed>>}  $envelope
	 */
	public function setContentEnvelope( array $envelope ): void
	{
		$raw    = isset( $envelope['raw'] ) && is_string( $envelope['raw'] ) ? $envelope['raw'] : '';
		$blocks = isset( $envelope['blocks'] ) && is_array( $envelope['blocks'] ) ? array_values( $envelope['blocks'] ) : [];

		$this->setAttribute( 'content', [
			'raw'    => $raw,
			'blocks' => $blocks,
		] );
	}

	/**
	 * Scopes the query to patterns matching a slug.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder<VisualEditorPattern>  $query
	 *
	 * @return Builder<VisualEditorPattern>
	 */
	public function scopeForSlug( Builder $query, string $slug ): Builder
	{
		return $query->where( 'slug', $slug );
	}

	/**
	 * Scopes the query to patterns that have at least one of the given
	 * category slugs (OR semantics).
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder<VisualEditorPattern>  $query
	 * @param  array<int, string>            $slugs
	 *
	 * @return Builder<VisualEditorPattern>
	 */
	public function scopeWithAnyCategory( Builder $query, array $slugs ): Builder
	{
		$normalized = array_values( array_filter(
			$slugs,
			fn ( $slug ) => is_string( $slug ) && '' !== $slug
		) );

		if ( [] === $normalized ) {
			return $query;
		}

		return $query->whereHas(
			'categories',
			fn ( Builder $subQuery ) => $subQuery->whereIn( 'slug', $normalized )
		);
	}
}
