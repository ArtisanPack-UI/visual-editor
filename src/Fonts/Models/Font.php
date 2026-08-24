<?php

/**
 * Font model.
 *
 * A single installed font family in the Font Library, sourced from a
 * provider (Google, Bunny, custom upload, …). Weight/style variants are
 * persisted as {@see FontFace} rows; theme-scoped selections as
 * {@see ThemeFontBundle} rows.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Fonts\Models;

use ArtisanPackUI\VisualEditor\Database\Factories\FontFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int                            $id
 * @property string                         $provider
 * @property string                         $family
 * @property string                         $slug
 * @property bool                           $is_variable
 * @property string|null                    $license
 * @property string|null                    $source_url
 * @property \Illuminate\Support\Carbon|null $installed_at
 * @property \Illuminate\Support\Carbon     $created_at
 * @property \Illuminate\Support\Carbon     $updated_at
 */
class Font extends Model
{
	use HasFactory;

	protected $table = 've_fonts';

	protected $fillable = [
		'provider',
		'family',
		'slug',
		'is_variable',
		'license',
		'source_url',
		'installed_at',
	];

	protected function casts(): array
	{
		return [
			'is_variable'  => 'boolean',
			'installed_at' => 'datetime',
		];
	}

	/**
	 * The weight/style faces belonging to this font.
	 *
	 * @since 1.7.0
	 *
	 * @return HasMany<FontFace>
	 */
	public function faces(): HasMany
	{
		return $this->hasMany( FontFace::class );
	}

	/**
	 * The theme bundles that reference this font.
	 *
	 * @since 1.7.0
	 *
	 * @return HasMany<ThemeFontBundle>
	 */
	public function themeBundles(): HasMany
	{
		return $this->hasMany( ThemeFontBundle::class );
	}

	/**
	 * Package factories don't live under Laravel's default
	 * `Database\Factories` root, so point HasFactory at the package's
	 * namespaced factory explicitly.
	 *
	 * @since 1.7.0
	 */
	protected static function newFactory(): FontFactory
	{
		return FontFactory::new();
	}
}
