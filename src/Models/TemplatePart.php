<?php

/**
 * TemplatePart Model.
 *
 * Represents a reusable template part (header, footer, sidebar, or custom)
 * that can be shared across multiple templates in the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Models
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Template part model for storing reusable layout sections.
 *
 * Template parts represent reusable sections like headers, footers,
 * and sidebars that can be embedded in any template. Editing a part
 * updates all templates that reference it.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Models
 *
 * @property int            $id
 * @property string         $name
 * @property string         $slug
 * @property string|null    $description
 * @property string         $area
 * @property array          $content
 * @property string         $status
 * @property bool           $is_custom
 * @property bool           $is_locked
 * @property int|null       $user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @since      1.0.0
 */
class TemplatePart extends Model
{
	/**
	 * Valid area types for template parts.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const AREAS = [ 'header', 'footer', 'sidebar', 'custom' ];

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table = 'visual_editor_template_parts';

	/**
	 * The model's default values for attributes.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>
	 */
	protected $attributes = [
		'area'      => 'custom',
		'status'    => 'active',
		'is_custom' => false,
		'is_locked' => false,
	];

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'name',
		'slug',
		'description',
		'area',
		'content',
		'status',
		'is_custom',
		'is_locked',
		'user_id',
	];

	/**
	 * Scope a query to template parts for a specific area.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query The query builder instance.
	 * @param string  $area  The area to filter by (header, footer, sidebar, custom).
	 *
	 * @return Builder
	 */
	public function scopeForArea( Builder $query, string $area ): Builder
	{
		return $query->where( 'area', $area );
	}

	/**
	 * Scope a query to active template parts.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query The query builder instance.
	 *
	 * @return Builder
	 */
	public function scopeActive( Builder $query ): Builder
	{
		return $query->where( 'status', 'active' );
	}

	/**
	 * Scope a query to draft template parts.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query The query builder instance.
	 *
	 * @return Builder
	 */
	public function scopeDraft( Builder $query ): Builder
	{
		return $query->where( 'status', 'draft' );
	}

	/**
	 * Scope a query to custom (user-created) template parts.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query The query builder instance.
	 *
	 * @return Builder
	 */
	public function scopeCustom( Builder $query ): Builder
	{
		return $query->where( 'is_custom', true );
	}

	/**
	 * Scope a query to built-in (non-custom) template parts.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query The query builder instance.
	 *
	 * @return Builder
	 */
	public function scopeBuiltIn( Builder $query ): Builder
	{
		return $query->where( 'is_custom', false );
	}

	/**
	 * Scope a query to template parts created by a specific user.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder  $query  The query builder instance.
	 * @param int|null $userId The user ID to filter by.
	 *
	 * @return Builder
	 */
	public function scopeByUser( Builder $query, ?int $userId ): Builder
	{
		return $query->where( 'user_id', $userId );
	}

	/**
	 * Get the user that created the template part.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsTo
	 */
	public function user(): BelongsTo
	{
		$userModel = config( 'artisanpack.visual-editor.user_model', 'App\\Models\\User' );

		return $this->belongsTo( $userModel );
	}

	/**
	 * Create a revision snapshot of this template part.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $userId The ID of the user creating the revision.
	 *
	 * @return Revision
	 */
	public function createRevision( ?int $userId = null ): Revision
	{
		return Revision::create( [
			'document_type' => 'template_part',
			'document_id'   => $this->id,
			'blocks'        => $this->content,
			'user_id'       => $userId,
			'created_at'    => now(),
		] );
	}

	/**
	 * Get all revisions for this template part.
	 *
	 * @since 1.0.0
	 *
	 * @return \Illuminate\Database\Eloquent\Collection<int, Revision>
	 */
	public function revisions(): \Illuminate\Database\Eloquent\Collection
	{
		return Revision::forDocument( 'template_part', $this->id )
			->orderByDesc( 'created_at' )
			->get();
	}

	/**
	 * Duplicate this template part with a new slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $newSlug The slug for the duplicated template part.
	 * @param string|null $newName Optional new name; defaults to original name with " (Copy)" appended.
	 *
	 * @return static
	 */
	public function duplicate( string $newSlug, ?string $newName = null ): static
	{
		return static::create( [
			'name'        => $newName ?? $this->name . ' (' . __( 'Copy' ) . ')',
			'slug'        => $newSlug,
			'description' => $this->description,
			'area'        => $this->area,
			'content'     => $this->content,
			'status'      => 'draft',
			'is_custom'   => true,
			'is_locked'   => false,
			'user_id'     => $this->user_id,
		] );
	}

	/**
	 * Get the attributes that should be cast.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'content'   => 'array',
			'is_custom' => 'boolean',
			'is_locked' => 'boolean',
			'user_id'   => 'integer',
		];
	}
}
