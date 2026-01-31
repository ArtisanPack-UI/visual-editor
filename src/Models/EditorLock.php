<?php

/**
 * Editor Lock model.
 *
 * Represents an active editing lock on content to prevent
 * concurrent edits, with heartbeat-based lock management.
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
use Illuminate\Support\Carbon;

/**
 * Editor Lock model class.
 *
 * @since 1.0.0
 *
 * @property int    $id
 * @property int    $content_id
 * @property int    $user_id
 * @property string $session_id
 * @property Carbon $started_at
 * @property Carbon $last_heartbeat
 */
class EditorLock extends Model
{
	use HasFactory;

	/**
	 * Indicates if the model should be timestamped.
	 *
	 * This model uses started_at and last_heartbeat instead of
	 * standard created_at/updated_at timestamps.
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
	protected $table = 've_editor_locks';

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
		'session_id',
		'started_at',
		'last_heartbeat',
	];

	// =========================================
	// Relationships
	// =========================================

	/**
	 * Gets the content this lock belongs to.
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
	 * Gets the user who holds this lock.
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
	 * Scopes a query to only include expired locks.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<EditorLock> $query The query builder instance.
	 *
	 * @return Builder<EditorLock>
	 */
	public function scopeExpired( Builder $query ): Builder
	{
		$timeout = config( 'artisanpack.visual-editor.locking.lock_timeout', 120 );

		return $query->where( 'last_heartbeat', '<', Carbon::now()->subSeconds( $timeout ) );
	}

	/**
	 * Scopes a query to only include active (non-expired) locks.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<EditorLock> $query The query builder instance.
	 *
	 * @return Builder<EditorLock>
	 */
	public function scopeActive( Builder $query ): Builder
	{
		$timeout = config( 'artisanpack.visual-editor.locking.lock_timeout', 120 );

		return $query->where( 'last_heartbeat', '>=', Carbon::now()->subSeconds( $timeout ) );
	}

	// =========================================
	// Methods
	// =========================================

	/**
	 * Updates the heartbeat timestamp.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function heartbeat(): bool
	{
		$this->last_heartbeat = Carbon::now();

		return $this->save();
	}

	/**
	 * Checks if this lock has expired.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function isExpired(): bool
	{
		$timeout = config( 'artisanpack.visual-editor.locking.lock_timeout', 120 );

		if ( null === $this->last_heartbeat ) {
			return true;
		}

		return $this->last_heartbeat->lt( Carbon::now()->subSeconds( $timeout ) );
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
			'started_at'     => 'datetime',
			'last_heartbeat' => 'datetime',
		];
	}
}
