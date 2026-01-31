<?php

/**
 * Experiment model.
 *
 * Represents an A/B test experiment for content variations
 * including goal tracking, traffic splitting, and variant management.
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
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Experiment model class.
 *
 * @since 1.0.0
 *
 * @property int                             $id
 * @property int                             $content_id
 * @property int                             $created_by
 * @property string                          $name
 * @property string|null                     $description
 * @property string                          $type
 * @property int                             $traffic_split
 * @property string                          $goal_type
 * @property string|null                     $goal_target
 * @property string                          $status
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property int|null                        $winner_variant_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Experiment extends Model
{
	use HasFactory;

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table = 've_experiments';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 *
	 * @var list<string>
	 */
	protected $fillable = [
		'content_id',
		'created_by',
		'name',
		'description',
		'type',
		'traffic_split',
		'goal_type',
		'goal_target',
		'status',
		'started_at',
		'ended_at',
		'winner_variant_id',
	];

	// =========================================
	// Relationships
	// =========================================

	/**
	 * Gets the content this experiment belongs to.
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
	 * Gets the user who created this experiment.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsTo<Model, $this>
	 */
	public function creator(): BelongsTo
	{
		$userModel = config( 'auth.providers.users.model', 'App\\Models\\User' );

		return $this->belongsTo( $userModel, 'created_by' );
	}

	/**
	 * Gets the variants for this experiment.
	 *
	 * @since 1.0.0
	 *
	 * @return HasMany<ExperimentVariant, $this>
	 */
	public function variants(): HasMany
	{
		return $this->hasMany( ExperimentVariant::class, 'experiment_id' );
	}

	/**
	 * Gets the winning variant.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsTo<ExperimentVariant, $this>
	 */
	public function winnerVariant(): BelongsTo
	{
		return $this->belongsTo( ExperimentVariant::class, 'winner_variant_id' );
	}

	// =========================================
	// Scopes
	// =========================================

	/**
	 * Scopes a query to only include running experiments.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<Experiment> $query The query builder instance.
	 *
	 * @return Builder<Experiment>
	 */
	public function scopeRunning( Builder $query ): Builder
	{
		return $query->where( 'status', 'running' );
	}

	/**
	 * Scopes a query to only include draft experiments.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<Experiment> $query The query builder instance.
	 *
	 * @return Builder<Experiment>
	 */
	public function scopeDraft( Builder $query ): Builder
	{
		return $query->where( 'status', 'draft' );
	}

	/**
	 * Scopes a query to only include ended experiments.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<Experiment> $query The query builder instance.
	 *
	 * @return Builder<Experiment>
	 */
	public function scopeEnded( Builder $query ): Builder
	{
		return $query->where( 'status', 'ended' );
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
			'started_at' => 'datetime',
			'ended_at'   => 'datetime',
		];
	}
}
