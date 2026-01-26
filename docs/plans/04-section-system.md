# Visual Editor - Section System

## Overview

Sections are pre-designed layouts containing blocks. They provide a "guardrails" approach where users work with professionally designed patterns rather than pixel-level control.

## Section Interface

```php
namespace ArtisanPackUI\VisualEditor\Sections\Contracts;

interface SectionInterface
{
    // Identification
    public function getType(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function getIcon(): string;
    public function getCategory(): string;
    public function getPreviewImage(): ?string;

    // Structure
    public function getDefaultBlocks(): array;
    public function getStyleSchema(): array;
    public function getLayoutOptions(): array;

    // Defaults
    public function getDefaultStyles(): array;

    // Rendering
    public function render(array $blocks, array $styles, array $context = []): string;
}
```

---

## Core Sections

### Hero Section

**Type:** `hero`
**Category:** `headers`

```php
class HeroSection extends BaseSection
{
    protected string $type = 'hero';
    protected string $name = 'Hero';
    protected string $description = 'Large hero section with headline and call to action';
    protected string $icon = 'rectangle-group';
    protected string $category = 'headers';

    public function getDefaultBlocks(): array
    {
        return [
            [
                'type' => 'heading',
                'content' => [
                    'text' => 'Welcome to Our Website',
                    'level' => 'h1',
                ],
                'styles' => [
                    'alignment' => 'center',
                ],
            ],
            [
                'type' => 'paragraph',
                'content' => [
                    'text' => 'We help small businesses succeed online with beautiful, effective websites.',
                ],
                'styles' => [
                    'alignment' => 'center',
                    'size' => 'large',
                ],
            ],
            [
                'type' => 'button_group',
                'content' => [
                    'buttons' => [
                        ['text' => 'Get Started', 'url' => '#', 'style' => 'primary'],
                        ['text' => 'Learn More', 'url' => '#', 'style' => 'outline'],
                    ],
                ],
                'styles' => [
                    'alignment' => 'center',
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
                'options' => ['color', 'image', 'gradient'],
            ],
            'min_height' => [
                'type' => 'select',
                'label' => __('Minimum Height'),
                'options' => [
                    'auto' => __('Auto'),
                    '50vh' => __('Half Screen'),
                    '80vh' => __('Large'),
                    '100vh' => __('Full Screen'),
                ],
                'default' => '80vh',
            ],
            'padding' => [
                'type' => 'select',
                'label' => __('Padding'),
                'options' => [
                    'small' => __('Small'),
                    'medium' => __('Medium'),
                    'large' => __('Large'),
                ],
                'default' => 'large',
            ],
            'content_width' => [
                'type' => 'select',
                'label' => __('Content Width'),
                'options' => [
                    'narrow' => __('Narrow'),
                    'medium' => __('Medium'),
                    'wide' => __('Wide'),
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
                ],
                'default' => 'center',
            ],
        ];
    }
}
```

### Hero with Image Section

**Type:** `hero_image`
**Category:** `headers`

```php
class HeroImageSection extends BaseSection
{
    protected string $type = 'hero_image';
    protected string $name = 'Hero with Image';
    protected string $description = 'Hero section with side-by-side image';
    protected string $icon = 'photo';
    protected string $category = 'headers';

    public function getDefaultBlocks(): array
    {
        return [
            [
                'type' => 'columns',
                'content' => ['columns' => '2', 'layout' => 'equal'],
                'children' => [
                    [
                        'type' => 'column',
                        'children' => [
                            [
                                'type' => 'heading',
                                'content' => ['text' => 'Your Headline Here', 'level' => 'h1'],
                            ],
                            [
                                'type' => 'paragraph',
                                'content' => ['text' => 'Supporting text that explains your value proposition.'],
                            ],
                            [
                                'type' => 'button_group',
                                'content' => [
                                    'buttons' => [
                                        ['text' => 'Get Started', 'style' => 'primary'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'column',
                        'children' => [
                            [
                                'type' => 'image',
                                'content' => ['media_id' => null, 'alt' => ''],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getLayoutOptions(): array
    {
        return [
            'image_position' => [
                'type' => 'select',
                'label' => __('Image Position'),
                'options' => [
                    'right' => __('Right'),
                    'left' => __('Left'),
                ],
                'default' => 'right',
            ],
        ];
    }
}
```

### Features Section

**Type:** `features`
**Category:** `content`

```php
class FeaturesSection extends BaseSection
{
    protected string $type = 'features';
    protected string $name = 'Features';
    protected string $description = 'Highlight key features or benefits';
    protected string $icon = 'squares-2x2';
    protected string $category = 'content';

    public function getDefaultBlocks(): array
    {
        return [
            [
                'type' => 'heading',
                'content' => ['text' => 'Why Choose Us', 'level' => 'h2'],
                'styles' => ['alignment' => 'center'],
            ],
            [
                'type' => 'paragraph',
                'content' => ['text' => 'Everything you need to succeed'],
                'styles' => ['alignment' => 'center'],
            ],
            [
                'type' => 'columns',
                'content' => ['columns' => '3'],
                'children' => [
                    $this->featureColumn('star', 'Feature One', 'Description of the first feature.'),
                    $this->featureColumn('shield-check', 'Feature Two', 'Description of the second feature.'),
                    $this->featureColumn('clock', 'Feature Three', 'Description of the third feature.'),
                ],
            ],
        ];
    }

    protected function featureColumn(string $icon, string $title, string $description): array
    {
        return [
            'type' => 'column',
            'children' => [
                ['type' => 'icon', 'content' => ['icon' => $icon, 'size' => 'large']],
                ['type' => 'heading', 'content' => ['text' => $title, 'level' => 'h3']],
                ['type' => 'paragraph', 'content' => ['text' => $description]],
            ],
        ];
    }

    public function getStyleSchema(): array
    {
        return [
            'columns' => [
                'type' => 'select',
                'label' => __('Number of Features'),
                'options' => ['2' => '2', '3' => '3', '4' => '4'],
                'default' => '3',
            ],
            'background' => [
                'type' => 'background',
                'label' => __('Background'),
            ],
            'padding' => [
                'type' => 'select',
                'label' => __('Padding'),
                'options' => ['small', 'medium', 'large'],
                'default' => 'large',
            ],
        ];
    }
}
```

### Testimonials Section

**Type:** `testimonials`
**Category:** `social_proof`

```php
class TestimonialsSection extends BaseSection
{
    protected string $type = 'testimonials';
    protected string $name = 'Testimonials';
    protected string $description = 'Customer testimonials and reviews';
    protected string $icon = 'chat-bubble-left-right';
    protected string $category = 'social_proof';

    public function getDefaultBlocks(): array
    {
        return [
            [
                'type' => 'heading',
                'content' => ['text' => 'What Our Customers Say', 'level' => 'h2'],
                'styles' => ['alignment' => 'center'],
            ],
            [
                'type' => 'columns',
                'content' => ['columns' => '3'],
                'children' => [
                    $this->testimonialColumn('"Amazing service!"', 'John Doe', 'CEO, Company'),
                    $this->testimonialColumn('"Highly recommended!"', 'Jane Smith', 'Designer'),
                    $this->testimonialColumn('"Best decision we made."', 'Bob Wilson', 'Founder'),
                ],
            ],
        ];
    }

    protected function testimonialColumn(string $quote, string $name, string $title): array
    {
        return [
            'type' => 'column',
            'children' => [
                ['type' => 'quote', 'content' => ['text' => $quote]],
                ['type' => 'paragraph', 'content' => ['text' => "— {$name}, {$title}"]],
            ],
        ];
    }

    public function getLayoutOptions(): array
    {
        return [
            'layout' => [
                'type' => 'select',
                'label' => __('Layout'),
                'options' => [
                    'grid' => __('Grid'),
                    'carousel' => __('Carousel'),
                ],
                'default' => 'grid',
            ],
        ];
    }
}
```

### CTA Section

**Type:** `cta`
**Category:** `conversion`

```php
class CtaSection extends BaseSection
{
    protected string $type = 'cta';
    protected string $name = 'Call to Action';
    protected string $description = 'Conversion-focused call to action banner';
    protected string $icon = 'megaphone';
    protected string $category = 'conversion';

    public function getDefaultBlocks(): array
    {
        return [
            [
                'type' => 'heading',
                'content' => ['text' => 'Ready to Get Started?', 'level' => 'h2'],
                'styles' => ['alignment' => 'center'],
            ],
            [
                'type' => 'paragraph',
                'content' => ['text' => 'Join thousands of satisfied customers today.'],
                'styles' => ['alignment' => 'center'],
            ],
            [
                'type' => 'button_group',
                'content' => [
                    'buttons' => [
                        ['text' => 'Start Free Trial', 'style' => 'primary', 'size' => 'large'],
                    ],
                ],
                'styles' => ['alignment' => 'center'],
            ],
        ];
    }

    public function getStyleSchema(): array
    {
        return [
            'background' => [
                'type' => 'background',
                'label' => __('Background'),
                'default' => 'primary',
            ],
            'padding' => [
                'type' => 'select',
                'label' => __('Padding'),
                'options' => ['medium', 'large'],
                'default' => 'large',
            ],
        ];
    }
}
```

---

## Section Categories

| Category | Description | Sections |
|----------|-------------|----------|
| `headers` | Page header sections | hero, hero_image |
| `content` | General content sections | features, services, text, text_image |
| `social_proof` | Trust-building sections | testimonials, logo_cloud, stats |
| `conversion` | Action-oriented sections | cta, pricing, contact |
| `media` | Media-focused sections | gallery, team |
| `utility` | Support sections | faq, blog_posts |

---

## Complete Section List

| Section | Type | Category |
|---------|------|----------|
| Hero | `hero` | headers |
| Hero with Image | `hero_image` | headers |
| Features | `features` | content |
| Services | `services` | content |
| Text | `text` | content |
| Text with Image | `text_image` | content |
| Testimonials | `testimonials` | social_proof |
| Logo Cloud | `logo_cloud` | social_proof |
| Stats | `stats` | social_proof |
| CTA | `cta` | conversion |
| Pricing | `pricing` | conversion |
| Contact | `contact` | conversion |
| Gallery | `gallery` | media |
| Team | `team` | media |
| FAQ | `faq` | utility |
| Blog Posts | `blog_posts` | utility |

---

## User-Created Sections

Users can save any arrangement of blocks as a reusable section:

### Saving a Section

1. Select blocks to include
2. Click "Save as Section" in toolbar
3. Provide name and category
4. Section saved to user's library

### Section Storage

```php
// ve_user_sections table
Schema::create('ve_user_sections', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('name');
    $table->string('category')->nullable();
    $table->json('blocks'); // Block structure
    $table->json('styles'); // Section styles
    $table->string('preview_image')->nullable();
    $table->boolean('is_shared')->default(false);
    $table->timestamps();
});
```

### Using Saved Sections

Saved sections appear in the Section Library alongside core sections.

---

## Section Registration

### Via Service Provider

```php
use ArtisanPackUI\VisualEditor\Facades\Sections;

public function boot()
{
    Sections::register(new MyCustomSection());
}
```

### Via Config

```php
// config/visual-editor.php

'sections' => [
    'custom_section' => [
        'name' => 'Custom Section',
        'description' => 'My custom section',
        'icon' => 'cube',
        'category' => 'custom',
        'view' => 'my-theme::sections.custom',
        'default_blocks' => [...],
        'style_schema' => [...],
    ],
],
```

---

## Section Rendering

```blade
{{-- resources/views/sections/hero.blade.php --}}

@php
    $styles = $section->styles ?? [];
    $bgClass = match($styles['background'] ?? 'white') {
        'white' => 'bg-white',
        'gray' => 'bg-gray-50',
        'primary' => 'bg-primary-600 text-white',
        'dark' => 'bg-gray-900 text-white',
        default => 'bg-white',
    };
    $paddingClass = match($styles['padding'] ?? 'large') {
        'small' => 'py-8',
        'medium' => 'py-16',
        'large' => 'py-24',
        default => 'py-24',
    };
    $heightStyle = $styles['min_height'] ?? 'auto';
    $widthClass = match($styles['content_width'] ?? 'medium') {
        'narrow' => 'max-w-2xl',
        'medium' => 'max-w-4xl',
        'wide' => 'max-w-6xl',
        default => 'max-w-4xl',
    };
    $alignClass = match($styles['vertical_alignment'] ?? 'center') {
        'top' => 'items-start',
        'center' => 'items-center',
        'bottom' => 'items-end',
        default => 'items-center',
    };
@endphp

<section
    class="{{ $bgClass }} {{ $paddingClass }} flex {{ $alignClass }}"
    style="min-height: {{ $heightStyle }};"
>
    <div class="container mx-auto px-4">
        <div class="{{ $widthClass }} mx-auto">
            @foreach($blocks as $block)
                {!! $block !!}
            @endforeach
        </div>
    </div>
</section>
```
