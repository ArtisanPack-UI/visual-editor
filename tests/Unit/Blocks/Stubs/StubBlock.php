<?php

/**
 * Stub Block for Testing.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Blocks\Stubs
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace Tests\Unit\Blocks\Stubs;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * A concrete stub block for testing BaseBlock functionality.
 *
 * This block does not have a block.json and relies on class properties
 * for backward compatibility testing.
 *
 * @since 1.0.0
 */
class StubBlock extends BaseBlock
{
	protected string $type = 'stub';

	protected string $name = 'Stub Block';

	protected string $description = 'A stub block for testing';

	protected string $icon = 'cube';

	protected string $category = 'text';

	protected array $keywords = [ 'test', 'stub' ];

	/**
	 * Get the content field schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		return [
			'text' => [
				'type'    => 'text',
				'label'   => 'Text',
				'default' => 'Hello',
			],
			'level' => [
				'type'    => 'select',
				'label'   => 'Level',
				'options' => [ 'h1' => 'H1', 'h2' => 'H2' ],
				'default' => 'h2',
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return [
			'alignment' => [
				'type'    => 'alignment',
				'label'   => 'Alignment',
				'default' => 'left',
			],
			'color' => [
				'type'  => 'color',
				'label' => 'Color',
			],
		];
	}

	/**
	 * Get available block transforms.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	public function getTransforms(): array
	{
		return [
			'paragraph' => [
				'text' => 'text',
			],
		];
	}

	/**
	 * Get the block's supported features.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getSupports(): array
	{
		return [
			'align'      => true,
			'color'      => [
				'text'       => true,
				'background' => false,
			],
			'typography' => [
				'fontSize'   => true,
				'fontFamily' => false,
			],
			'spacing'    => [
				'margin'  => false,
				'padding' => false,
			],
			'border'     => false,
			'anchor'     => true,
			'htmlId'     => true,
			'className'  => true,
		];
	}
}
