<?php

declare(strict_types=1);

namespace Tests\Stubs;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

class StubCustomUiBlock extends BaseBlock
{
    protected string $type = 'stub-custom-ui';

    protected string $name = 'Custom UI Block';

    protected string $description = 'A block with custom toolbar and inspector.';

    protected string $category = 'layout';

    public function getContentSchema(): array
    {
        return [];
    }

    public function hasCustomToolbar(): bool
    {
        return true;
    }

    public function renderToolbar(): string
    {
        return '<button class="custom-toolbar-btn">Custom Action</button>';
    }

    public function hasCustomInspector(): bool
    {
        return true;
    }

    public function renderInspector(): string
    {
        return '<div class="custom-inspector-panel">Custom Settings</div>';
    }
}
