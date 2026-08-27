<?php

/**
 * FontFace model.
 *
 * One weight/style variant of an installed {@see Font}, backed by a
 * self-hosted file on the configured disk. Variable fonts store their
 * parsed axis ranges in `axes`.
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

use ArtisanPackUI\VisualEditor\Database\Factories\FontFaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $font_id
 * @property int         $weight
 * @property string      $style
 * @property string      $format
 * @property string      $disk
 * @property string      $path
 * @property int         $file_size
 * @property array|null  $axes
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class FontFace extends Model
{
	use HasFactory;

	protected $table = 've_font_faces';

	protected $fillable = [
		'font_id',
		'weight',
		'style',
		'format',
		'disk',
		'path',
		'file_size',
		'axes',
	];

	protected function casts(): array
	{
		return [
			'font_id'   => 'integer',
			'weight'    => 'integer',
			'file_size' => 'integer',
			'axes'      => 'array',
		];
	}

	/**
	 * The font this face belongs to.
	 *
	 * @since 1.7.0
	 *
	 * @return BelongsTo<Font, FontFace>
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
	protected static function newFactory(): FontFaceFactory
	{
		return FontFaceFactory::new();
	}
}
