<?php

/**
 * Experiment Variant model.
 *
 * Represents an individual A/B test variation with
 * impression and conversion tracking.
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
 * Experiment Variant model class.
 *
 * @since 1.0.0
 *
 * @property int                             $id
 * @property int                             $experiment_id
 * @property string                          $name
 * @property bool                            $is_control
 * @property array                           $content_data
 * @property int                             $impressions
 * @property int                             $conversions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read float                      $conversion_rate
 */
class ExperimentVariant extends Model
{
	use HasFactory;

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table = 've_experiment_variants';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 *
	 * @var list<string>
	 */
	protected $fillable = [
		'experiment_id',
		'name',
		'is_control',
		'content_data',
		'impressions',
		'conversions',
	];

	// =========================================
	// Relationships
	// =========================================

	/**
	 * Gets the experiment this variant belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @return BelongsTo<Experiment, $this>
	 */
	public function experiment(): BelongsTo
	{
		return $this->belongsTo( Experiment::class, 'experiment_id' );
	}

	// =========================================
	// Scopes
	// =========================================

	/**
	 * Scopes a query to only include control variants.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<ExperimentVariant> $query The query builder instance.
	 *
	 * @return Builder<ExperimentVariant>
	 */
	public function scopeControl( Builder $query ): Builder
	{
		return $query->where( 'is_control', true );
	}

	// =========================================
	// Accessors
	// =========================================

	/**
	 * Gets the conversion rate as a percentage.
	 *
	 * @since 1.0.0
	 *
	 * @return float
	 */
	public function getConversionRateAttribute(): float
	{
		if ( 0 === $this->impressions ) {
			return 0.0;
		}

		return round( ( $this->conversions / $this->impressions ) * 100, 2 );
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
			'content_data' => 'array',
			'is_control'   => 'boolean',
		];
	}
}
