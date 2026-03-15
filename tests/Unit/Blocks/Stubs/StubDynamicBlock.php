<?php

/**
 * Stub Dynamic Block for Testing.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Blocks\Stubs
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace Tests\Unit\Blocks\Stubs;

use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;

/**
 * A concrete stub dynamic block for testing DynamicBlock functionality.
 *
 * @since 2.0.0
 */
class StubDynamicBlock extends DynamicBlock
{
	protected string $type = 'stub-dynamic';

	protected string $name = 'Stub Dynamic';

	protected string $description = 'A stub dynamic block for testing';

	protected string $icon = 'bolt';

	protected string $category = 'dynamic';

	/**
	 * Get the Livewire component class for this dynamic block.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function getComponent(): string
	{
		return 'App\\Livewire\\StubDynamicComponent';
	}

	/**
	 * Get the content field schema.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		return [
			'count' => [
				'type'    => 'range',
				'label'   => 'Count',
				'min'     => 1,
				'max'     => 10,
				'default' => 5,
			],
		];
	}
}
