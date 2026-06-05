<?php

/**
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace Tests\Fixtures;

use ArtisanPackUI\VisualEditor\Blocks\ProvidesBlockMetadata;

class TestBlockMetadata implements ProvidesBlockMetadata
{
	public static function blockMetadata(): array
	{
		return [
			'name'        => 'tests/metadata-block',
			'title'       => 'Metadata Block',
			'category'    => 'artisanpack',
			'description' => 'Fixture block registered via a class.',
			'attributes'  => [
				'label' => [
					'type'    => 'string',
					'default' => '',
				],
			],
		];
	}
}
