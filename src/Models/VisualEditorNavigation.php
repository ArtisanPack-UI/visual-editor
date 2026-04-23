<?php

/**
 * VisualEditorNavigation model.
 *
 * Eloquent backing for the `wp_navigation` entity exposed at
 * `/visual-editor/api/navigation`. Stores the Gutenberg block tree inside
 * the `{ raw, blocks }` envelope the B1 core-data shim expects. The tree
 * carries `core/navigation-link` and `core/navigation-submenu` blocks
 * (including nested submenus per B2's `nested.json` fixture); the model
 * persists them verbatim so round-tripping through the REST surface
 * preserves the exact shape the editor reads back.
 *
 * Records also carry a `status` + `menu_order` pair so the
 * {@see \ArtisanPackUI\VisualEditor\Services\MenuLocationResolver} can
 * fall back to the first published nav when a configured menu location
 * has no assignment or points at a missing record.
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

class VisualEditorNavigation extends Model
{
	public const STATUS_PUBLISH = 'publish';
	public const STATUS_DRAFT   = 'draft';
	public const STATUS_PRIVATE = 'private';

	protected $table = 'visual_editor_navigations';

	/**
	 * @var array<int, string>
	 */
	protected $fillable = [
		'slug',
		'title',
		'content',
		'status',
		'menu_order',
	];

	/**
	 * @var array<string, string>
	 */
	protected $casts = [
		'content'    => 'array',
		'menu_order' => 'integer',
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
	 * Whether the nav has any blocks in its content envelope.
	 *
	 * The fallback resolver treats an empty block tree the same as a
	 * missing record, because a nav with nothing to render is no use to
	 * the front end.
	 *
	 * @since 1.0.0
	 */
	public function isEmpty(): bool
	{
		return [] === $this->getBlocks();
	}

	/**
	 * Scopes the query to published navs, ordered by `menu_order`.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder<VisualEditorNavigation>  $query
	 *
	 * @return Builder<VisualEditorNavigation>
	 */
	public function scopePublished( Builder $query ): Builder
	{
		return $query->where( 'status', self::STATUS_PUBLISH )
			->orderBy( 'menu_order' )
			->orderBy( 'id' );
	}
}
