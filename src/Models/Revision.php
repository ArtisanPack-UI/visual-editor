<?php

/**
 * Revision Model.
 *
 * Represents an immutable revision snapshot of a document's block content.
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
 * Revision model for storing document revision history.
 *
 * Revisions are immutable — they have no updated_at timestamp.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Models
 *
 * @property int         $id
 * @property string      $document_type
 * @property int         $document_id
 * @property array       $blocks
 * @property int|null    $user_id
 * @property \Carbon\Carbon $created_at
 *
 * @since      1.0.0
 */
class Revision extends Model
{
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * Revisions are immutable, so only created_at is used.
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
	protected $table = 'visual_editor_revisions';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'document_type',
		'document_id',
		'blocks',
		'user_id',
		'created_at',
	];

	/**
	 * Scope a query to revisions for a specific document.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query        The query builder instance.
	 * @param string  $documentType The document type.
	 * @param int     $documentId   The document ID.
	 *
	 * @return Builder
	 */
	public function scopeForDocument( Builder $query, string $documentType, int $documentId ): Builder
	{
		return $query->where( 'document_type', $documentType )
			->where( 'document_id', $documentId );
	}

	/**
	 * Scope a query to revisions created by a specific user.
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
		if ( null === $userId ) {
			return $query->whereNull( 'user_id' );
		}

		return $query->where( 'user_id', $userId );
	}

	/**
	 * Get the user that created the revision.
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
	 * Get the attributes that should be cast.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'blocks'      => 'array',
			'document_id' => 'integer',
			'user_id'     => 'integer',
			'created_at'  => 'datetime',
		];
	}
}
