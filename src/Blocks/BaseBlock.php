<?php

/**
 * Abstract base block for the visual editor.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\VisualEditor\Blocks;

use ArtisanPackUI\VisualEditor\Blocks\Contracts\BlockInterface;

abstract class BaseBlock implements BlockInterface
{
    /**
     * The block type identifier.
     *
     * @since 1.0.0
     */
    protected string $type = '';

    /**
     * The block display name.
     *
     * @since 1.0.0
     */
    protected string $name = '';

    /**
     * The block description.
     *
     * @since 1.0.0
     */
    protected string $description = '';

    /**
     * The block icon name.
     *
     * @since 1.0.0
     */
    protected string $icon = '_default';

    /**
     * The block category.
     *
     * @since 1.0.0
     */
    protected string $category = 'common';

    /**
     * Searchable keywords.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected array $keywords = [];

    /**
     * Whether this block is publicly visible in the inserter.
     *
     * @since 1.0.0
     */
    protected bool $public = true;

    /**
     * Supported alignment options.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected array $alignments = [];

    /**
     * Whether this block supports inner blocks.
     *
     * @since 1.0.0
     */
    protected bool $supportsInnerBlocks = false;

    /**
     * Inner blocks orientation ('vertical' or 'horizontal').
     *
     * @since 1.0.0
     */
    protected string $innerBlocksOrientation = 'vertical';

    /**
     * Whether this block has a custom JavaScript renderer.
     *
     * @since 1.0.0
     */
    protected bool $hasJsRenderer = false;

    /**
     * Block schema version.
     *
     * @since 1.0.0
     */
    protected int $version = 1;

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function getStyleSchema(): array
    {
        return [];
    }

    public function getAdvancedSchema(): array
    {
        return [];
    }

    public function getDefaultContent(): array
    {
        $defaults = [];
        foreach ($this->getContentSchema() as $key => $field) {
            $defaults[$key] = $field['default'] ?? '';
        }

        return $defaults;
    }

    public function getDefaultStyles(): array
    {
        $defaults = [];
        foreach ($this->getStyleSchema() as $key => $field) {
            $defaults[$key] = $field['default'] ?? '';
        }

        return $defaults;
    }

    public function getAllowedParents(): ?array
    {
        return null;
    }

    public function getAllowedChildren(): ?array
    {
        return null;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function getSupportedAlignments(): array
    {
        return $this->alignments;
    }

    public function supportsInnerBlocks(): bool
    {
        return $this->supportsInnerBlocks;
    }

    public function getInnerBlocksOrientation(): string
    {
        return $this->innerBlocksOrientation;
    }

    public function hasJsRenderer(): bool
    {
        return $this->hasJsRenderer;
    }

    public function hasCustomInspector(): bool
    {
        return false;
    }

    public function renderInspector(): string
    {
        return '';
    }

    public function hasCustomToolbar(): bool
    {
        return false;
    }

    public function renderToolbar(): string
    {
        return '';
    }

    public function getToolbarControls(): array
    {
        return [];
    }

    public function render(array $content, array $styles, array $context = []): string
    {
        return '';
    }

    public function renderEditor(array $content, array $styles, array $context = []): string
    {
        return '';
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function migrate(array $content, int $fromVersion): array
    {
        return $content;
    }

    /**
     * Get block metadata as an array for serialization.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'icon' => $this->getIcon(),
            'category' => $this->getCategory(),
            'keywords' => $this->getKeywords(),
            'public' => $this->isPublic(),
            'supportsInnerBlocks' => $this->supportsInnerBlocks(),
            'innerBlocksOrientation' => $this->getInnerBlocksOrientation(),
            'allowedChildren' => $this->getAllowedChildren(),
            'allowedParents' => $this->getAllowedParents(),
            'hasJsRenderer' => $this->hasJsRenderer(),
            'hasCustomInspector' => $this->hasCustomInspector(),
            'hasCustomToolbar' => $this->hasCustomToolbar(),
            'alignments' => $this->getSupportedAlignments(),
        ];
    }
}
