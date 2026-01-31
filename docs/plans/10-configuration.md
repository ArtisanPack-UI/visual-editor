# Visual Editor - Configuration Reference

> **Phase:** 1 (Core Editor) — High Priority (Setup)
>
> Package configuration must be created alongside database migrations before feature development.

---

## Configuration Architecture

The visual editor uses a hybrid configuration approach that integrates with `artisanpack-ui/cms-framework`:

1. **Static Config** (`config/visual-editor.php`): Non-admin-configurable settings like routes and middleware
2. **CMS Framework Settings** (`apGetSetting()` / `apUpdateSetting()`): Admin-configurable settings stored in database
3. **CMS Framework Permissions** (`hasPermissionTo()`): Role-based access control via database

This allows developers to set defaults while giving administrators the ability to customize settings through the admin interface.

---

## Settings Registration

The visual editor registers its settings during service provider boot:

```php
// VisualEditorServiceProvider.php

protected function registerSettings(): void
{
    // Block/section restrictions
    apRegisterSetting('visual_editor.allowed_blocks', null, fn($v) => $v, 'json');
    apRegisterSetting('visual_editor.disallowed_blocks', [], fn($v) => $v, 'json');
    apRegisterSetting('visual_editor.allowed_sections', null, fn($v) => $v, 'json');
    apRegisterSetting('visual_editor.disallowed_sections', [], fn($v) => $v, 'json');

    // Feature toggles
    apRegisterSetting('visual_editor.locking.enabled', true, fn($v) => (bool) $v, 'boolean');
    apRegisterSetting('visual_editor.ai.enabled', false, fn($v) => (bool) $v, 'boolean');
    apRegisterSetting('visual_editor.ab_testing.enabled', false, fn($v) => (bool) $v, 'boolean');

    // Performance budgets
    apRegisterSetting('visual_editor.performance.max_weight', 2097152, fn($v) => (int) $v, 'integer');
    apRegisterSetting('visual_editor.performance.max_images', 20, fn($v) => (int) $v, 'integer');

    // Accessibility settings
    apRegisterSetting('visual_editor.accessibility.require_alt_text', true, fn($v) => (bool) $v, 'boolean');
    apRegisterSetting('visual_editor.accessibility.check_contrast', true, fn($v) => (bool) $v, 'boolean');

    // Versioning
    apRegisterSetting('visual_editor.versioning.autosave_interval', 60, fn($v) => (int) $v, 'integer');
    apRegisterSetting('visual_editor.versioning.max_autosave_revisions', 50, fn($v) => (int) $v, 'integer');
}
```

---

## Complete Configuration File

```php
<?php

// config/visual-editor.php
// This file contains STATIC configuration only.
// Admin-configurable settings use CMS Framework's apGetSetting()/apUpdateSetting().

return [

    /*
    |--------------------------------------------------------------------------
    | General Settings (Static - not admin-configurable)
    |--------------------------------------------------------------------------
    */

    'enabled' => env('VISUAL_EDITOR_ENABLED', true),

    // Route prefix for editor
    'route_prefix' => 'admin/editor',

    // Middleware for editor routes
    'middleware' => ['web', 'auth', 'verified'],

    /*
    |--------------------------------------------------------------------------
    | Content Types
    |--------------------------------------------------------------------------
    |
    | Content types are managed by cms-framework package.
    | This setting enables/disables visual editor for each type.
    |
    */

    'content_types' => [
        'page' => true,
        'post' => true,
        // Custom types from cms-framework automatically inherit
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocks
    |--------------------------------------------------------------------------
    */

    'blocks' => [
        // Core blocks (enabled by default)
        'core' => [
            'heading' => true,
            'paragraph' => true,
            'image' => true,
            'list' => true,
            'quote' => true,
            'code' => true,
            'button' => true,
            'button_group' => true,
            'video' => true,
            'audio' => true,
            'file' => true,
            'gallery' => true,
            'columns' => true,
            'group' => true,
            'spacer' => true,
            'divider' => true,
            'tabs' => true,
            'accordion' => true,
            'form' => true,
            'map' => true,
            'social_embed' => true,
            'html' => true,
            'shortcode' => true,
            'latest_posts' => true,
            'table_of_contents' => true,
            'global_content' => true,
        ],

        // Custom blocks defined in config
        'custom' => [
            // 'my_block' => [
            //     'name' => 'My Block',
            //     'view' => 'my-theme::blocks.my-block',
            //     'content_schema' => [...],
            //     'style_schema' => [...],
            // ],
        ],

        // Block categories
        'categories' => [
            'text' => ['icon' => 'document-text', 'label' => 'Text'],
            'media' => ['icon' => 'photo', 'label' => 'Media'],
            'layout' => ['icon' => 'view-columns', 'label' => 'Layout'],
            'interactive' => ['icon' => 'cursor-arrow-rays', 'label' => 'Interactive'],
            'embeds' => ['icon' => 'code-bracket', 'label' => 'Embeds'],
            'dynamic' => ['icon' => 'arrow-path', 'label' => 'Dynamic'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sections
    |--------------------------------------------------------------------------
    |
    | Sections are organized into granular, purpose-specific categories.
    | All default sections use the "default_" prefix to distinguish them
    | from developer or user-created sections.
    |
    */

    'sections' => [
        // Core sections (all use "default_" prefix)
        'core' => [
            'default_hero' => true,
            'default_hero_image' => true,
            'default_features' => true,
            'default_services' => true,
            'default_testimonials' => true,
            'default_team' => true,
            'default_gallery' => true,
            'default_cta' => true,
            'default_contact' => true,
            'default_faq' => true,
            'default_pricing' => true,
            'default_stats' => true,
            'default_logo_cloud' => true,
            'default_blog_posts' => true,
            'default_text' => true,
            'default_text_image' => true,
        ],

        // Explicitly disabled sections (alternative to setting false above)
        'disabled' => [
            // 'default_pricing',
            // 'default_stats',
        ],

        // Section categories (granular, purpose-specific)
        'categories' => [
            'hero' => ['icon' => 'rectangle-group', 'label' => 'Hero'],
            'features' => ['icon' => 'squares-2x2', 'label' => 'Features'],
            'services' => ['icon' => 'briefcase', 'label' => 'Services'],
            'testimonials' => ['icon' => 'chat-bubble-left-right', 'label' => 'Testimonials'],
            'team' => ['icon' => 'user-group', 'label' => 'Team'],
            'gallery' => ['icon' => 'photo', 'label' => 'Gallery'],
            'cta' => ['icon' => 'megaphone', 'label' => 'Call to Action'],
            'contact' => ['icon' => 'envelope', 'label' => 'Contact'],
            'faq' => ['icon' => 'question-mark-circle', 'label' => 'FAQ'],
            'pricing' => ['icon' => 'currency-dollar', 'label' => 'Pricing'],
            'stats' => ['icon' => 'chart-bar', 'label' => 'Statistics'],
            'logos' => ['icon' => 'building-office', 'label' => 'Logo Cloud'],
            'blog' => ['icon' => 'newspaper', 'label' => 'Blog'],
            'text' => ['icon' => 'document-text', 'label' => 'Text'],
            'custom' => ['icon' => 'cube', 'label' => 'Custom'],
        ],

        // Allow users to save custom sections
        'allow_user_sections' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Templates
    |--------------------------------------------------------------------------
    */

    'templates' => [
        // Available template parts
        'parts' => ['header', 'footer', 'sidebar'],

        // Default templates
        'default_page_template' => 'page',
        'default_post_template' => 'single',

        // Template editing
        'allow_custom_templates' => true,
        'allow_template_editing' => true,

        // Locked templates (cannot be edited)
        'locked_templates' => [],

        // Locked template parts
        'locked_parts' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Styles
    |--------------------------------------------------------------------------
    */

    'styles' => [
        'enabled' => true,

        // Allow custom color creation
        'allow_custom_colors' => true,

        // Available Google Fonts
        'google_fonts' => [
            'Inter',
            'Roboto',
            'Open Sans',
            'Lato',
            'Poppins',
            'Montserrat',
            'Playfair Display',
            'Merriweather',
            'Source Sans Pro',
            'Nunito',
        ],

        // Preset color palettes
        'color_presets' => [
            'blue' => '#3b82f6',
            'indigo' => '#6366f1',
            'purple' => '#8b5cf6',
            'pink' => '#ec4899',
            'red' => '#ef4444',
            'orange' => '#f97316',
            'yellow' => '#eab308',
            'green' => '#22c55e',
            'teal' => '#14b8a6',
            'cyan' => '#06b6d4',
        ],

        // Locked style categories (cannot be edited by users)
        'locked_categories' => [],

        // Auto-compile styles on save
        'auto_compile' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions (CMS Framework Integration)
    |--------------------------------------------------------------------------
    |
    | Permissions are managed via artisanpack-ui/cms-framework.
    | The visual editor registers permissions and roles during boot.
    | See 07-permissions-locking.md for full permission documentation.
    |
    | Permissions are checked using: $user->hasPermissionTo('visual_editor.access')
    | Roles can be managed via the CMS admin interface.
    |
    */

    // Block/section restrictions are stored in CMS Framework settings
    // and can be managed via admin interface. These are the defaults.
    'defaults' => [
        // Block restrictions (stored in apGetSetting('visual_editor.allowed_blocks'))
        'allowed_blocks' => null, // null = all blocks allowed
        'disallowed_blocks' => [], // Blocks to hide from inserter

        // Section restrictions (stored in apGetSetting('visual_editor.allowed_sections'))
        'allowed_sections' => null,
        'disallowed_sections' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Registered Permissions (Reference)
    |--------------------------------------------------------------------------
    |
    | These permissions are registered via ap_register_permission() during boot.
    | They are listed here for reference only - do not modify.
    |
    | Core Access:
    |   - visual_editor.access          Access Visual Editor
    |   - visual_editor.manage          Manage Visual Editor Settings
    |
    | Templates:
    |   - visual_editor.templates.view      View Templates
    |   - visual_editor.templates.create    Create Templates
    |   - visual_editor.templates.edit      Edit Templates
    |   - visual_editor.templates.delete    Delete Templates
    |   - visual_editor.template_parts.edit Edit Template Parts
    |
    | Global Styles:
    |   - visual_editor.styles.view     View Global Styles
    |   - visual_editor.styles.edit     Edit Global Styles
    |   - visual_editor.styles.colors   Edit Colors
    |   - visual_editor.styles.typography Edit Typography
    |   - visual_editor.styles.spacing  Edit Spacing
    |
    | Advanced Features:
    |   - visual_editor.custom_css      Add Custom CSS
    |   - visual_editor.custom_html     Add Custom HTML
    |   - visual_editor.custom_js       Add Custom JavaScript
    |   - visual_editor.view_code       View Generated Code
    |
    | Sections:
    |   - visual_editor.sections.create     Create Sections
    |   - visual_editor.sections.delete     Delete Sections
    |   - visual_editor.sections.reorder    Reorder Sections
    |   - visual_editor.sections.save_patterns Save Section Patterns
    |
    | Blocks:
    |   - visual_editor.blocks.add      Add Blocks
    |   - visual_editor.blocks.delete   Delete Blocks
    |   - visual_editor.blocks.reorder  Reorder Blocks
    |
    | Content Locking:
    |   - visual_editor.content.lock    Lock Content
    |   - visual_editor.content.unlock  Unlock Content
    |
    | Publishing:
    |   - visual_editor.content.publish     Publish Content
    |   - visual_editor.content.schedule    Schedule Content
    |   - visual_editor.content.unpublish   Unpublish Content
    |
    | Versioning:
    |   - visual_editor.revisions.view      View Revisions
    |   - visual_editor.revisions.restore   Restore Revisions
    |   - visual_editor.versions.create     Create Named Versions
    |
    | Optional Features:
    |   - visual_editor.ai.use              Use AI Assistant
    |   - visual_editor.experiments.create  Create A/B Experiments
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Roles (Reference)
    |--------------------------------------------------------------------------
    |
    | These roles are registered via ap_register_role() during boot.
    | They can be customized via the CMS admin interface.
    |
    | - visual_editor_content   Content Editor (limited access)
    | - visual_editor_site      Site Editor (full editing, no code)
    | - visual_editor_developer Editor Developer (full access)
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Content Locking
    |--------------------------------------------------------------------------
    */

    'locking' => [
        'enabled' => true,

        // Available lock levels
        'available_levels' => ['content', 'move', 'delete', 'full'],

        // Require reason when locking
        'require_reason' => false,

        // Show lock indicators in editor
        'show_indicators' => true,

        // Allow bulk locking
        'allow_bulk_lock' => true,

        // Auto-lock published content
        'auto_lock_published' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Versioning
    |--------------------------------------------------------------------------
    */

    'versioning' => [
        'enabled' => true,

        // Auto-save interval (seconds)
        'autosave_interval' => 60,

        // Maximum auto-save revisions to keep
        'max_autosave_revisions' => 50,

        // Keep named versions indefinitely
        'keep_named_versions' => true,

        // Days to keep auto-save revisions
        'autosave_retention_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Assistant (CMS Framework Settings)
    |--------------------------------------------------------------------------
    |
    | AI settings are stored in the CMS Framework settings system to allow
    | administrators to configure providers and API keys through the admin
    | interface. API keys are encrypted in the database for security.
    |
    | Settings are managed via:
    |   - apGetSetting('visual_editor.ai.*')
    |   - apUpdateSetting('visual_editor.ai.*', $value)
    |
    | The following settings are registered during package boot:
    |
    | Core Settings:
    |   - visual_editor.ai.enabled                          (boolean)
    |   - visual_editor.ai.provider                         (string: openai, anthropic, or custom)
    |
    | Default Provider Settings:
    |   - visual_editor.ai.openai.api_key                   (string, encrypted)
    |   - visual_editor.ai.openai.model                     (string)
    |   - visual_editor.ai.anthropic.api_key                (string, encrypted)
    |   - visual_editor.ai.anthropic.model                  (string)
    |
    | Custom Provider Settings:
    |   - visual_editor.ai.{provider}.{key}                 (varies by provider)
    |
    | Feature Toggles:
    |   - visual_editor.ai.features.content_suggestions     (boolean)
    |   - visual_editor.ai.features.alt_text                (boolean)
    |   - visual_editor.ai.features.layout_suggestions      (boolean)
    |   - visual_editor.ai.features.seo_suggestions         (boolean)
    |
    | Rate Limits:
    |   - visual_editor.ai.rate_limits.requests_per_minute  (integer)
    |   - visual_editor.ai.rate_limits.requests_per_day     (integer)
    |
    | Extensibility:
    | Developers can register custom AI providers (e.g., Google Gemini, Cohere)
    | via the `ap.visualEditor.aiProvidersRegister` hook. Custom providers
    | automatically have their settings registered based on their getSettingsSchema()
    | method. See 08-additional-features.md for full implementation documentation.
    |
    */

    // Default values for AI settings (used when registering settings)
    'ai_defaults' => [
        'enabled' => false,
        'provider' => 'openai',
        'openai' => [
            'model' => 'gpt-4',
        ],
        'anthropic' => [
            'model' => 'claude-3-sonnet',
        ],
        'features' => [
            'content_suggestions' => true,
            'alt_text' => true,
            'layout_suggestions' => false,
            'seo_suggestions' => false,
        ],
        'rate_limits' => [
            'requests_per_minute' => 10,
            'requests_per_day' => 100,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    */

    'performance' => [
        'enabled' => true,

        'budget' => [
            'max_weight' => 2097152, // 2MB in bytes
            'max_images' => 20,
            'max_embeds' => 5,
            'max_scripts' => 10,
        ],

        // Show warnings in editor
        'show_warnings' => true,

        // Block publishing when over budget
        'block_publish_on_warning' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | A/B Testing
    |--------------------------------------------------------------------------
    */

    'ab_testing' => [
        'enabled' => env('VISUAL_EDITOR_AB_TESTING', false),

        // Minimum sample size for significance
        'min_sample_size' => 100,

        // Confidence level for winner determination
        'confidence_level' => 0.95,

        // Track anonymous visitors
        'track_anonymous' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Offline Support
    |--------------------------------------------------------------------------
    */

    'offline' => [
        'enabled' => true,

        // Sync strategy
        'sync_strategy' => 'queue', // queue, immediate

        // Max offline changes to store
        'max_queued_changes' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Presence
    |--------------------------------------------------------------------------
    */

    'presence' => [
        'enabled' => true,

        // Heartbeat interval (seconds)
        'heartbeat_interval' => 30,

        // Consider user away after (seconds)
        'away_timeout' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Editor UI
    |--------------------------------------------------------------------------
    */

    'editor' => [
        // Default device preview
        'default_device' => 'desktop',

        // Show device preview options
        'show_device_toggle' => true,

        // Keyboard shortcuts
        'shortcuts' => [
            'save' => 'cmd+s',
            'undo' => 'cmd+z',
            'redo' => 'cmd+shift+z',
            'publish' => 'cmd+shift+p',
            'toggle_sidebar_left' => 'cmd+[',
            'toggle_sidebar_right' => 'cmd+]',
            'command_palette' => 'cmd+k',
            'search_blocks' => '/',
        ],

        // Canvas settings
        'canvas' => [
            'show_block_outlines' => true,
            'show_insertion_points' => true,
        ],

        // Sidebar settings
        'sidebar' => [
            'default_left_panel' => 'layers', // layers, inserter, sections
            'default_right_panel' => 'settings', // settings, styles, advanced
            'collapsible' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO Integration
    |--------------------------------------------------------------------------
    */

    'seo' => [
        // Enable SEO panel (requires artisanpack-ui/seo)
        'enabled' => true,

        // Show content analysis
        'show_analysis' => true,

        // Require focus keyword
        'require_focus_keyword' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Accessibility
    |--------------------------------------------------------------------------
    |
    | The artisanpack-ui/accessibility package is a required dependency.
    | These settings control how accessibility features are used.
    |
    */

    'accessibility' => [
        // Enforce alt text on images
        'require_alt_text' => true, // warning only
        'block_publish_without_alt' => false,

        // Check heading hierarchy
        'check_heading_hierarchy' => true,

        // Color contrast checking (uses artisanpack-ui/accessibility)
        'check_contrast' => true,
        'auto_suggest_accessible_colors' => true, // Suggest accessible text colors when bg changes
        'contrast_ratio_threshold' => 4.5, // WCAG AA standard

        // Accessibility scanner
        'scanner' => [
            'enabled' => true,
            'show_in_toolbar' => true, // Show scan button in toolbar
            'checks' => [
                'alt_text' => true,
                'heading_hierarchy' => true,
                'color_contrast' => true,
                'link_text' => true,
                'empty_buttons' => true,
                'form_labels' => true,
            ],
            'allow_export' => true, // Allow exporting scan results
        ],

        // Real-time contrast feedback in color pickers
        'realtime_contrast_check' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    */

    'media' => [
        // Use artisanpack-ui/media-library for media selection
        'use_media_library' => true,

        // Allowed image types
        'allowed_image_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],

        // Allowed video types (for self-hosted)
        'allowed_video_types' => ['mp4', 'webm', 'ogg'],

        // Max file size (bytes)
        'max_file_size' => 10485760, // 10MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Pre-Publish Checks
    |--------------------------------------------------------------------------
    */

    'pre_publish_checks' => [
        'enabled' => true,

        'checks' => [
            'broken_links' => true,
            'missing_alt_text' => true,
            'heading_hierarchy' => true,
            'meta_title' => true,
            'meta_description' => true,
            'spelling' => false, // Requires additional setup
            'performance' => true,
        ],

        // Block publishing if checks fail
        'block_on_failure' => false,
    ],

];
```

---

## Environment Variables

```env
# Visual Editor
VISUAL_EDITOR_ENABLED=true
VISUAL_EDITOR_AI_ENABLED=false
VISUAL_EDITOR_AI_PROVIDER=openai
VISUAL_EDITOR_AB_TESTING=false

# AI Providers
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
```

---

## Publishing Config

```bash
php artisan vendor:publish --tag=visual-editor-config
```

This creates `config/visual-editor.php` in your application.

---

## CMS Framework Integration Summary

### Configuration Sources

The visual editor uses three configuration sources:

| Source | Purpose | Admin Editable | Example |
|--------|---------|----------------|---------|
| **Config File** | Static settings (routes, middleware) | No | `config('visual-editor.route_prefix')` |
| **CMS Settings** | Dynamic settings (features, limits) | Yes | `apGetSetting('visual_editor.ai.enabled')` |
| **CMS Permissions** | User access control | Yes | `$user->hasPermissionTo('visual_editor.access')` |

### Settings Stored in CMS Framework

These settings are registered with `apRegisterSetting()` and can be changed via admin interface:

```php
// Feature toggles
'visual_editor.locking.enabled'           // boolean
'visual_editor.ai.enabled'                // boolean
'visual_editor.ab_testing.enabled'        // boolean

// Restrictions
'visual_editor.allowed_blocks'            // json (array or null)
'visual_editor.disallowed_blocks'         // json (array)
'visual_editor.allowed_sections'          // json (array or null)
'visual_editor.disallowed_sections'       // json (array)

// Performance budgets
'visual_editor.performance.max_weight'    // integer (bytes)
'visual_editor.performance.max_images'    // integer

// Accessibility
'visual_editor.accessibility.require_alt_text'   // boolean
'visual_editor.accessibility.check_contrast'     // boolean

// Versioning
'visual_editor.versioning.autosave_interval'     // integer (seconds)
'visual_editor.versioning.max_autosave_revisions' // integer

// AI Provider Settings (admin can configure provider and API keys)
'visual_editor.ai.provider'                          // string (openai, anthropic, or custom)
'visual_editor.ai.openai.api_key'                    // string (encrypted)
'visual_editor.ai.openai.model'                      // string
'visual_editor.ai.anthropic.api_key'                 // string (encrypted)
'visual_editor.ai.anthropic.model'                   // string
'visual_editor.ai.{custom_provider}.{key}'           // varies (custom providers)
'visual_editor.ai.features.content_suggestions'      // boolean
'visual_editor.ai.features.alt_text'                 // boolean
'visual_editor.ai.features.layout_suggestions'       // boolean
'visual_editor.ai.features.seo_suggestions'          // boolean
'visual_editor.ai.rate_limits.requests_per_minute'   // integer
'visual_editor.ai.rate_limits.requests_per_day'      // integer
```

### AI Provider Extensibility

Developers can register custom AI providers using the hooks system:

```php
// Register a custom provider (e.g., Google Gemini)
addFilter('ap.visualEditor.aiProvidersRegister', function (array $providers) {
    $providers['gemini'] = new GeminiProvider();
    return $providers;
});
```

Custom providers must implement `AIProviderInterface` and provide:
- `getIdentifier()`: Unique provider ID
- `getName()`: Display name
- `getAvailableModels()`: Available model options
- `getSettingsSchema()`: Settings fields for admin UI
- `isConfigured()`: Check if API key is set
- AI methods: `generateText()`, `improveText()`, `generateAltText()`, etc.

See `08-additional-features.md` for the complete `AIProviderInterface` and example implementation

### Admin Menu Pages

The visual editor registers these admin pages via CMS framework:

| Page | Route | Capability Required |
|------|-------|---------------------|
| Editor | `/admin/visual-editor` | `visual_editor.access` |
| Templates | `/admin/visual-editor-templates` | `visual_editor.templates.view` |
| Global Styles | `/admin/visual-editor-styles` | `visual_editor.styles.view` |
| Settings | `/admin/visual-editor-settings` | `visual_editor.manage` |
| AI Settings | `/admin/visual-editor-ai` | `visual_editor.manage` |

### Retrieving Settings

```php
// Get setting with fallback to default
$enabled = apGetSetting('visual_editor.locking.enabled', true);

// Update setting (typically from admin interface)
apUpdateSetting('visual_editor.locking.enabled', false);

// Check permission
if (auth()->user()->hasPermissionTo('visual_editor.manage')) {
    // Show admin settings page
}
```

See `07-permissions-locking.md` for complete permission and role documentation.
