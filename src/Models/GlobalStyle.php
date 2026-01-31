<?php

/**
 * Global Style model.
 *
 * Represents a global style entry for theme customizations
 * and CSS custom property overrides.
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

/**
 * Global Style model class.
 *
 * @since 1.0.0
 *
 * @property int                             $id
 * @property string                          $key
 * @property array                           $value
 * @property array|null                      $theme_default
 * @property bool                            $is_customized
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class GlobalStyle extends Model
{
	use HasFactory;

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $table = 've_global_styles';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 *
	 * @var list<string>
	 */
	protected $fillable = [
		'key',
		'value',
		'theme_default',
		'is_customized',
	];

	// =========================================
	// Scopes
	// =========================================

	/**
	 * Scopes a query to only include customized styles.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder<GlobalStyle> $query The query builder instance.
	 *
	 * @return Builder<GlobalStyle>
	 */
	public function scopeCustomized( Builder $query ): Builder
	{
		return $query->where( 'is_customized', true );
	}

	// =========================================
	// Methods
	// =========================================

	/**
	 * Finds a global style by its key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The style key.
	 *
	 * @return self|null
	 */
	public static function findByKey( string $key ): ?self
	{
		return static::where( 'key', $key )->first();
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
			'value'         => 'array',
			'theme_default' => 'array',
			'is_customized' => 'boolean',
		];
	}
}
