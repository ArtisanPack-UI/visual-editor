<?php

/**
 * FontFace factory.
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
use ArtisanPackUI\VisualEditor\Fonts\Models\FontFace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FontFace>
 */
class FontFaceFactory extends Factory
{
	protected $model = FontFace::class;

	public function definition(): array
	{
		$weight = $this->faker->randomElement( [ 300, 400, 500, 700 ] );
		$style  = $this->faker->randomElement( [ 'normal', 'italic' ] );

		return [
			'font_id'   => Font::factory(),
			'weight'    => $weight,
			'style'     => $style,
			'format'    => 'woff2',
			'disk'      => 'public',
			'path'      => 'visual-editor/fonts/' . $this->faker->uuid() . '.woff2',
			'file_size' => $this->faker->numberBetween( 10_000, 200_000 ),
			'axes'      => null,
		];
	}

	/**
	 * Attach variable-font axis metadata to the face and pair it with a
	 * variable parent font so the pair stays internally consistent.
	 *
	 * @since 1.7.0
	 */
	public function variable(): static
	{
		return $this->state( fn (): array => [
			'font_id' => Font::factory()->variable(),
			'axes'    => [
				'wght' => [ 'min' => 100, 'max' => 900, 'default' => 400 ],
			],
		] );
	}
}
