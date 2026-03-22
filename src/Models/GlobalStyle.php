<?php

/**
 * Global Style Model.
 *
 * Persists the site-wide global style settings (color palette, typography
 * presets, and spacing scale) in the database. Supports revision history
 * via the existing polymorphic Revision model.
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
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * Global Style model for persisting site-wide style settings.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Models
 *
 * @property int              $id
 * @property string           $key
 * @property array|null       $palette
 * @property array|null       $typography
 * @property array|null       $spacing
 * @property int|null         $user_id
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 *
 * @since      1.0.0
 */
class GlobalStyle extends Model
{
	/**
	 * The default key used for the primary global styles record.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public const DEFAULT_KEY = 'default';

	/**
	 * The document type identifier used for revision history.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public const REVISION_DOCUMENT_TYPE = 'global_style';

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table = 'visual_editor_global_styles';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'key',
		'palette',
		'typography',
		'spacing',
		'user_id',
	];

	/**
	 * Get the user that last modified the global styles.
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
	 * Get the revision history for this global style record.
	 *
	 * @since 1.0.0
	 *
	 * @return HasMany
	 */
	public function revisions(): HasMany
	{
		return $this->hasMany( Revision::class, 'document_id' )
			->where( 'document_type', self::REVISION_DOCUMENT_TYPE )
			->orderByDesc( 'created_at' );
	}

	/**
	 * Create a revision snapshot of the current state.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $userId The user creating the revision.
	 *
	 * @return Revision
	 */
	public function createRevision( ?int $userId = null ): Revision
	{
		if ( ! $this->exists ) {
			throw new InvalidArgumentException( 'Cannot create a revision for an unsaved GlobalStyle.' );
		}

		$maxRevisions = (int) config( 'artisanpack.visual-editor.persistence.max_revisions', 50 );

		$revision = Revision::create( [
			'document_type' => self::REVISION_DOCUMENT_TYPE,
			'document_id'   => $this->id,
			'blocks'        => [
				'palette'    => $this->palette,
				'typography' => $this->typography,
				'spacing'    => $this->spacing,
			],
			'user_id'       => $userId,
			'created_at'    => now(),
		] );

		$this->pruneRevisions( $maxRevisions );

		return $revision;
	}

	/**
	 * Restore state from a revision.
	 *
	 * @since 1.0.0
	 *
	 * @param Revision $revision The revision to restore from.
	 *
	 * @return void
	 */
	public function restoreFromRevision( Revision $revision ): void
	{
		$data = $revision->blocks;

		$this->update( [
			'palette'    => $data['palette'] ?? null,
			'typography' => $data['typography'] ?? null,
			'spacing'    => $data['spacing'] ?? null,
		] );
	}

	/**
	 * Scope a query to find the record by key.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder $query The query builder instance.
	 * @param string  $key   The global style key.
	 *
	 * @return Builder
	 */
	public function scopeByKey( Builder $query, string $key ): Builder
	{
		return $query->where( 'key', $key );
	}

	/**
	 * Prune old revisions beyond the configured maximum.
	 *
	 * @since 1.0.0
	 *
	 * @param int $maxRevisions Maximum number of revisions to keep.
	 *
	 * @return void
	 */
	protected function pruneRevisions( int $maxRevisions ): void
	{
		$keepIds = Revision::forDocument( self::REVISION_DOCUMENT_TYPE, $this->id )
			->orderByDesc( 'created_at' )
			->limit( $maxRevisions )
			->pluck( 'id' );

		Revision::where( 'document_type', self::REVISION_DOCUMENT_TYPE )
			->where( 'document_id', $this->id )
			->whereNotIn( 'id', $keepIds )
			->delete();
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
			'palette'    => 'array',
			'typography' => 'array',
			'spacing'    => 'array',
			'user_id'    => 'integer',
		];
	}
}
