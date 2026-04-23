<?php

/**
 * VisualEditorTemplatePart model.
 *
 * Eloquent backing for the `wp_template_part` entity exposed at
 * `/visual-editor/api/template-parts`. Stores the Gutenberg block tree
 * inside the `{ raw, blocks }` envelope the B1 core-data shim expects,
 * together with the `area` enum (`header`, `footer`, `sidebar`,
 * `uncategorized`) and the `theme` scoping that pairs with `slug` as the
 * composite natural key.
 *
 * Shares the `{ raw, blocks }` storage shape with {@see VisualEditorTemplate}
 * so block renderers can consume either surface without a translation
 * step. `referenced_by` is derived on the REST layer (see
 * {@see \ArtisanPackUI\VisualEditor\Http\Controllers\TemplatePartController::resolveReferencedBy()})
 * — the model does not persist or maintain the relationship directly.
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

class VisualEditorTemplatePart extends Model
{
	public const AREA_HEADER        = 'header';
	public const AREA_FOOTER        = 'footer';
	public const AREA_SIDEBAR       = 'sidebar';
	public const AREA_UNCATEGORIZED = 'uncategorized';

	/**
	 * @var array<int, string>
	 */
	public const AREAS = [
		self::AREA_HEADER,
		self::AREA_FOOTER,
		self::AREA_SIDEBAR,
		self::AREA_UNCATEGORIZED,
	];

	protected $table = 'visual_editor_template_parts';

	/**
	 * @var array<int, string>
	 */
	protected $fillable = [
		'slug',
		'title',
		'content',
		'area',
		'theme',
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
	 * Scopes the query to template parts published for a theme.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder<VisualEditorTemplatePart>  $query
	 *
	 * @return Builder<VisualEditorTemplatePart>
	 */
	public function scopeForTheme( Builder $query, string $theme ): Builder
	{
		return $query->where( 'theme', $theme );
	}

	/**
	 * Scopes the query to a specific area enum value.
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder<VisualEditorTemplatePart>  $query
	 *
	 * @return Builder<VisualEditorTemplatePart>
	 */
	public function scopeForArea( Builder $query, string $area ): Builder
	{
		return $query->where( 'area', $area );
	}

	/**
	 * Scopes the query to parts matching a slug (optionally constrained
	 * to a theme).
	 *
	 * @since 1.0.0
	 *
	 * @param  Builder<VisualEditorTemplatePart>  $query
	 *
	 * @return Builder<VisualEditorTemplatePart>
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
