<?php

declare(strict_types=1);

namespace Tests\Stubs;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

class StubContainerBlock extends BaseBlock
{
    protected string $type = 'stub-container';

    protected string $name = 'Stub Container';

    protected string $description = 'A container block for testing.';

    protected string $icon = 'square-3-stack-3d';

    protected string $category = 'layout';

    protected array $keywords = ['container', 'layout'];

    protected bool $supportsInnerBlocks = true;

    protected string $innerBlocksOrientation = 'vertical';

    protected bool $hasJsRenderer = true;

    public function getContentSchema(): array
    {
        return [];
    }

    public function getAllowedChildren(): ?array
    {
        return ['stub', 'paragraph'];
    }
}
