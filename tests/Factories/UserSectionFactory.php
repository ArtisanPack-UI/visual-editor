<?php

declare( strict_types=1 );

/**
 * User Section Factory
 *
 * Factory for creating test UserSection instances.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Factories
 *
 * @since      1.1.0
 */

namespace Tests\Factories;

use ArtisanPackUI\VisualEditor\Models\UserSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * User Section Factory class.
 *
 * @extends Factory<UserSection>
 *
 * @since 1.1.0
 */
class UserSectionFactory extends Factory
{
	/**
	 * The name of the factory's corresponding model.
	 *
	 * @var class-string<UserSection>
	 */
	protected $model = UserSection::class;

	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array
	{
		return [
			'name'        => fake()->words( 3, true ),
			'description' => fake()->sentence(),
			'category'    => fake()->randomElement( [ 'headers', 'content', 'features', 'cta' ] ),
			'blocks'      => [
				[
					'id'       => 've-block-' . fake()->uuid(),
					'type'     => 'heading',
					'content'  => [ 'text' => fake()->sentence(), 'level' => 'h2' ],
					'settings' => [],
				],
				[
					'id'       => 've-block-' . fake()->uuid(),
					'type'     => 'text',
					'content'  => [ 'text' => fake()->paragraph() ],
					'settings' => [],
				],
			],
			'styles'        => [],
			'preview_image' => null,
			'is_shared'     => false,
			'use_count'     => 0,
		];
	}

	/**
	 * Indicate that the section is shared.
	 *
	 * @since 1.1.0
	 *
	 * @return static
	 */
	public function shared(): static
	{
		return $this->state( fn ( array $attributes ) => [
			'is_shared' => true,
		] );
	}
}
