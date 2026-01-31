# Visual Editor - Block System

> **Phases:** 1 (Core) & 2 (Full Library)
> - **Phase 1**: Block Registry, Block Interface, Basic Text Blocks (heading, paragraph, list, quote, code)
> - **Phase 2**: Media Blocks, Layout Blocks, Interactive Blocks, Embed Blocks, Dynamic Blocks, Additional Text Blocks

---

## Block Interface

```php
namespace ArtisanPackUI\VisualEditor\Blocks\Contracts;

interface BlockInterface
{
    // Identification
    public function getType(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function getIcon(): string;
    public function getCategory(): string;
    public function getKeywords(): array;

    // Schema
    public function getContentSchema(): array;
    public function getStyleSchema(): array;
    public function getAdvancedSchema(): array;

    // Defaults
    public function getDefaultContent(): array;
    public function getDefaultStyles(): array;

    // Constraints
    public function getAllowedParents(): ?array;
    public function getAllowedChildren(): ?array;

    // Rendering
    public function render(array $content, array $styles, array $context = []): string;
    public function renderEditor(array $content, array $styles, array $context = []): string;

    // Versioning
    public function getVersion(): int;
    public function migrate(array $content, int $fromVersion): array;
}
```

## Field Types

### Text Field
```php
'field_name' => [
    'type' => 'text',
    'label' => 'Field Label',
    'placeholder' => 'Placeholder text',
    'default' => '',
    'validation' => 'required|max:255',
]
```

### Rich Text Field
```php
'field_name' => [
    'type' => 'rich_text',
    'label' => 'Content',
    'toolbar' => ['bold', 'italic', 'link', 'list'],
    'placeholder' => 'Start typing...',
]
```

### Select Field
```php
'field_name' => [
    'type' => 'select',
    'label' => 'Choose Option',
    'options' => [
        'option1' => 'Option 1',
        'option2' => 'Option 2',
    ],
    'default' => 'option1',
]
```

### Toggle Field
```php
'field_name' => [
    'type' => 'toggle',
    'label' => 'Enable Feature',
    'default' => false,
]
```

### Media Field
```php
'field_name' => [
    'type' => 'media',
    'label' => 'Select Image',
    'media_type' => 'image', // image, video, audio, file
    'multiple' => false,
]
```

### Color Field
```php
'field_name' => [
    'type' => 'color',
    'label' => 'Text Color',
    'default' => null, // null = inherit
    'allow_custom' => true,
    'presets' => ['primary', 'secondary', 'accent'],
]
```

### URL Field
```php
'field_name' => [
    'type' => 'url',
    'label' => 'Link URL',
    'placeholder' => 'https://example.com',
    'allow_internal' => true,
    'allow_external' => true,
]
```

### Alignment Field
```php
'field_name' => [
    'type' => 'alignment',
    'label' => 'Text Alignment',
    'options' => ['left', 'center', 'right', 'justify'],
    'default' => 'left',
]
```

### Spacing Field
```php
'field_name' => [
    'type' => 'spacing',
    'label' => 'Padding',
    'sides' => ['top', 'right', 'bottom', 'left'],
    'linked' => true, // allow linking all sides
]
```

### Repeater Field
```php
'field_name' => [
    'type' => 'repeater',
    'label' => 'Items',
    'min' => 1,
    'max' => 10,
    'fields' => [
        'title' => ['type' => 'text', 'label' => 'Title'],
        'description' => ['type' => 'text', 'label' => 'Description'],
    ],
]
```

### Conditional Fields
```php
'field_name' => [
    'type' => 'text',
    'label' => 'Link Text',
    'condition' => ['link_url', '!=', ''], // Only show when link_url is set
]
```

---

## Core Blocks Specification

### Heading Block

**Type:** `heading`
**Category:** `text`
**Icon:** `h1`

```php
class HeadingBlock extends BaseBlock
{
    protected string $type = 'heading';
    protected string $name = 'Heading';
    protected string $description = 'Add a heading to your content';
    protected string $icon = 'h1';
    protected string $category = 'text';
    protected array $keywords = ['title', 'h1', 'h2', 'h3', 'header'];

    public function getContentSchema(): array
    {
        return [
            'text' => [
                'type' => 'rich_text',
                'label' => __('Heading Text'),
                'placeholder' => __('Add heading...'),
                'toolbar' => ['bold', 'italic', 'link'],
            ],
            'level' => [
                'type' => 'select',
                'label' => __('Level'),
                'options' => [
                    'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3',
                    'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6',
                ],
                'default' => 'h2',
            ],
        ];
    }

    public function getStyleSchema(): array
    {
        return [
            'alignment' => [
                'type' => 'alignment',
                'label' => __('Text Alignment'),
                'options' => ['left', 'center', 'right'],
                'default' => 'left',
            ],
            'color' => [
                'type' => 'color',
                'label' => __('Text Color'),
                'default' => null,
            ],
        ];
    }
}
```

### Paragraph Block

**Type:** `paragraph`
**Category:** `text`
**Icon:** `bars-3-bottom-left`

```php
class ParagraphBlock extends BaseBlock
{
    protected string $type = 'paragraph';
    protected string $name = 'Paragraph';
    protected string $description = 'Add a paragraph of text';
    protected string $icon = 'bars-3-bottom-left';
    protected string $category = 'text';
    protected array $keywords = ['text', 'content', 'body'];

    public function getContentSchema(): array
    {
        return [
            'text' => [
                'type' => 'rich_text',
                'label' => __('Content'),
                'placeholder' => __('Start writing...'),
                'toolbar' => ['bold', 'italic', 'link', 'list'],
            ],
        ];
    }

    public function getStyleSchema(): array
    {
        return [
            'alignment' => [
                'type' => 'alignment',
                'label' => __('Text Alignment'),
                'default' => 'left',
            ],
            'size' => [
                'type' => 'select',
                'label' => __('Font Size'),
                'options' => [
                    'small' => __('Small'),
                    'base' => __('Normal'),
                    'large' => __('Large'),
                    'xl' => __('Extra Large'),
                ],
                'default' => 'base',
            ],
            'drop_cap' => [
                'type' => 'toggle',
                'label' => __('Drop Cap'),
                'default' => false,
            ],
        ];
    }
}
```

### Image Block

**Type:** `image`
**Category:** `media`
**Icon:** `photo`

```php
class ImageBlock extends BaseBlock
{
    protected string $type = 'image';
    protected string $name = 'Image';
    protected string $description = 'Add an image to your content';
    protected string $icon = 'photo';
    protected string $category = 'media';
    protected array $keywords = ['photo', 'picture', 'img'];

    public function getContentSchema(): array
    {
        return [
            'media_id' => [
                'type' => 'media',
                'label' => __('Image'),
                'media_type' => 'image',
            ],
            'alt' => [
                'type' => 'text',
                'label' => __('Alt Text'),
                'help' => __('Describe the image for accessibility'),
            ],
            'caption' => [
                'type' => 'rich_text',
                'label' => __('Caption'),
                'toolbar' => ['bold', 'italic', 'link'],
            ],
            'link' => [
                'type' => 'url',
                'label' => __('Link URL'),
            ],
            'link_target' => [
                'type' => 'select',
                'label' => __('Link Target'),
                'options' => [
                    '_self' => __('Same window'),
                    '_blank' => __('New window'),
                ],
                'default' => '_self',
                'condition' => ['link', '!=', ''],
            ],
        ];
    }

    public function getStyleSchema(): array
    {
        return [
            'size' => [
                'type' => 'select',
                'label' => __('Size'),
                'options' => [
                    'small' => __('Small'),
                    'medium' => __('Medium'),
                    'large' => __('Large'),
                    'full' => __('Full Width'),
                ],
                'default' => 'large',
            ],
            'alignment' => [
                'type' => 'alignment',
                'label' => __('Alignment'),
                'default' => 'center',
            ],
            'rounded' => [
                'type' => 'toggle',
                'label' => __('Rounded Corners'),
                'default' => false,
            ],
            'shadow' => [
                'type' => 'toggle',
                'label' => __('Drop Shadow'),
                'default' => false,
            ],
        ];
    }
}
```

### Button Block

**Type:** `button`
**Category:** `interactive`
**Icon:** `cursor-arrow-rays`

```php
class ButtonBlock extends BaseBlock
{
    protected string $type = 'button';
    protected string $name = 'Button';
    protected string $description = 'Add a call-to-action button';
    protected string $icon = 'cursor-arrow-rays';
    protected string $category = 'interactive';
    protected array $keywords = ['cta', 'link', 'action'];

    public function getContentSchema(): array
    {
        return [
            'text' => [
                'type' => 'text',
                'label' => __('Button Text'),
                'placeholder' => __('Click me'),
                'default' => __('Click me'),
            ],
            'url' => [
                'type' => 'url',
                'label' => __('Link URL'),
                'placeholder' => 'https://example.com',
            ],
            'target' => [
                'type' => 'select',
                'label' => __('Link Target'),
                'options' => [
                    '_self' => __('Same window'),
                    '_blank' => __('New window'),
                ],
                'default' => '_self',
            ],
            'icon' => [
                'type' => 'icon',
                'label' => __('Icon'),
                'position' => ['before', 'after'],
            ],
        ];
    }

    public function getStyleSchema(): array
    {
        return [
            'style' => [
                'type' => 'select',
                'label' => __('Style'),
                'options' => [
                    'primary' => __('Primary'),
                    'secondary' => __('Secondary'),
                    'outline' => __('Outline'),
                    'ghost' => __('Ghost'),
                ],
                'default' => 'primary',
            ],
            'size' => [
                'type' => 'select',
                'label' => __('Size'),
                'options' => [
                    'small' => __('Small'),
                    'medium' => __('Medium'),
                    'large' => __('Large'),
                ],
                'default' => 'medium',
            ],
            'full_width' => [
                'type' => 'toggle',
                'label' => __('Full Width'),
                'default' => false,
            ],
        ];
    }
}
```

### Columns Block

**Type:** `columns`
**Category:** `layout`
**Icon:** `view-columns`

```php
class ColumnsBlock extends BaseBlock
{
    protected string $type = 'columns';
    protected string $name = 'Columns';
    protected string $description = 'Create multi-column layouts';
    protected string $icon = 'view-columns';
    protected string $category = 'layout';
    protected array $keywords = ['grid', 'layout', 'row'];

    public function getContentSchema(): array
    {
        return [
            'columns' => [
                'type' => 'select',
                'label' => __('Number of Columns'),
                'options' => [
                    '2' => __('2 Columns'),
                    '3' => __('3 Columns'),
                    '4' => __('4 Columns'),
                ],
                'default' => '2',
            ],
            'layout' => [
                'type' => 'select',
                'label' => __('Layout'),
                'options' => [
                    'equal' => __('Equal Width'),
                    '2-1' => __('2/3 + 1/3'),
                    '1-2' => __('1/3 + 2/3'),
                    '1-1-2' => __('25% + 25% + 50%'),
                ],
                'default' => 'equal',
                'condition' => ['columns', '==', '2'],
            ],
        ];
    }

    public function getStyleSchema(): array
    {
        return [
            'gap' => [
                'type' => 'select',
                'label' => __('Gap'),
                'options' => [
                    'none' => __('None'),
                    'small' => __('Small'),
                    'medium' => __('Medium'),
                    'large' => __('Large'),
                ],
                'default' => 'medium',
            ],
            'vertical_alignment' => [
                'type' => 'select',
                'label' => __('Vertical Alignment'),
                'options' => [
                    'top' => __('Top'),
                    'center' => __('Center'),
                    'bottom' => __('Bottom'),
                    'stretch' => __('Stretch'),
                ],
                'default' => 'top',
            ],
            'stack_on_mobile' => [
                'type' => 'toggle',
                'label' => __('Stack on Mobile'),
                'default' => true,
            ],
        ];
    }

    public function getAllowedChildren(): ?array
    {
        return ['column']; // Only column blocks allowed inside
    }
}
```

---

## Complete Block List

| Block | Type | Category | Has Children |
| ------- | ------ | ---------- | -------------- |
| Heading | `heading` | text | No |
| Paragraph | `paragraph` | text | No |
| List | `list` | text | No |
| Quote | `quote` | text | No |
| Code | `code` | text | No |
| Image | `image` | media | No |
| Gallery | `gallery` | media | No |
| Video | `video` | media | No |
| Audio | `audio` | media | No |
| File | `file` | media | No |
| Button | `button` | interactive | No |
| Button Group | `button_group` | interactive | Yes (buttons) |
| Form | `form` | interactive | No |
| Tabs | `tabs` | interactive | Yes (tab items) |
| Accordion | `accordion` | interactive | Yes (accordion items) |
| Columns | `columns` | layout | Yes (columns) |
| Group | `group` | layout | Yes (any) |
| Spacer | `spacer` | layout | No |
| Divider | `divider` | layout | No |
| Map | `map` | embeds | No |
| Social Embed | `social_embed` | embeds | No |
| HTML | `html` | embeds | No |
| Shortcode | `shortcode` | embeds | No |
| Latest Posts | `latest_posts` | dynamic | No |
| Table of Contents | `table_of_contents` | dynamic | No |
| Global Content | `global_content` | dynamic | No |

---

## Block Registration

### Via Service Provider

```php
// In your package or app service provider

use ArtisanPackUI\VisualEditor\Facades\Blocks;

public function boot()
{
    Blocks::register(new MyCustomBlock());
}
```

### Via Config

```php
// config/visual-editor.php

'blocks' => [
    'custom_block' => [
        'name' => 'Custom Block',
        'description' => 'My custom block',
        'icon' => 'cube',
        'category' => 'custom',
        'view' => 'my-theme::blocks.custom',
        'content_schema' => [
            'text' => [
                'type' => 'text',
                'label' => 'Text',
            ],
        ],
        'style_schema' => [
            'alignment' => [
                'type' => 'alignment',
                'label' => 'Alignment',
            ],
        ],
    ],
],
```

### Via Hook

```php
addFilter('ap.visualEditor.blocksRegister', function (array $blocks) {
    $blocks['custom_block'] = new MyCustomBlock();
    return $blocks;
});
```

---

## Block Unregistration

Developers can unregister blocks (including core blocks) to customize the editor.

### Via Service Provider

```php
use ArtisanPackUI\VisualEditor\Facades\Blocks;

public function boot()
{
    // Unregister a single block
    Blocks::unregister('html');

    // Unregister multiple blocks
    Blocks::unregister(['html', 'code', 'shortcode']);

    // Unregister all blocks in a category
    Blocks::unregisterCategory('embeds');
}
```

### Via Config

```php
// config/visual-editor.php

'blocks' => [
    'core' => [
        'heading' => true,
        'paragraph' => true,
        'html' => false, // Disabled
        'code' => false, // Disabled
        // ...
    ],

    // Alternative: explicitly disable blocks
    'disabled' => [
        'html',
        'code',
        'shortcode',
    ],
],
```

### Via Hook

```php
// Unregister blocks via filter hook
addFilter('ap.visualEditor.blocksRegister', function (array $blocks) {
    unset($blocks['html']);
    unset($blocks['code']);
    return $blocks;
});

// Or use a dedicated action
addAction('ap.visualEditor.blocksInit', function () {
    Blocks::unregister(['html', 'code']);
});
```

---

## Block Versioning & Migrations

Each block has a version number. When the schema changes, increment the version and add a migration:

```php
class HeadingBlock extends BaseBlock
{
    protected int $version = 2;

    public function migrate(array $content, int $fromVersion): array
    {
        if ($fromVersion < 2) {
            // In v2 we renamed 'size' to 'level'
            if (isset($content['size'])) {
                $content['level'] = $content['size'];
                unset($content['size']);
            }
        }

        return $content;
    }
}
```

The system automatically runs migrations when rendering old content.
