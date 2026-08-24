<?php

/**
 * ThemeFontBundle factory.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Database\Factories;

use ArtisanPackUI\VisualEditor\Fonts\Models\Font;
use ArtisanPackUI\VisualEditor\Fonts\Models\ThemeFontBundle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThemeFontBundle>
 */
class ThemeFontBundleFactory extends Factory
{
	protected $model = ThemeFontBundle::class;

	public function definition(): array
	{
		return [
			'theme_slug' => str( $this->faker->unique()->words( 2, true ) )->slug()->toString(),
			'font_id'    => Font::factory(),
			'faces'      => [
				[ 'weight' => 400, 'style' => 'normal' ],
				[ 'weight' => 700, 'style' => 'normal' ],
			],
		];
	}
}
