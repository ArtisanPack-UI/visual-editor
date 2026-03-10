<?php

/**
 * Stub Container Block for Testing.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Blocks\Stubs
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace Tests\Unit\Blocks\Stubs;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * A concrete stub container block for testing inner blocks functionality.
 *
 * @since 2.0.0
 */
class StubContainerBlock extends BaseBlock
{
	protected string $type = 'stub-container';

	protected string $name = 'Stub Container';

	protected string $description = 'A stub container block for testing';

	protected string $icon = 'rectangle-group';

	protected string $category = 'layout';

	/**
	 * @inheritDoc
	 */
	public function getContentSchema(): array
	{
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function getStyleSchema(): array
	{
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function supportsInnerBlocks(): bool
	{
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function hasJsRenderer(): bool
	{
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getInnerBlocksOrientation(): string
	{
		return 'horizontal';
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedChildren(): ?array
	{
		return [ 'stub', 'paragraph' ];
	}
}
