<?php

/**
 * Font factory.
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
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Font>
 */
class FontFactory extends Factory
{
	protected $model = Font::class;

	public function definition(): array
	{
		$family = $this->faker->unique()->words( 2, true );
		$slug   = str( $family )->slug()->toString();

		return [
			'provider'     => $this->faker->randomElement( [ 'google', 'bunny', 'custom' ] ),
			'family'       => ucwords( $family ),
			'slug'         => $slug,
			'is_variable'  => false,
			'license'      => $this->faker->randomElement( [ 'OFL-1.1', 'Apache-2.0', null ] ),
			'source_url'   => $this->faker->url(),
			'installed_at' => now(),
		];
	}

	/**
	 * Mark the font as a variable font.
	 *
	 * @since 1.7.0
	 */
	public function variable(): static
	{
		return $this->state( fn ( array $attributes ): array => [
			'is_variable' => true,
		] );
	}
}
