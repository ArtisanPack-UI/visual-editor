<?php

declare(strict_types=1);

namespace Tests\Stubs;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

class StubBlock extends BaseBlock
{
    protected string $type = 'stub';

    protected string $name = 'Stub Block';

    protected string $description = 'A stub block for testing.';

    protected string $icon = 'cube';

    protected string $category = 'text';

    protected array $keywords = ['test', 'stub'];

    public function getContentSchema(): array
    {
        return [
            'text' => [
                'type' => 'text',
                'label' => 'Text',
                'default' => '',
                'placeholder' => 'Enter text...',
            ],
        ];
    }
}
