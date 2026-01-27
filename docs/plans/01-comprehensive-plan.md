# Visual Editor Package - Comprehensive Implementation Plan

**Package:** `artisanpack-ui/visual-editor`
**Version Target:** 1.0.0
**Created:** January 26, 2026
**Status:** Planning

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Core Philosophy](#2-core-philosophy)
3. [Architecture Overview](#3-architecture-overview)
4. [Editor Interface Design](#4-editor-interface-design)
5. [Block System](#5-block-system)
6. [Section System](#6-section-system)
7. [Template System](#7-template-system)
8. [Global Styles System](#8-global-styles-system)
9. [Permissions & Locking](#9-permissions--locking)
10. [Versioning & Revisions](#10-versioning--revisions)
11. [AI Assistant](#11-ai-assistant)
12. [Performance Budgets](#12-performance-budgets)
13. [A/B Testing](#13-ab-testing)
14. [SEO Integration](#14-seo-integration)
15. [Offline Support](#15-offline-support)
16. [Accessibility](#16-accessibility)
17. [Mobile Experience](#17-mobile-experience)
18. [Developer Extensibility](#18-developer-extensibility)
19. [Database Schema](#19-database-schema)
20. [Configuration Reference](#20-configuration-reference)
21. [Implementation Phases](#21-implementation-phases)

---

## 1. Executive Summary

The Visual Editor is a block-based content creation and site editing system for Laravel applications. It provides:

- **Content Editing**: Create pages, posts, and custom content types using a block-based editor
- **Full Site Editing**: Visually edit headers, footers, sidebars, and all template parts
- **Template Library**: Save, share, and import templates between sites
- **Global Styles**: Tailwind CSS integration with visual style customization
- **Developer Control**: Granular configuration to lock down capabilities for clients

### Key Differentiators from WordPress

| WordPress Pain Point | Our Solution |
|---------------------|--------------|
| Too many nested panels | Split sidebar approach - layers/inserters left, settings right |
| Confusing block discovery | Smart categorization, recent blocks, contextual suggestions |
| Unclear save/publish states | Explicit status bar, visual indicators, named versions |
| Poor mobile editing | Responsive editor with adaptive UI patterns |

---

## 2. Core Philosophy

### 2.1 Guiding Principles

1. **Sections Over Pixels**: Users work with pre-designed sections, not pixel-level control. This ensures professional results without breaking responsive design.

2. **Progressive Disclosure**: Simple by default, powerful when needed. Basic users see essential options; power users can access advanced features.

3. **Accessibility First**: Every feature must be keyboard navigable and screen reader compatible. No exceptions.

4. **Developer Guardrails**: Developers can lock any aspect of the editor to prevent clients from breaking designs.

5. **Performance Conscious**: Built-in budgets warn users when pages become too heavy.

### 2.2 Three Core Concepts (UX Principle #2)

Users only need to understand:

1. **Blocks** - Individual content elements (heading, text, image, button)
2. **Sections** - Pre-designed layouts containing blocks (hero, features, testimonials)
3. **Templates** - Page-level layouts that define where sections can go

---

## 3. Architecture Overview

### 3.1 Package Dependencies

```
visual-editor
├── artisanpack-ui/core (required)
├── artisanpack-ui/cms-framework (required - permissions, roles, settings, admin interface)
├── artisanpack-ui/media-library (required - media selection)
├── artisanpack-ui/livewire-ui-components (required - UI components, use FIRST before custom)
├── artisanpack-ui/livewire-drag-and-drop (required - reordering)
├── artisanpack-ui/hooks (required - extensibility, hooks use "ap.visualEditor." prefix)
├── artisanpack-ui/accessibility (required - contrast checking, WCAG compliance)
└── artisanpack-ui/seo (optional - SEO panel integration)
```

**Important Guidelines:**
- **cms-framework**: The visual editor deeply integrates with the CMS framework for:
  - **Permissions & Roles**: Uses `HasRolesAndPermissions` trait and `ap_register_permission()` / `ap_register_role()`
  - **Settings**: Uses `apGetSetting()` / `apUpdateSetting()` for admin-configurable settings
  - **Admin Interface**: Uses `apAddAdminPage()` / `apAddAdminSection()` for admin menu integration
- **livewire-ui-components**: Always use existing components from this package first. Only create custom components when no suitable component exists.
- **hooks**: All hook names use the `ap.visualEditor.` prefix with camelCase (e.g., `ap.visualEditor.contentSaving`, `ap.visualEditor.blocksRegister`).
- **accessibility**: This package is required (not optional) for contrast checking when users select colors.

### 3.2 CMS Framework Integration

The visual editor is tightly integrated with `artisanpack-ui/cms-framework` for core functionality:

#### Permissions & Roles

```php
// Permission check
if ($user->hasPermissionTo('visual_editor.templates.edit')) {
    // Allow template editing
}

// Role-based access
$user->hasRole('visual_editor_developer'); // Full access role
```

Permissions are registered automatically during package boot and can be managed via the CMS admin interface. See `07-permissions-locking.md` for the complete list.

#### Settings Management

```php
// Get admin-configurable setting
$maxImages = apGetSetting('visual_editor.performance.max_images', 20);

// Update setting (from admin interface)
apUpdateSetting('visual_editor.ai.enabled', true);
```

Settings allow administrators to customize visual editor behavior without code changes.

#### Admin Menu Integration

```php
// Register visual editor in admin menu
apAddAdminSection('visual-editor', __('Visual Editor'), 20);
apAddAdminPage(__('Editor'), 'visual-editor', 'visual-editor', [
    'capability' => 'visual_editor.access',
]);
```

#### Content Types

The visual editor works with content types defined in CMS framework, automatically inheriting:
- Content type definitions (pages, posts, custom types)
- Content relationships and taxonomy support
- Publishing workflows

### 3.3 High-Level Component Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        EditorShell                               │
│  ┌─────────────┬─────────────────────────┬─────────────────┐   │
│  │   Left      │                         │     Right       │   │
│  │  Sidebar    │     EditorCanvas        │    Sidebar      │   │
│  │             │                         │                 │   │
│  │ - Layers    │  ┌─────────────────┐   │ - Settings      │   │
│  │ - Inserter  │  │                 │   │ - Styles        │   │
│  │ - Patterns  │  │    IframeView   │   │ - Advanced      │   │
│  │             │  │                 │   │                 │   │
│  └─────────────┴──┴─────────────────┴───┴─────────────────┘   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                      EditorToolbar                       │   │
│  │  [Save] [Preview] [Publish] [Device] [Undo] [Redo]      │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

### 3.4 Directory Structure

See `02-directory-structure.md` for the complete package structure.

---

## 4. Editor Interface Design

### 4.1 Layout Structure

The editor uses a three-column layout with collapsible sidebars:

```
┌──────────────────────────────────────────────────────────────────┐
│ [☰] Logo    [Desktop ▼] [↶ Undo] [↷ Redo]    [Save ▼] [Publish] │
├────────────┬─────────────────────────────────┬───────────────────┤
│            │                                 │                   │
│  LAYERS    │                                 │   SETTINGS        │
│  ────────  │                                 │   ────────        │
│  ▼ Header  │                                 │   Block: Heading  │
│    Logo    │        CANVAS                   │                   │
│    Nav     │    (iframe preview)             │   Content         │
│  ▼ Main    │                                 │   ├─ Text: ...    │
│    Hero    │                                 │   └─ Level: H1    │
│    Features│                                 │                   │
│  ▼ Footer  │                                 │   Styles          │
│            │                                 │   ├─ Alignment    │
│  ────────  │                                 │   └─ Color        │
│  INSERTER  │                                 │                   │
│  [Search]  │                                 │   Advanced        │
│  Blocks    │                                 │   ├─ CSS Class    │
│  Sections  │                                 │   └─ HTML Anchor  │
│            │                                 │                   │
└────────────┴─────────────────────────────────┴───────────────────┘
```

### 4.2 Toolbar Components

The toolbar provides global actions and status:

- **Left**: Menu toggle, site logo/link to dashboard
- **Center**: Device toggle (desktop/tablet/mobile), undo/redo
- **Right**: Save dropdown (draft, version), publish button with status

### 4.3 Left Sidebar

**Layers Panel**: Hierarchical tree view of all content showing:
- Template parts (header, footer, sidebar)
- Sections within content area
- Blocks within sections
- Lock indicators for locked items
- Drag handles for reordering

**Block Inserter**: Searchable block library with:
- Recent blocks (last 6 used)
- Categorized blocks (text, media, layout, interactive, embeds, dynamic)
- Search with keyword matching
- Preview on hover

**Section Library**: Pre-designed sections with:
- Categories (hero, features, testimonials, CTA, etc.)
- User-saved sections
- Theme-provided sections

### 4.4 Right Sidebar

Context-aware panel that shows different tabs based on selection:

**When Block Selected**:
- Content tab: Block-specific content fields
- Styles tab: Visual styling options
- Advanced tab: CSS class, HTML anchor, custom attributes

**When Section Selected**:
- Layout tab: Column layout, spacing, width
- Styles tab: Background, padding, alignment
- Advanced tab: Visibility rules, CSS class

**When Nothing Selected**:
- Page settings: Title, slug, status
- SEO tab (if seo package installed): Meta, Open Graph
- Social tab: Social sharing preview

### 4.5 Canvas

The canvas renders content in an iframe for true WYSIWYG:

- Loads actual site styles
- Responsive to device toggle
- Click-to-select blocks
- Inline editing for text blocks
- Visual drag handles for reordering
- Insertion points between blocks/sections

### 4.6 Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Cmd+S` | Save |
| `Cmd+Z` | Undo |
| `Cmd+Shift+Z` | Redo |
| `Cmd+Shift+P` | Publish |
| `Cmd+K` | Command palette |
| `/` | Search blocks |
| `Cmd+[` | Toggle left sidebar |
| `Cmd+]` | Toggle right sidebar |
| `Backspace` | Delete selected block |
| `Cmd+D` | Duplicate selected |
| `Escape` | Deselect |

---

## 5. Block System

See `03-block-system.md` for detailed block specifications.

### 5.1 Block Categories

| Category | Blocks |
|----------|--------|
| **Text** | heading, paragraph, list, quote, code |
| **Media** | image, gallery, video, audio, file |
| **Interactive** | button, button_group, form, tabs, accordion |
| **Layout** | columns, group, spacer, divider |
| **Embeds** | map, social, html, shortcode |
| **Dynamic** | latest_posts, table_of_contents, global_content |

### 5.2 Block Types

All 20+ blocks included in initial release:

1. **heading** - H1-H6 with alignment and color
2. **paragraph** - Rich text with formatting
3. **list** - Bulleted/numbered with nesting
4. **quote** - Blockquote with citation
5. **code** - Syntax highlighted code
6. **image** - Single image with caption
7. **gallery** - Image grid with lightbox
8. **video** - YouTube, Vimeo, self-hosted
9. **audio** - Audio player
10. **file** - Download link
11. **button** - Single CTA button
12. **button_group** - Multiple buttons
13. **form** - Embedded form (forms package)
14. **tabs** - Tabbed content
15. **accordion** - Collapsible sections
16. **columns** - 2-4 column layouts
17. **group** - Block container
18. **spacer** - Vertical spacing
19. **divider** - Horizontal rule
20. **map** - Google Maps embed
21. **social** - Social embed (Twitter, Instagram, etc.)
22. **html** - Custom HTML (configurable restriction)
23. **shortcode** - Shortcode processing
24. **latest_posts** - Dynamic post list
25. **table_of_contents** - Auto-generated TOC
26. **global_content** - Business info insertion

### 5.3 Block Extensibility

Developers can add blocks via:

1. **Config-based**: Define in `config/visual-editor.php`
2. **Blade component**: Create view + config
3. **Livewire component**: Full interactive blocks

---

## 6. Section System

See `04-section-system.md` for detailed section specifications.

### 6.1 Section Categories

Sections are organized into granular, purpose-specific categories:

| Category | Description |
|----------|-------------|
| `hero` | Hero/banner sections |
| `features` | Feature highlight sections |
| `services` | Service showcase sections |
| `testimonials` | Customer testimonial sections |
| `team` | Team member sections |
| `gallery` | Image gallery sections |
| `cta` | Call-to-action sections |
| `contact` | Contact form sections |
| `faq` | FAQ sections |
| `pricing` | Pricing table sections |
| `stats` | Statistics sections |
| `logos` | Logo cloud/partner sections |
| `blog` | Blog/post sections |
| `text` | Text content sections |
| `custom` | User-created sections |

### 6.2 Core Sections (Default Prefix)

All default sections are prefixed with "Default" to distinguish them from developer or user-created sections:

| Section | Type | Category |
|---------|------|----------|
| Default Hero | `default_hero` | hero |
| Default Hero with Image | `default_hero_image` | hero |
| Default Features | `default_features` | features |
| Default Services | `default_services` | services |
| Default Testimonials | `default_testimonials` | testimonials |
| Default Team | `default_team` | team |
| Default Gallery | `default_gallery` | gallery |
| Default CTA | `default_cta` | cta |
| Default Contact | `default_contact` | contact |
| Default FAQ | `default_faq` | faq |
| Default Pricing | `default_pricing` | pricing |
| Default Stats | `default_stats` | stats |
| Default Logo Cloud | `default_logo_cloud` | logos |
| Default Blog Posts | `default_blog_posts` | blog |
| Default Text | `default_text` | text |
| Default Text with Image | `default_text_image` | text |

### 6.3 Section Registration & Unregistration

Developers can register custom sections and unregister default sections:

```php
use ArtisanPackUI\VisualEditor\Facades\Sections;

// Register a custom section
Sections::register(new MyCustomHeroSection());

// Unregister a default section
Sections::unregister('default_hero_image');

// Unregister all sections in a category
Sections::unregisterCategory('pricing');
```

### 6.4 User-Created Sections

Users can save any arrangement of blocks as a reusable section:

1. Select blocks to include
2. Click "Save as Section"
3. Name and categorize (in `custom` category by default)
4. Available in Section Library

---

## 7. Template System

See `05-template-system.md` for detailed template specifications.

### 7.1 Template Hierarchy

Full WordPress-style template hierarchy support:

- `index` - Fallback for everything
- `single` - Single post/content
- `single-{type}` - Single post of specific type
- `page` - Static pages
- `page-{slug}` - Specific page by slug
- `archive` - Archive listings
- `archive-{type}` - Archive for content type
- `category` - Category archives
- `tag` - Tag archives
- `author` - Author archives
- `search` - Search results
- `404` - Not found page
- `home` - Homepage (if set to posts)
- `front-page` - Static front page

### 7.2 Template Parts

Editable template parts:

- **Header**: Site-wide header
- **Footer**: Site-wide footer
- **Sidebar**: Optional sidebar content
- **Comments**: Comment display template

### 7.3 Template Library

- Save templates for reuse
- Export/import templates
- Future: Community template sharing

---

## 8. Global Styles System

See `06-global-styles.md` for detailed styles specifications.

### 8.1 Tailwind Integration

The editor integrates with Tailwind CSS while handling JIT compilation:

1. **Design Tokens**: Define colors, fonts, spacing as CSS custom properties
2. **Tailwind Config Sync**: Token values sync to `tailwind.config.js`
3. **JIT Compilation**: Build process generates necessary classes
4. **Runtime Fallback**: CSS custom properties for dynamic values

### 8.2 Style Categories

| Category | Properties |
|----------|------------|
| **Colors** | Primary, secondary, accent, neutral, semantic (success, warning, error) |
| **Typography** | Font families, sizes, weights, line heights |
| **Spacing** | Padding/margin scale |
| **Layout** | Container width, breakpoints |
| **Components** | Button styles, form inputs, cards |

### 8.3 Theme Inheritance

User customizations layer on top of theme defaults:
- Theme provides base design tokens
- User overrides stored separately
- Theme updates don't overwrite user changes

---

## 9. Permissions & Locking

See `07-permissions-locking.md` for detailed specifications.

### 9.1 CMS Framework Permissions

Permissions are managed through `artisanpack-ui/cms-framework`:

```php
// Check permissions using CMS framework
if ($user->hasPermissionTo('visual_editor.templates.edit')) {
    // Allow template editing
}

// Permission categories:
// - visual_editor.access           Core editor access
// - visual_editor.manage           Admin settings
// - visual_editor.templates.*      Template operations
// - visual_editor.styles.*         Global styles
// - visual_editor.sections.*       Section operations
// - visual_editor.blocks.*         Block operations
// - visual_editor.content.*        Publishing and locking
```

**Default Roles (registered automatically):**
- `visual_editor_content` - Content Editor (limited access)
- `visual_editor_site` - Site Editor (full editing, no code)
- `visual_editor_developer` - Editor Developer (full access)

Permissions and roles can be managed via the CMS admin interface.

### 9.2 Admin-Configurable Restrictions

Block and section restrictions are stored as CMS Framework settings:

```php
// Get restrictions (admin can configure these)
$disallowedBlocks = apGetSetting('visual_editor.disallowed_blocks', []);
$allowedSections = apGetSetting('visual_editor.allowed_sections');
```

### 9.3 UI-Based Locking

Users with `visual_editor.content.lock` permission can lock:

- **Template locking**: Lock entire template structure
- **Section locking**: Lock section, allow content editing
- **Block locking**: Lock specific blocks
- **Content-only mode**: Allow only text/image changes

### 9.4 Lock Levels

1. **Structure locked**: Can't add/remove/reorder, can edit content
2. **Fully locked**: No changes allowed
3. **Move locked**: Can edit, can't move
4. **Delete locked**: Can edit/move, can't delete

---

## 10. Versioning & Revisions

### 10.1 Auto-Save

- Save every 60 seconds during editing
- Save on significant changes
- Stored as revisions

### 10.2 Named Versions

Users can create named versions:
- "Before holiday update"
- "Client v1 approval"
- Easily restore any version

### 10.3 Revision History

- View all revisions
- Compare versions
- Restore previous version
- Auto-cleanup old revisions (configurable)

### 10.4 Block Migrations

When block schema changes:
1. Block version incremented
2. Migration method handles old content
3. Dual render support during transition

---

## 11. AI Assistant

See `08-additional-features.md` for detailed specifications.

### 11.1 Features (Off by default)

When enabled:
- **Content suggestions**: Improve headlines, expand text
- **Alt text generation**: Auto-suggest image descriptions
- **Layout suggestions**: Recommend sections based on content
- **SEO suggestions**: Meta descriptions, keyword optimization

### 11.2 CMS Framework Settings Integration

AI settings are stored using the CMS Framework settings system, allowing administrators to configure providers and API keys through the admin interface:

```php
// Check if AI is enabled
$aiEnabled = apGetSetting('visual_editor.ai.enabled', false);

// Get configured provider
$provider = apGetSetting('visual_editor.ai.provider', 'openai');

// API keys are encrypted in the database
$apiKey = decrypt(apGetSetting('visual_editor.ai.openai.api_key', ''));
```

**Admin-Configurable Settings:**
- Provider selection (OpenAI, Anthropic, or custom providers)
- API keys (encrypted in database)
- Model selection per provider
- Feature toggles (content suggestions, alt text, layout, SEO)
- Rate limits (requests per minute/day)

Users with `visual_editor.manage` permission can configure AI settings through the admin interface at `/admin/visual-editor-ai`.

### 11.3 Extensible AI Providers

Developers can register custom AI providers (e.g., Google Gemini, Cohere, local models) using the hooks system:

```php
// Register a custom provider
addFilter('ap.visualEditor.aiProvidersRegister', function (array $providers) {
    $providers['gemini'] = new GeminiProvider();
    return $providers;
});
```

Custom providers implement `AIProviderInterface` and automatically:
- Appear in the admin provider selection dropdown
- Have their settings rendered in the admin UI
- Register their settings with CMS Framework

See `08-additional-features.md` for the complete interface and example implementation.

---

## 12. Performance Budgets

### 12.1 Page Analysis

Track per page:
- Total page weight (KB)
- Number of images
- Image total size
- Number of scripts
- Estimated load time

### 12.2 Warnings

Show warnings when:
- Page exceeds weight threshold (default: 2MB)
- Too many images (default: 20)
- Unoptimized images detected
- Too many third-party embeds

### 12.3 Recommendations

Suggest improvements:
- Compress images
- Lazy load below-fold images
- Reduce section count
- Optimize video embeds

---

## 13. A/B Testing

### 13.1 Experiment Creation

- Create content variants
- Set traffic split percentages
- Define conversion goals
- Set experiment duration

### 13.2 Variant Types

- Headline variants
- Section variants
- Full page variants
- CTA button variants

### 13.3 Results Tracking

- View impressions
- Track conversions
- Statistical significance
- Winner recommendation

---

## 14. SEO Integration

When `artisanpack-ui/seo` package installed:

### 14.1 SEO Panel

- Meta title with character count
- Meta description with preview
- Focus keyword analysis
- Open Graph preview
- Twitter Card preview

### 14.2 Content Analysis

- Keyword density
- Heading structure
- Internal link suggestions
- Image alt text audit

---

## 15. Offline Support

### 15.1 Auto-Save Queue

When offline:
- Changes saved to IndexedDB
- Visual indicator of offline status
- Queue syncs when connection returns

### 15.2 Conflict Resolution

If server has newer version:
- Show conflict dialog
- Option to merge or overwrite
- Never lose user's work

---

## 16. Accessibility

The `artisanpack-ui/accessibility` package is a **required dependency** and plays a central role in the visual editor.

### 16.1 Editor Accessibility

- Full keyboard navigation
- Screen reader announcements
- Focus management
- High contrast mode support
- Reduced motion support

### 16.2 Content Accessibility

- Alt text enforcement (warning)
- Heading hierarchy validation
- Color contrast checking (using `artisanpack-ui/accessibility`)
- Link text quality check

### 16.3 Color Contrast Integration

The accessibility package's contrast checking functions are used throughout the editor:

```php
use ArtisanPackUI\Accessibility\Facades\A11y;

// When user selects background color, automatically check text contrast
$backgroundColor = '#3b82f6';
$textColor = '#ffffff';

// Check if contrast meets WCAG 2.0 AA standard (4.5:1 ratio)
$hasGoodContrast = A11y::a11yCheckContrastColor($backgroundColor, $textColor);

// Suggest accessible text color for a background
$suggestedTextColor = A11y::a11yGetContrastColor($backgroundColor);

// Generate tinted accessible text color (maintains visual harmony)
$tintedTextColor = generateAccessibleTextColor($backgroundColor, true);
```

**Automatic Contrast Checking:**
- When setting section background colors, automatically suggest accessible text colors
- When setting button colors, verify text contrast meets WCAG standards
- Show warnings in the editor when color combinations fail contrast checks
- Provide one-click fix suggestions using the accessibility package

### 16.4 Accessibility Scanner Modal

A dedicated accessibility scanning modal/panel to audit content for issues:

```php
class AccessibilityScanner
{
    public function scan(Content $content): array
    {
        $issues = [];

        // Image alt text audit
        $issues = array_merge($issues, $this->scanAltText($content));

        // Heading hierarchy check
        $issues = array_merge($issues, $this->scanHeadingHierarchy($content));

        // Color contrast check (for all color combinations)
        $issues = array_merge($issues, $this->scanColorContrast($content));

        // Link text quality
        $issues = array_merge($issues, $this->scanLinkText($content));

        // Empty buttons
        $issues = array_merge($issues, $this->scanEmptyButtons($content));

        return $issues;
    }

    protected function scanColorContrast(Content $content): array
    {
        $issues = [];

        foreach ($content->getSectionsWithColors() as $section) {
            $bgColor = $section['styles']['background_color'] ?? null;
            $textColor = $section['styles']['text_color'] ?? null;

            if ($bgColor && $textColor) {
                if (!a11yCheckContrastColor($bgColor, $textColor)) {
                    $issues[] = [
                        'type' => 'contrast',
                        'severity' => 'error',
                        'message' => __('Text color does not have sufficient contrast with background'),
                        'section_id' => $section['id'],
                        'suggestion' => a11yGetContrastColor($bgColor),
                    ];
                }
            }
        }

        return $issues;
    }
}
```

**Scanner Features:**
- Scan button in toolbar opens accessibility audit modal
- Lists all accessibility issues with severity levels (error, warning, info)
- One-click navigation to problematic blocks/sections
- One-click fix for issues that can be auto-resolved (e.g., contrast)
- Export report as PDF or CSV
- Hook for adding custom accessibility checks: `ap.visualEditor.accessibilityCheck`

### 16.5 Accessibility Settings Panel

Dedicated accessibility panel in right sidebar when editing colors:

```blade
{{-- Shows when user is editing colors --}}
<x-artisanpack-card>
    <x-slot:header>
        <h3>{{ __('Accessibility') }}</h3>
    </x-slot:header>

    @if($contrastRatio >= 4.5)
        <x-artisanpack-alert type="success">
            {{ __('WCAG AA: Contrast ratio :ratio:1', ['ratio' => number_format($contrastRatio, 1)]) }}
        </x-artisanpack-alert>
    @else
        <x-artisanpack-alert type="error">
            {{ __('Insufficient contrast: :ratio:1 (minimum 4.5:1 required)', ['ratio' => number_format($contrastRatio, 1)]) }}
        </x-artisanpack-alert>
        <x-artisanpack-button wire:click="applySuggestedColor" size="sm">
            {{ __('Apply suggested color') }}
        </x-artisanpack-button>
    @endif
</x-artisanpack-card>
```

---

## 17. Mobile Experience

### 17.1 Responsive Editor

Same editor adapts to mobile:
- Sidebars become bottom sheets
- Touch-friendly targets
- Swipe gestures for navigation
- Simplified toolbar

### 17.2 Mobile-Specific UI

- Bottom navigation bar
- Sheet-based panels
- Touch-optimized drag and drop
- Larger touch targets

---

## 18. Developer Extensibility

### 18.1 UI Component Usage

**Critical Guideline:** Always use components from `artisanpack-ui/livewire-ui-components` first. Only create custom components when no suitable component exists.

```blade
{{-- CORRECT: Use existing ArtisanPack UI components --}}
<x-artisanpack-button wire:click="save" color="primary">
    {{ __('Save') }}
</x-artisanpack-button>

<x-artisanpack-input wire:model="title" label="{{ __('Title') }}" />

<x-artisanpack-card>
    <x-slot:header>{{ __('Settings') }}</x-slot:header>
    {{-- content --}}
</x-artisanpack-card>

<x-artisanpack-modal wire:model="showModal" title="{{ __('Confirm') }}">
    {{-- content --}}
</x-artisanpack-modal>

<x-artisanpack-alert type="warning">
    {{ __('Contrast ratio is below WCAG AA standard') }}
</x-artisanpack-alert>

{{-- INCORRECT: Don't create custom when component exists --}}
<button class="btn btn-primary">Save</button>
<div class="modal">...</div>
```

Available component categories from `livewire-ui-components`:
- **Form**: input, button, checkbox, select, datepicker, toggle, etc.
- **Layout**: card, modal, tabs, accordion, drawer, dropdown
- **Navigation**: menu, breadcrumbs, pagination, steps
- **Data Display**: table, avatar, badge, progress, stat
- **Feedback**: alert, toast, loading, skeleton
- **Utility**: icon, tooltip, clipboard

### 18.2 Adding Custom Blocks

```php
// Via service provider
Blocks::register(new MyCustomBlock());

// Via config
'blocks' => [
    'custom_block' => [
        'name' => 'Custom Block',
        'view' => 'my-theme::blocks.custom',
        'content_schema' => [...],
    ],
],
```

### 18.3 Adding Custom Sections

```php
Sections::register(new MyCustomSection());

// Unregister a default section
Sections::unregister('default_hero_image');
```

### 18.4 Hooks & Filters

All hooks use the `ap.visualEditor.` prefix with camelCase naming (via `artisanpack-ui/hooks`):

```php
// Add toolbar button
addFilter('ap.visualEditor.toolbarItems', function (array $items) {
    $items[] = ['type' => 'button', 'label' => 'My Action'];
    return $items;
});

// Modify block output
addFilter('ap.visualEditor.blockRender', function (string $html, array $block) {
    return $html;
});

// Before content save
addAction('ap.visualEditor.contentSaving', function ($content) {
    // Validate, transform, etc.
});

// Block/section registration
addFilter('ap.visualEditor.blocksRegister', function (array $blocks) {
    $blocks['my_block'] = new MyBlock();
    return $blocks;
});

addFilter('ap.visualEditor.sectionsRegister', function (array $sections) {
    $sections['my_section'] = new MySection();
    return $sections;
});

// After content published
addAction('ap.visualEditor.contentPublished', function ($content) {
    // Notify, cache invalidation, etc.
});

// Style/color changes
addFilter('ap.visualEditor.colorSelected', function (string $color, string $context) {
    // Validate contrast, etc.
    return $color;
});
```

**Available Hooks:**

| Hook | Type | Description |
|------|------|-------------|
| `ap.visualEditor.blocksRegister` | Filter | Register/unregister blocks |
| `ap.visualEditor.blocksInit` | Action | After blocks initialized |
| `ap.visualEditor.sectionsRegister` | Filter | Register/unregister sections |
| `ap.visualEditor.sectionsInit` | Action | After sections initialized |
| `ap.visualEditor.contentSaving` | Action | Before content is saved |
| `ap.visualEditor.contentSaved` | Action | After content is saved |
| `ap.visualEditor.contentPublished` | Action | After content is published |
| `ap.visualEditor.blockRender` | Filter | Modify block HTML output |
| `ap.visualEditor.sectionRender` | Filter | Modify section HTML output |
| `ap.visualEditor.toolbarItems` | Filter | Modify toolbar items |
| `ap.visualEditor.sidebarPanels` | Filter | Modify sidebar panels |
| `ap.visualEditor.colorSelected` | Filter | When a color is selected (for contrast checking) |
| `ap.visualEditor.accessibilityCheck` | Filter | Add custom accessibility checks |
| `ap.visualEditor.aiProvidersRegister` | Filter | Register custom AI providers |
| `ap.visualEditor.aiProviderRegistered` | Action | After an AI provider is registered |
| `ap.visualEditor.aiProviderUnregistered` | Action | After an AI provider is unregistered |

---

## 19. Database Schema

See `09-database-schema.md` for complete schema.

### 19.1 Core Tables

- `ve_contents` - Main content storage
- `ve_content_revisions` - Revision history
- `ve_templates` - Template definitions
- `ve_template_parts` - Reusable template parts
- `ve_sections` - User-created sections
- `ve_global_styles` - Style customizations
- `ve_experiments` - A/B test definitions
- `ve_experiment_variants` - Test variants

---

## 20. Configuration Reference

See `10-configuration.md` for complete config options.

---

## 21. Implementation Phases

### Phase 1: Core Editor (Weeks 1-8)

- [ ] Editor shell and layout
- [ ] Canvas with iframe preview
- [ ] Basic block system (10 core blocks)
- [ ] Section system
- [ ] Save/publish flow
- [ ] Undo/redo

### Phase 2: Full Block Library (Weeks 9-12)

- [ ] Complete all 26 blocks
- [ ] Block settings panels
- [ ] Inline editing
- [ ] Drag and drop

### Phase 3: Template System (Weeks 13-16)

- [ ] Template hierarchy
- [ ] Template parts editing
- [ ] Template library
- [ ] Theme integration

### Phase 4: Global Styles (Weeks 17-20)

- [ ] Style editor UI
- [ ] Tailwind integration
- [ ] Design token system
- [ ] Theme inheritance

### Phase 5: Advanced Features (Weeks 21-26)

- [ ] Permissions & locking
- [ ] Versioning system
- [ ] AI assistant
- [ ] Performance budgets
- [ ] A/B testing
- [ ] SEO integration

### Phase 6: Polish (Weeks 27-30)

- [ ] Accessibility audit
- [ ] Mobile optimization
- [ ] Offline support
- [ ] Documentation
- [ ] Testing suite

---

*See additional documents for detailed specifications of each system.*
