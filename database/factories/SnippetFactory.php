<?php

/**
 * Snippet factory.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Database\Factories;

use ArtisanPackUI\VisualEditor\Models\Snippet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Snippet>
 */
class SnippetFactory extends Factory
{
	protected $model = Snippet::class;

	public function definition(): array
	{
		$slug = 'snippet_' . $this->faker->unique()->numberBetween( 1, 1_000_000 );

		return [
			'slug'   => $slug,
			'title'  => $this->faker->sentence( 3 ),
			'blocks' => [
				[
					'name'        => 'artisanpack/paragraph',
					'attrs'       => [ 'content' => $this->faker->sentence() ],
					'innerBlocks' => [],
				],
			],
		];
	}
}
