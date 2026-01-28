<?php

/**
 * Content Revision model.
 *
 * Represents a revision of content, including autosaves,
 * manual saves, and named snapshots.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Models
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Content Revision model class.
 *
 * @since 1.0.0
 *
 * @property int                             $id
 * @property int                             $content_id
 * @property int                             $user_id
 * @property string                          $type
 * @property string|null                     $name
 * @property array                           $data
 * @property string|null                     $change_summary
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class ContentRevision extends Model
{
	use HasFactory;

	/**
	 * Indicates if the model should be timestamped.
	 *
	 * This model only has created_at, no updated_at.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public $timestamps = false;

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table = 've_content_revisions';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 *
	 * @var list<string>
	 */
	protected $fillable = [
		'content_id',
		'user_id',
		'type',
		'name',
		'data',
		'change_summary',
		'created_at',
	];

	// =========================================
	// Relationships
	// =========================================

	/**
	 * Gets the content this revision belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsTo<Content, $this>
	 */
	public function content(): BelongsTo
	{
		return $this->belongsTo( Content::class, 'content_id' );
	}

	/**
	 * Gets the user who created this revision.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsTo<Model, $this>
	 */
	public function user(): BelongsTo
	{
		$userModel = config( 'auth.providers.users.model', 'App\\Models\\User' );

		return $this->belongsTo( $userModel, 'user_id' );
	}

	// =========================================
	// Scopes
	// =========================================

	/**
	 * Scopes a query to only include autosave revisions.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<ContentRevision> $query The query builder instance.
	 *
	 * @return Builder<ContentRevision>
	 */
	public function scopeAutosaves( Builder $query ): Builder
	{
		return $query->where( 'type', 'autosave' );
	}

	/**
	 * Scopes a query to only include manual revisions.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<ContentRevision> $query The query builder instance.
	 *
	 * @return Builder<ContentRevision>
	 */
	public function scopeManual( Builder $query ): Builder
	{
		return $query->where( 'type', 'manual' );
	}

	/**
	 * Scopes a query to only include named revisions.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<ContentRevision> $query The query builder instance.
	 *
	 * @return Builder<ContentRevision>
	 */
	public function scopeNamed( Builder $query ): Builder
	{
		return $query->where( 'type', 'named' );
	}

	/**
	 * Scopes a query to a specific revision type.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<ContentRevision> $query The query builder instance.
	 * @param string                   $type  The revision type.
	 *
	 * @return Builder<ContentRevision>
	 */
	public function scopeOfType( Builder $query, string $type ): Builder
	{
		return $query->where( 'type', $type );
	}

	/**
	 * Gets the attributes that should be cast.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'data'       => 'array',
			'created_at' => 'datetime',
		];
	}
}
