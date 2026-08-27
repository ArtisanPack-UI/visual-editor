<?php

/**
 * ThemeFontBundle model.
 *
 * Links a theme to a library {@see Font}, recording which faces the
 * theme depends on so the bundle stays portable and can reinstall
 * missing library entries on activation.
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

use ArtisanPackUI\VisualEditor\Database\Factories\ThemeFontBundleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property string      $theme_slug
 * @property int         $font_id
 * @property array|null  $faces
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class ThemeFontBundle extends Model
{
	use HasFactory;

	protected $table = 've_theme_font_bundles';

	protected $fillable = [
		'theme_slug',
		'font_id',
		'faces',
	];

	protected function casts(): array
	{
		return [
			'font_id' => 'integer',
			'faces'   => 'array',
		];
	}

	/**
	 * The font this bundle references.
	 *
	 * @since 1.7.0
	 *
	 * @return BelongsTo<Font, ThemeFontBundle>
	 */
	public function font(): BelongsTo
	{
		return $this->belongsTo( Font::class );
	}

	/**
	 * Package factories don't live under Laravel's default
	 * `Database\Factories` root, so point HasFactory at the package's
	 * namespaced factory explicitly.
	 *
	 * @since 1.7.0
	 */
	protected static function newFactory(): ThemeFontBundleFactory
	{
		return ThemeFontBundleFactory::new();
	}
}
