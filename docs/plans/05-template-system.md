# Visual Editor - Template System

> **Phase:** 3 (Template System) — Medium Priority
>
> Includes: Template hierarchy, Template parts editing, Template library, Theme integration

---

## Overview

The template system provides full site editing capabilities, allowing users to visually edit headers, footers, sidebars, and all template parts with a WordPress-style template hierarchy.

## Template Hierarchy

The template hierarchy determines which template is used for a given request:

```text
Request Type              Template Chain (first match wins)
─────────────────────────────────────────────────────────────
Single Post               single-{type}-{slug} → single-{type} → single → index
Single Page               page-{slug} → page → single → index
Archive                   archive-{type} → archive → index
Category                  category-{slug} → category → archive → index
Tag                       tag-{slug} → tag → archive → index
Author                    author-{username} → author → archive → index
Search                    search → index
404                       404 → index
Home (posts)              home → index
Front Page (static)       front-page → page → index
```

## Template Interface

```php
namespace ArtisanPackUI\VisualEditor\Templates\Contracts;

interface TemplateInterface
{
    public function getSlug(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function getType(): string; // page, post, archive, etc.

    public function getTemplateParts(): array;
    public function getContentArea(): array;
    public function getStyleSchema(): array;

    public function render(array $context = []): string;
}
```

---

## Template Parts

Template parts are reusable components that can appear in multiple templates.

### Header Part

```php
class HeaderPart extends BaseTemplatePart
{
    protected string $slug = 'header';
    protected string $name = 'Header';

    public function getDefaultBlocks(): array
    {
        return [
            [
                'type' => 'group',
                'styles' => ['display' => 'flex', 'justify' => 'between', 'align' => 'center'],
                'children' => [
                    [
                        'type' => 'global_content',
                        'content' => ['key' => 'site_logo'],
                    ],
                    [
                        'type' => 'navigation',
                        'content' => ['menu' => 'primary'],
                    ],
                    [
                        'type' => 'button',
                        'content' => ['text' => 'Contact', 'url' => '/contact'],
                        'styles' => ['style' => 'primary'],
                    ],
                ],
            ],
        ];
    }

    public function getStyleSchema(): array
    {
        return [
            'background' => [
                'type' => 'background',
                'label' => __('Background'),
            ],
            'sticky' => [
                'type' => 'toggle',
                'label' => __('Sticky Header'),
                'default' => false,
            ],
            'transparent' => [
                'type' => 'toggle',
                'label' => __('Transparent on Hero'),
                'default' => false,
            ],
            'padding' => [
                'type' => 'spacing',
                'label' => __('Padding'),
            ],
        ];
    }
}
```

### Footer Part

```php
class FooterPart extends BaseTemplatePart
{
    protected string $slug = 'footer';
    protected string $name = 'Footer';

    public function getDefaultBlocks(): array
    {
        return [
            [
                'type' => 'columns',
                'content' => ['columns' => '4'],
                'children' => [
                    [
                        'type' => 'column',
                        'children' => [
                            ['type' => 'global_content', 'content' => ['key' => 'site_logo']],
                            ['type' => 'paragraph', 'content' => ['text' => 'Company description.']],
                        ],
                    ],
                    [
                        'type' => 'column',
                        'children' => [
                            ['type' => 'heading', 'content' => ['text' => 'Quick Links', 'level' => 'h4']],
                            ['type' => 'navigation', 'content' => ['menu' => 'footer']],
                        ],
                    ],
                    [
                        'type' => 'column',
                        'children' => [
                            ['type' => 'heading', 'content' => ['text' => 'Contact', 'level' => 'h4']],
                            ['type' => 'global_content', 'content' => ['key' => 'address']],
                            ['type' => 'global_content', 'content' => ['key' => 'phone', 'format' => 'link']],
                            ['type' => 'global_content', 'content' => ['key' => 'email', 'format' => 'link']],
                        ],
                    ],
                    [
                        'type' => 'column',
                        'children' => [
                            ['type' => 'heading', 'content' => ['text' => 'Follow Us', 'level' => 'h4']],
                            ['type' => 'social_links', 'content' => []],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'divider',
            ],
            [
                'type' => 'group',
                'styles' => ['display' => 'flex', 'justify' => 'between'],
                'children' => [
                    ['type' => 'paragraph', 'content' => ['text' => '© 2026 Company Name. All rights reserved.']],
                    ['type' => 'navigation', 'content' => ['menu' => 'legal']],
                ],
            ],
        ];
    }
}
```

### Sidebar Part

```php
class SidebarPart extends BaseTemplatePart
{
    protected string $slug = 'sidebar';
    protected string $name = 'Sidebar';

    public function getDefaultBlocks(): array
    {
        return [
            [
                'type' => 'heading',
                'content' => ['text' => 'Search', 'level' => 'h4'],
            ],
            [
                'type' => 'search_form',
            ],
            [
                'type' => 'heading',
                'content' => ['text' => 'Categories', 'level' => 'h4'],
            ],
            [
                'type' => 'category_list',
            ],
            [
                'type' => 'heading',
                'content' => ['text' => 'Recent Posts', 'level' => 'h4'],
            ],
            [
                'type' => 'latest_posts',
                'content' => ['count' => 5, 'show_image' => false],
            ],
        ];
    }
}
```

---

## Template Types

### Page Template

```php
class PageTemplate extends BaseTemplate
{
    protected string $slug = 'page';
    protected string $name = 'Default Page';
    protected string $type = 'page';

    public function getTemplateParts(): array
    {
        return ['header', 'footer'];
    }

    public function getContentArea(): array
    {
        return [
            'max_width' => 'container',
            'padding' => 'large',
        ];
    }
}
```

### Full Width Page Template

```php
class FullWidthPageTemplate extends BaseTemplate
{
    protected string $slug = 'page-full-width';
    protected string $name = 'Full Width Page';
    protected string $type = 'page';

    public function getTemplateParts(): array
    {
        return ['header', 'footer'];
    }

    public function getContentArea(): array
    {
        return [
            'max_width' => 'full',
            'padding' => 'none',
        ];
    }
}
```

### Single Post Template

```php
class SinglePostTemplate extends BaseTemplate
{
    protected string $slug = 'single';
    protected string $name = 'Single Post';
    protected string $type = 'post';

    public function getTemplateParts(): array
    {
        return ['header', 'sidebar', 'footer'];
    }

    public function getContentArea(): array
    {
        return [
            'layout' => 'sidebar-right',
            'sidebar_width' => '300px',
            'max_width' => 'container',
        ];
    }

    public function getDefaultBlocks(): array
    {
        return [
            ['type' => 'post_title'],
            ['type' => 'post_meta', 'content' => ['show' => ['date', 'author', 'categories']]],
            ['type' => 'post_featured_image'],
            ['type' => 'post_content'],
            ['type' => 'post_tags'],
            ['type' => 'post_author_bio'],
            ['type' => 'post_comments'],
        ];
    }
}
```

### Archive Template

```php
class ArchiveTemplate extends BaseTemplate
{
    protected string $slug = 'archive';
    protected string $name = 'Archive';
    protected string $type = 'archive';

    public function getTemplateParts(): array
    {
        return ['header', 'sidebar', 'footer'];
    }

    public function getDefaultBlocks(): array
    {
        return [
            ['type' => 'archive_title'],
            ['type' => 'archive_description'],
            ['type' => 'post_loop', 'content' => ['layout' => 'list']],
            ['type' => 'pagination'],
        ];
    }

    public function getStyleSchema(): array
    {
        return [
            'layout' => [
                'type' => 'select',
                'label' => __('Post Layout'),
                'options' => [
                    'list' => __('List'),
                    'grid' => __('Grid'),
                    'masonry' => __('Masonry'),
                ],
                'default' => 'list',
            ],
            'columns' => [
                'type' => 'select',
                'label' => __('Grid Columns'),
                'options' => ['2', '3', '4'],
                'default' => '3',
                'condition' => ['layout', 'in', ['grid', 'masonry']],
            ],
        ];
    }
}
```

---

## Template Resolution

```php
class TemplateResolver
{
    public function resolve(string $type, ?string $slug = null, ?string $contentType = null): TemplateInterface
    {
        $candidates = $this->buildCandidateList($type, $slug, $contentType);

        foreach ($candidates as $candidate) {
            if ($template = $this->findTemplate($candidate)) {
                return $template;
            }
        }

        return $this->findTemplate('index');
    }

    protected function buildCandidateList(string $type, ?string $slug, ?string $contentType): array
    {
        return match($type) {
            'single' => [
                $contentType && $slug ? "single-{$contentType}-{$slug}" : null,
                $contentType ? "single-{$contentType}" : null,
                'single',
                'index',
            ],
            'page' => [
                $slug ? "page-{$slug}" : null,
                'page',
                'single',
                'index',
            ],
            'archive' => [
                $contentType ? "archive-{$contentType}" : null,
                'archive',
                'index',
            ],
            'category' => [
                $slug ? "category-{$slug}" : null,
                'category',
                'archive',
                'index',
            ],
            // ... more types
            default => ['index'],
        };
    }
}
```

---

## Database Schema

### Templates Table

```php
Schema::create('ve_templates', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->string('name');
    $table->string('description')->nullable();
    $table->string('type'); // page, post, archive, etc.
    $table->json('template_parts'); // ['header', 'footer', 'sidebar']
    $table->json('content_area_settings');
    $table->json('styles')->nullable();
    $table->boolean('is_custom')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### Template Parts Table

```php
Schema::create('ve_template_parts', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->string('name');
    $table->json('blocks');
    $table->json('styles')->nullable();
    $table->json('lock')->nullable();
    $table->timestamps();
});
```

---

## Template Library

### Saving Templates

Users can save custom templates:

1. Create/modify a template
2. Click "Save as Template"
3. Name and describe the template
4. Template saved to library

### Exporting Templates

Templates can be exported as JSON for sharing:

```json
{
    "version": "1.0",
    "type": "template",
    "slug": "my-custom-template",
    "name": "My Custom Template",
    "template_parts": ["header", "footer"],
    "content_area_settings": {
        "max_width": "container",
        "layout": "full-width"
    },
    "styles": {
        "background": "white"
    },
    "parts": {
        "header": {
            "blocks": [...],
            "styles": {...}
        },
        "footer": {
            "blocks": [...],
            "styles": {...}
        }
    }
}
```

### Importing Templates

```php
class ImportService
{
    public function importTemplate(array $data): Template
    {
        // Validate structure
        $this->validateTemplateStructure($data);

        // Create template
        $template = Template::create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'type' => $data['type'] ?? 'page',
            'template_parts' => $data['template_parts'],
            'content_area_settings' => $data['content_area_settings'],
            'styles' => $data['styles'] ?? [],
            'is_custom' => true,
        ]);

        // Import template parts
        foreach ($data['parts'] ?? [] as $slug => $partData) {
            TemplatePart::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $partData['name'] ?? ucfirst($slug),
                    'blocks' => $partData['blocks'],
                    'styles' => $partData['styles'] ?? [],
                ]
            );
        }

        return $template;
    }
}
```

---

## Theme Integration

Themes can provide default templates:

```php
// In theme service provider

public function boot()
{
    // Register custom templates
    Templates::register(new MyTheme\Templates\LandingPageTemplate());
    Templates::register(new MyTheme\Templates\PortfolioTemplate());

    // Register template parts
    TemplateParts::register(new MyTheme\Parts\MegaMenuHeader());
    TemplateParts::register(new MyTheme\Parts\NewsletterFooter());
}
```

Themes can also provide template part variations:

```php
'header_variations' => [
    'default' => HeaderPart::class,
    'centered' => CenteredHeaderPart::class,
    'minimal' => MinimalHeaderPart::class,
],
```

---

## Configuration

```php
// config/visual-editor.php

'templates' => [
    // Allow users to create custom templates
    'enable_custom_templates' => true,

    // Allow editing of existing templates
    'enable_template_editing' => true,

    // Default template parts included in new templates
    'default_template_parts' => ['header', 'footer'],

    // Directory for template part view files
    'template_parts_directory' => 'template-parts',
],
```
