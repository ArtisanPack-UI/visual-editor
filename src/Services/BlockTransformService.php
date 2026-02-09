<?php

declare(strict_types=1);

/**
 * Block Transform Service
 *
 * Handles transformation of blocks from one type to another, including
 * intelligent content and settings mapping.
 *
 *
 * @since      1.9.0
 */

namespace ArtisanPackUI\VisualEditor\Services;

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;
use InvalidArgumentException;

/**
 * Block Transform Service class.
 *
 * Provides functionality to transform blocks from one type to another
 * while intelligently mapping content and settings between types.
 *
 * @since 1.9.0
 */
class BlockTransformService
{
    /**
     * The block registry instance.
     *
     * @since 1.9.0
     */
    protected BlockRegistry $registry;

    /**
     * Create a new BlockTransformService instance.
     *
     * @since 1.9.0
     *
     * @param  BlockRegistry  $registry  The block registry.
     */
    public function __construct(BlockRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Transform a block from one type to another.
     *
     * @since 1.9.0
     *
     * @param  array  $block  The source block data.
     * @param  string  $toType  The target block type.
     * @return array The transformed block data.
     *
     * @throws InvalidArgumentException If the transformation is not allowed.
     */
    public function transform(array $block, string $toType): array
    {
        $fromType = $block['type'];

        if (! $this->registry->canTransformTo($fromType, $toType)) {
            throw new InvalidArgumentException(
                sprintf(
                    __('Cannot transform block type "%s" to "%s".'),
                    $fromType,
                    $toType,
                ),
            );
        }

        // Handle special case: columns → grid transformation
        if ($fromType === 'columns' && $toType === 'grid') {
            return $this->transformColumnsToGrid($block);
        }

        // Handle special case: grid → columns transformation
        if ($fromType === 'grid' && $toType === 'columns') {
            return $this->transformGridToColumns($block);
        }

        $transformed = [
            'id' => $block['id'],
            'type' => $toType,
            'content' => $this->mapContent($block, $toType),
            'settings' => $this->mapSettings($block, $toType),
        ];

        // Preserve inner blocks if both support them
        if (isset($block['inner_blocks'])) {
            $toConfig = $this->registry->get($toType);
            if ($toConfig['inner_blocks'] ?? false) {
                $transformed['inner_blocks'] = $this->transformInnerBlocks(
                    $block['inner_blocks'],
                    $fromType,
                    $toType,
                );
            }
        }

        return $transformed;
    }

    /**
     * Transform inner blocks when parent block type changes.
     *
     * Handles special cases like columns → grid (column → grid_item)
     * and grid → columns (grid_item → column).
     *
     * @since 1.9.0
     *
     * @param  array  $innerBlocks  The inner blocks to transform.
     * @param  string  $fromType  The parent's original type.
     * @param  string  $toType  The parent's target type.
     * @return array The transformed inner blocks.
     */
    protected function transformInnerBlocks(array $innerBlocks, string $fromType, string $toType): array
    {
        $childTypeMap = [
            'columns' => [
                'grid' => ['column' => 'grid_item'],
            ],
            'grid' => [
                'columns' => ['grid_item' => 'column'],
            ],
        ];

        // Check if we have a child type mapping for this transformation
        if (! isset($childTypeMap[$fromType][$toType])) {
            // No special mapping needed, return as-is
            return $innerBlocks;
        }

        $mapping = $childTypeMap[$fromType][$toType];
        $transformed = [];

        foreach ($innerBlocks as $innerBlock) {
            $childFromType = $innerBlock['type'];

            // Check if this child type needs to be transformed
            if (isset($mapping[$childFromType])) {
                $childToType = $mapping[$childFromType];

                // Transform the child block
                $transformedChild = [
                    'id' => $innerBlock['id'],
                    'type' => $childToType,
                    'content' => $innerBlock['content'] ?? [],
                    'settings' => $innerBlock['settings'] ?? [],
                ];

                // Recursively preserve inner blocks of the child
                if (isset($innerBlock['inner_blocks']) && ! empty($innerBlock['inner_blocks'])) {
                    $transformedChild['inner_blocks'] = $innerBlock['inner_blocks'];
                }

                $transformed[] = $transformedChild;
            } else {
                // Keep as-is if not in mapping
                $transformed[] = $innerBlock;
            }
        }

        return $transformed;
    }

    /**
     * Map content fields between block types.
     *
     * @since 1.9.0
     *
     * @param  array  $block  The source block data.
     * @param  string  $toType  The target block type.
     * @return array The mapped content.
     */
    protected function mapContent(array $block, string $toType): array
    {
        $fromType = $block['type'];
        $fromContent = $block['content'] ?? [];
        $toConfig = $this->registry->get($toType);
        $content = [];

        // Get content schema for target block
        $toSchema = $toConfig['content_schema'] ?? [];

        // Text-based transformations
        if ($this->isTextBasedBlock($fromType) && $this->isTextBasedBlock($toType)) {
            // Preserve text content
            if (isset($fromContent['text'])) {
                $content['text'] = $fromContent['text'];
            }

            // Add type-specific fields with defaults
            foreach ($toSchema as $field => $config) {
                if (! isset($content[$field])) {
                    $content[$field] = $config['default'] ?? $this->getDefaultForField($field, $toType);
                }
            }
        }

        // Media transformations
        if ($fromType === 'image' && $toType === 'video') {
            $content['url'] = $fromContent['media_id'] ?? '';
            $content['autoplay'] = false;
            $content['loop'] = false;
        }

        // Button to button group
        if ($fromType === 'button' && $toType === 'button_group') {
            $content['buttons'] = [
                [
                    'text' => $fromContent['text'] ?? '',
                    'url' => $fromContent['url'] ?? '',
                    'target' => $fromContent['target'] ?? '_self',
                ],
            ];
        }

        // Button group to button (use first button)
        if ($fromType === 'button_group' && $toType === 'button') {
            $buttons = $fromContent['buttons'] ?? [];
            if (! empty($buttons)) {
                $firstButton = $buttons[0];
                $content['text'] = $firstButton['text'] ?? '';
                $content['url'] = $firstButton['url'] ?? '';
                $content['target'] = $firstButton['target'] ?? '_self';
            }
        }

        return $content;
    }

    /**
     * Map settings between block types.
     *
     * @since 1.9.0
     *
     * @param  array  $block  The source block data.
     * @param  string  $toType  The target block type.
     * @return array The mapped settings.
     */
    protected function mapSettings(array $block, string $toType): array
    {
        $fromType = $block['type'];
        $fromSettings = $block['settings'] ?? [];
        $toConfig = $this->registry->get($toType);
        $settings = [];

        // Get settings schema for target block
        $toSchema = $toConfig['settings_schema'] ?? [];
        $fromConfig = $this->registry->get($fromType);
        $fromSupports = $fromConfig['supports'] ?? [];
        $toSupports = $toConfig['supports'] ?? [];

        // Preserve supported settings that both blocks share
        $commonSupports = array_intersect($fromSupports, $toSupports);

        foreach ($fromSettings as $key => $value) {
            // Check if this setting is relevant to common supports
            if ($this->isRelevantSetting($key, $commonSupports)) {
                $settings[$key] = $value;
            }
        }

        // Add missing required settings with defaults
        foreach ($toSchema as $field => $config) {
            if (! isset($settings[$field]) && isset($config['default'])) {
                $settings[$field] = $config['default'];
            }
        }

        return $settings;
    }

    /**
     * Check if a block type is text-based.
     *
     * @since 1.9.0
     *
     * @param  string  $type  The block type.
     * @return bool True if the block is text-based.
     */
    protected function isTextBasedBlock(string $type): bool
    {
        return in_array($type, ['text', 'heading', 'list', 'quote'], true);
    }

    /**
     * Get default value for a content field based on block type.
     *
     * @since 1.9.0
     *
     * @param  string  $field  The field name.
     * @param  string  $blockType  The block type.
     * @return mixed The default value.
     */
    protected function getDefaultForField(string $field, string $blockType): mixed
    {
        $defaults = [
            'heading' => ['level' => 'h2'],
            'list' => ['style' => 'bullet'],
            'quote' => ['citation' => ''],
            'button' => ['target' => '_self'],
        ];

        return $defaults[$blockType][$field] ?? null;
    }

    /**
     * Check if a setting is relevant to the given supports.
     *
     * @since 1.9.0
     *
     * @param  string  $key  The setting key.
     * @param  array  $supports  The array of support features.
     * @return bool True if the setting is relevant.
     */
    protected function isRelevantSetting(string $key, array $supports): bool
    {
        $settingSupportsMap = [
            'text_color' => ['typography', 'colors'],
            'background_color' => ['colors'],
            'font_size' => ['typography'],
            'font_weight' => ['typography'],
            'border_width' => ['borders'],
            'border_color' => ['borders'],
            'border_radius' => ['borders'],
        ];

        if (! isset($settingSupportsMap[$key])) {
            return true; // Unknown settings are preserved
        }

        foreach ($settingSupportsMap[$key] as $support) {
            if (in_array($support, $supports, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Transform a columns block to a grid block.
     *
     * Columns store data as content.columns[] with each column having a blocks array.
     * Grid stores data as content.items[] where each grid_item has content.inner_blocks.
     *
     * @since 1.9.0
     *
     * @param  array  $block  The columns block data.
     * @return array The transformed grid block data.
     */
    protected function transformColumnsToGrid(array $block): array
    {
        $columns = $block['content']['columns'] ?? [];
        $items = [];

        // Transform each column into a grid item (simple structure, not a full block)
        foreach ($columns as $index => $column) {
            $item = [
                'id' => 've-item-'.uniqid().'-'.$index,
                'inner_blocks' => $column['blocks'] ?? [],
            ];

            $items[] = $item;
        }

        // Map settings and set the grid columns count to match number of items
        $settings = $this->mapSettings($block, 'grid');
        $settings['columns'] = (string) count($items);

        return [
            'id' => $block['id'],
            'type' => 'grid',
            'content' => [
                'items' => $items,
            ],
            'settings' => $settings,
        ];
    }

    /**
     * Transform a grid block to a columns block.
     *
     * Grid stores data as content.items[] where each grid_item has content.inner_blocks.
     * Columns store data as content.columns[] with each column having a blocks array.
     *
     * @since 1.9.0
     *
     * @param  array  $block  The grid block data.
     * @return array The transformed columns block data.
     */
    protected function transformGridToColumns(array $block): array
    {
        $gridItems = $block['content']['items'] ?? [];
        $columns = [];

        // Transform each grid_item into a column
        foreach ($gridItems as $index => $gridItem) {
            $column = [
                'id' => 've-col-'.uniqid().'-'.$index,
                'blocks' => $gridItem['inner_blocks'] ?? [],
            ];

            $columns[] = $column;
        }

        // Map settings and set the preset to match number of columns
        $settings = $this->mapSettings($block, 'columns');
        $settings['preset'] = $this->getColumnsPreset(count($columns));

        return [
            'id' => $block['id'],
            'type' => 'columns',
            'content' => [
                'columns' => $columns,
            ],
            'settings' => $settings,
        ];
    }

    /**
     * Get the appropriate preset for the given number of columns.
     *
     * @since 1.9.0
     *
     * @param  int  $columnCount  The number of columns.
     * @return string The preset string (e.g., '50-50', '33-33-33').
     */
    protected function getColumnsPreset(int $columnCount): string
    {
        return match ($columnCount) {
            1 => '100',
            2 => '50-50',
            3 => '33-33-33',
            4 => '25-25-25-25',
            5 => '20-20-20-20-20',
            6 => '16-16-16-16-16-16',
            default => implode('-', array_fill(0, $columnCount, (string) (int) (100 / $columnCount))),
        };
    }
}
