<?php

declare(strict_types=1);

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;
use ArtisanPackUI\VisualEditor\Services\BlockTransformService;

beforeEach(function (): void {
    $this->registry = app(BlockRegistry::class);
    $this->service = new BlockTransformService($this->registry);
});

// =========================================
// Basic Text-Based Transformations
// =========================================

test('transforms heading to text preserving content', function (): void {
    $block = [
        'id' => 've-block-1',
        'type' => 'heading',
        'content' => [
            'text' => 'Sample Heading',
            'level' => 'h2',
        ],
        'settings' => [
            'text_color' => '#000000',
        ],
    ];

    $result = $this->service->transform($block, 'text');

    expect($result['type'])->toBe('text')
        ->and($result['id'])->toBe('ve-block-1')
        ->and($result['content']['text'])->toBe('Sample Heading')
        ->and($result['settings']['text_color'])->toBe('#000000');
});

test('transforms text to heading with default level', function (): void {
    $block = [
        'id' => 've-block-2',
        'type' => 'text',
        'content' => [
            'text' => 'Sample Text',
        ],
        'settings' => [],
    ];

    $result = $this->service->transform($block, 'heading');

    expect($result['type'])->toBe('heading')
        ->and($result['content']['text'])->toBe('Sample Text')
        ->and($result['content']['level'])->toBe('h2');
});

test('transforms list to quote preserving text', function (): void {
    $block = [
        'id' => 've-block-3',
        'type' => 'list',
        'content' => [
            'text' => 'List item text',
            'style' => 'bullet',
        ],
        'settings' => [],
    ];

    $result = $this->service->transform($block, 'quote');

    expect($result['type'])->toBe('quote')
        ->and($result['content']['text'])->toBe('List item text')
        ->and($result['content']['citation'])->toBe('');
});

// =========================================
// Button Transformations
// =========================================

test('transforms button to button_group', function (): void {
    $block = [
        'id' => 've-block-4',
        'type' => 'button',
        'content' => [
            'text' => 'Click Me',
            'url' => '/action',
            'target' => '_blank',
        ],
        'settings' => [],
    ];

    $result = $this->service->transform($block, 'button_group');

    expect($result['type'])->toBe('button_group')
        ->and($result['content']['buttons'])->toBeArray()
        ->and($result['content']['buttons'])->toHaveCount(1)
        ->and($result['content']['buttons'][0]['text'])->toBe('Click Me')
        ->and($result['content']['buttons'][0]['url'])->toBe('/action')
        ->and($result['content']['buttons'][0]['target'])->toBe('_blank');
});

test('transforms button_group to button using first button', function (): void {
    $block = [
        'id' => 've-block-5',
        'type' => 'button_group',
        'content' => [
            'buttons' => [
                [
                    'text' => 'First Button',
                    'url' => '/first',
                    'target' => '_self',
                ],
                [
                    'text' => 'Second Button',
                    'url' => '/second',
                    'target' => '_blank',
                ],
            ],
        ],
        'settings' => [],
    ];

    $result = $this->service->transform($block, 'button');

    expect($result['type'])->toBe('button')
        ->and($result['content']['text'])->toBe('First Button')
        ->and($result['content']['url'])->toBe('/first')
        ->and($result['content']['target'])->toBe('_self');
});

// =========================================
// Columns to Grid Transformation
// =========================================

test('transforms columns to grid preserving all content', function (): void {
    $block = [
        'id' => 've-block-cols-1',
        'type' => 'columns',
        'content' => [
            'columns' => [
                [
                    'id' => 've-col-1',
                    'blocks' => [
                        [
                            'id' => 've-heading-1',
                            'type' => 'heading',
                            'content' => ['text' => 'Column 1 Heading'],
                            'settings' => [],
                        ],
                        [
                            'id' => 've-text-1',
                            'type' => 'text',
                            'content' => ['text' => 'Column 1 Text'],
                            'settings' => [],
                        ],
                    ],
                ],
                [
                    'id' => 've-col-2',
                    'blocks' => [
                        [
                            'id' => 've-heading-2',
                            'type' => 'heading',
                            'content' => ['text' => 'Column 2 Heading'],
                            'settings' => [],
                        ],
                    ],
                ],
            ],
        ],
        'settings' => [],
    ];

    $result = $this->service->transform($block, 'grid');

    expect($result['type'])->toBe('grid')
        ->and($result['id'])->toBe('ve-block-cols-1')
        ->and($result['content']['items'])->toBeArray()
        ->and($result['content']['items'])->toHaveCount(2);

    // Check first grid item (simple structure with just id and inner_blocks)
    expect($result['content']['items'][0]['id'])->toContain('ve-item-')
        ->and($result['content']['items'][0]['inner_blocks'])->toHaveCount(2)
        ->and($result['content']['items'][0]['inner_blocks'][0]['id'])->toBe('ve-heading-1')
        ->and($result['content']['items'][0]['inner_blocks'][0]['content']['text'])->toBe('Column 1 Heading')
        ->and($result['content']['items'][0]['inner_blocks'][1]['id'])->toBe('ve-text-1')
        ->and($result['content']['items'][0]['inner_blocks'][1]['content']['text'])->toBe('Column 1 Text');

    // Check second grid item
    expect($result['content']['items'][1]['id'])->toContain('ve-item-')
        ->and($result['content']['items'][1]['inner_blocks'])->toHaveCount(1)
        ->and($result['content']['items'][1]['inner_blocks'][0]['id'])->toBe('ve-heading-2')
        ->and($result['content']['items'][1]['inner_blocks'][0]['content']['text'])->toBe('Column 2 Heading');
});

test('transforms empty columns to grid', function (): void {
    $block = [
        'id' => 've-block-cols-2',
        'type' => 'columns',
        'content' => [
            'columns' => [
                [
                    'id' => 've-col-1',
                    'blocks' => [],
                ],
                [
                    'id' => 've-col-2',
                    'blocks' => [],
                ],
            ],
        ],
        'settings' => [],
    ];

    $result = $this->service->transform($block, 'grid');

    expect($result['type'])->toBe('grid')
        ->and($result['content']['items'])->toHaveCount(2)
        ->and($result['content']['items'][0]['inner_blocks'])->toBeArray()
        ->and($result['content']['items'][0]['inner_blocks'])->toHaveCount(0)
        ->and($result['content']['items'][1]['inner_blocks'])->toBeArray()
        ->and($result['content']['items'][1]['inner_blocks'])->toHaveCount(0);
});

test('creates grid items with correct structure', function (): void {
    $block = [
        'id' => 've-block-cols-3',
        'type' => 'columns',
        'content' => [
            'columns' => [
                [
                    'id' => 've-col-1',
                    'blocks' => [],
                ],
            ],
        ],
        'settings' => [],
    ];

    $result = $this->service->transform($block, 'grid');

    // Grid items should have simple structure with just id and inner_blocks
    expect($result['content']['items'][0]['id'])->toContain('ve-item-')
        ->and($result['content']['items'][0]['inner_blocks'])->toBeArray()
        ->and($result['content']['items'][0])->not->toHaveKey('type')
        ->and($result['content']['items'][0])->not->toHaveKey('content')
        ->and($result['content']['items'][0])->not->toHaveKey('settings');
});

test('sets grid columns setting to match number of items', function (): void {
    $block = [
        'id' => 've-block-cols-4',
        'type' => 'columns',
        'content' => [
            'columns' => [
                ['id' => 've-col-1', 'blocks' => []],
                ['id' => 've-col-2', 'blocks' => []],
                ['id' => 've-col-3', 'blocks' => []],
            ],
        ],
        'settings' => [],
    ];

    $result = $this->service->transform($block, 'grid');

    expect($result['settings']['columns'])->toBe('3');
});

// =========================================
// Grid to Columns Transformation
// =========================================

test('transforms grid to columns preserving all content', function (): void {
    $block = [
        'id' => 've-block-grid-1',
        'type' => 'grid',
        'content' => [
            'items' => [
                [
                    'id' => 've-item-1',
                    'inner_blocks' => [
                        [
                            'id' => 've-heading-1',
                            'type' => 'heading',
                            'content' => ['text' => 'Grid Item 1 Heading'],
                            'settings' => [],
                        ],
                        [
                            'id' => 've-text-1',
                            'type' => 'text',
                            'content' => ['text' => 'Grid Item 1 Text'],
                            'settings' => [],
                        ],
                    ],
                ],
                [
                    'id' => 've-item-2',
                    'inner_blocks' => [
                        [
                            'id' => 've-heading-2',
                            'type' => 'heading',
                            'content' => ['text' => 'Grid Item 2 Heading'],
                            'settings' => [],
                        ],
                    ],
                ],
            ],
        ],
        'settings' => [],
    ];

    $result = $this->service->transform($block, 'columns');

    expect($result['type'])->toBe('columns')
        ->and($result['id'])->toBe('ve-block-grid-1')
        ->and($result['content']['columns'])->toBeArray()
        ->and($result['content']['columns'])->toHaveCount(2);

    // Check first column
    expect($result['content']['columns'][0]['blocks'])->toHaveCount(2)
        ->and($result['content']['columns'][0]['blocks'][0]['id'])->toBe('ve-heading-1')
        ->and($result['content']['columns'][0]['blocks'][0]['content']['text'])->toBe('Grid Item 1 Heading')
        ->and($result['content']['columns'][0]['blocks'][1]['id'])->toBe('ve-text-1')
        ->and($result['content']['columns'][0]['blocks'][1]['content']['text'])->toBe('Grid Item 1 Text');

    // Check second column
    expect($result['content']['columns'][1]['blocks'])->toHaveCount(1)
        ->and($result['content']['columns'][1]['blocks'][0]['id'])->toBe('ve-heading-2')
        ->and($result['content']['columns'][1]['blocks'][0]['content']['text'])->toBe('Grid Item 2 Heading');
});

test('transforms empty grid to columns', function (): void {
    $block = [
        'id' => 've-block-grid-2',
        'type' => 'grid',
        'content' => [
            'items' => [
                [
                    'id' => 've-item-1',
                    'inner_blocks' => [],
                ],
                [
                    'id' => 've-item-2',
                    'inner_blocks' => [],
                ],
            ],
        ],
        'settings' => [],
    ];

    $result = $this->service->transform($block, 'columns');

    expect($result['type'])->toBe('columns')
        ->and($result['content']['columns'])->toHaveCount(2)
        ->and($result['content']['columns'][0]['blocks'])->toBeArray()
        ->and($result['content']['columns'][0]['blocks'])->toHaveCount(0)
        ->and($result['content']['columns'][1]['blocks'])->toBeArray()
        ->and($result['content']['columns'][1]['blocks'])->toHaveCount(0);
});

test('sets columns preset to match number of grid items', function (): void {
    $block = [
        'id' => 've-block-grid-3',
        'type' => 'grid',
        'content' => [
            'items' => [
                ['id' => 've-item-1', 'inner_blocks' => []],
                ['id' => 've-item-2', 'inner_blocks' => []],
                ['id' => 've-item-3', 'inner_blocks' => []],
            ],
        ],
        'settings' => [],
    ];

    $result = $this->service->transform($block, 'columns');

    expect($result['settings']['preset'])->toBe('33-33-33');
});

// =========================================
// Settings Mapping
// =========================================

test('preserves common settings between block types', function (): void {
    $block = [
        'id' => 've-block-6',
        'type' => 'heading',
        'content' => ['text' => 'Heading'],
        'settings' => [
            'text_color' => '#FF0000',
            'background_color' => '#FFFFFF',
            'font_size' => '24px',
        ],
    ];

    $result = $this->service->transform($block, 'text');

    expect($result['settings']['text_color'])->toBe('#FF0000')
        ->and($result['settings']['background_color'])->toBe('#FFFFFF')
        ->and($result['settings']['font_size'])->toBe('24px');
});

test('does not preserve unsupported settings', function (): void {
    $block = [
        'id' => 've-block-7',
        'type' => 'heading',
        'content' => ['text' => 'Heading'],
        'settings' => [
            'text_color' => '#FF0000',
            'border_width' => '2px',
            'border_radius' => '5px',
        ],
    ];

    $result = $this->service->transform($block, 'text');

    expect($result['settings'])->toHaveKey('text_color')
        ->and($result['settings'])->not->toHaveKey('border_width')
        ->and($result['settings'])->not->toHaveKey('border_radius');
});

// =========================================
// Error Handling
// =========================================

test('throws exception for disallowed transformation', function (): void {
    $block = [
        'id' => 've-block-8',
        'type' => 'image',
        'content' => ['url' => '/image.jpg'],
        'settings' => [],
    ];

    $this->service->transform($block, 'heading');
})->throws(InvalidArgumentException::class, 'Cannot transform block type "image" to "heading".');

test('throws exception for non-existent target type', function (): void {
    $block = [
        'id' => 've-block-9',
        'type' => 'heading',
        'content' => ['text' => 'Heading'],
        'settings' => [],
    ];

    $this->service->transform($block, 'nonexistent_type');
})->throws(InvalidArgumentException::class);

// =========================================
// Round-Trip Transformations
// =========================================

test('round-trip columns to grid and back preserves content', function (): void {
    $originalBlock = [
        'id' => 've-block-roundtrip-1',
        'type' => 'columns',
        'content' => [
            'columns' => [
                [
                    'id' => 've-col-1',
                    'blocks' => [
                        [
                            'id' => 've-heading-1',
                            'type' => 'heading',
                            'content' => ['text' => 'Original Heading'],
                            'settings' => [],
                        ],
                    ],
                ],
            ],
        ],
        'settings' => [],
    ];

    // Transform to grid
    $gridBlock = $this->service->transform($originalBlock, 'grid');

    // Transform back to columns
    $columnsBlock = $this->service->transform($gridBlock, 'columns');

    expect($columnsBlock['type'])->toBe('columns')
        ->and($columnsBlock['content']['columns'][0]['blocks'][0]['id'])->toBe('ve-heading-1')
        ->and($columnsBlock['content']['columns'][0]['blocks'][0]['content']['text'])->toBe('Original Heading');
});
