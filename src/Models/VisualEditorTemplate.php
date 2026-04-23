<?php

/**
 * VisualEditorTemplate model.
 *
 * Eloquent backing for the `wp_template` entity exposed at
 * `/visual-editor/api/templates`. Stores the Gutenberg block tree inside
 * the `{ raw, blocks }` envelope the B1 core-data shim expects, together
 * with the template-hierarchy metadata (`slug`, `theme`, `source`,
 * `origin`) the fallback resolver cascades through.
 *
 * The model intentionally does not use the `HasBlockContent` trait
 * because templates hold the raw serialized payload alongside the parsed
 * block tree; the trait assumes the block column is the block array
 * itself. The per-block shape stored under `content.blocks` still
 * matches every other block-tree surface in the package (`name`,
 * `attributes`, `innerBlocks`), so block renderers can consume it
 * without a translation step.
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

class VisualEditorTemplate extends Model
{
	public const SOURCE_THEME  = 'theme';
	public const SOURCE_CUSTOM = 'custom';

	public const ORIGIN_THEME  = 'theme';
	public const ORIGIN_PLUGIN = 'plugin';
	public const ORIGIN_CUSTOM = 'custom';

	public const STATUS_PUBLISH = 'publish';
	public const STATUS_DRAFT   = 'draft';
	public const STATUS_PRIVATE = 'private';

	protected $table = 'visual_editor_templates';

	/**
	 * @var array<int, string>
	 */
	protected $fillable = [
		'slug',
		'title',
		'description',
		'content',
		'status',
		'theme',
		'source',
		'origin',
	];

	/**
	 * @var array<string, string>
	 */
	protected $casts = [
		'content' => 'array',
	];

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
	 * Scopes the query to templates published for a theme.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder<VisualEditorTemplate>  $query
	 *
	 * @return Builder<VisualEditorTemplate>
	 */
	public function scopeForTheme( Builder $query, string $theme ): Builder
	{
		return $query->where( 'theme', $theme );
	}

	/**
	 * Scopes the query to templates matching a slug (optionally constrained
	 * to a theme).
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder<VisualEditorTemplate>  $query
	 *
	 * @return Builder<VisualEditorTemplate>
	 */
	public function scopeForSlug( Builder $query, string $slug, ?string $theme = null ): Builder
	{
		$query->where( 'slug', $slug );

		if ( null !== $theme && '' !== $theme ) {
			$query->where( 'theme', $theme );
		}

		return $query;
	}
}
