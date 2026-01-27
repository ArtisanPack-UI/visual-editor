# Visual Editor - Configuration Reference

## Complete Configuration File

```php
<?php

// config/visual-editor.php

return [

    /*
    |--------------------------------------------------------------------------
    | General Settings
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
    | Permissions
    |--------------------------------------------------------------------------
    */

    'permissions' => [
        // Template editing
        'can_edit_templates' => true,
        'can_create_templates' => true,
        'can_delete_templates' => false,
        'can_edit_template_parts' => true,

        // Global styles
        'can_edit_global_styles' => true,
        'can_edit_colors' => true,
        'can_edit_typography' => true,
        'can_edit_spacing' => true,

        // Advanced features
        'can_add_custom_css' => false,
        'can_add_custom_html' => false,
        'can_add_custom_js' => false,
        'can_view_code' => false,

        // Sections
        'can_create_sections' => true,
        'can_delete_sections' => true,
        'can_reorder_sections' => true,
        'can_save_section_patterns' => true,

        // Blocks
        'can_add_blocks' => true,
        'can_delete_blocks' => true,
        'can_reorder_blocks' => true,

        // Block restrictions
        'allowed_blocks' => null, // null = all blocks allowed
        'disallowed_blocks' => [], // Blocks to hide from inserter

        // Section restrictions
        'allowed_sections' => null,
        'disallowed_sections' => [],

        // Content locking
        'can_lock_content' => true,
        'can_unlock_content' => true,

        // Publishing
        'can_publish' => true,
        'can_schedule' => true,
        'can_unpublish' => true,

        // Versioning
        'can_view_revisions' => true,
        'can_restore_revisions' => true,
        'can_create_named_versions' => true,

        // AI features
        'can_use_ai' => true,

        // A/B testing
        'can_create_experiments' => false,
    ],

    // Permission presets for roles
    'permission_presets' => [
        'content_editor' => [
            'can_edit_templates' => false,
            'can_edit_global_styles' => false,
            'can_add_custom_css' => false,
            'can_add_custom_html' => false,
            'can_create_sections' => false,
            'can_delete_sections' => false,
            'can_lock_content' => false,
            'can_create_experiments' => false,
            'disallowed_blocks' => ['html', 'code', 'shortcode'],
        ],
        'site_editor' => [
            'can_edit_templates' => true,
            'can_edit_global_styles' => true,
            'can_add_custom_css' => false,
            'can_add_custom_html' => false,
            'can_lock_content' => true,
            'disallowed_blocks' => ['html', 'code'],
        ],
        'developer' => [
            'can_edit_templates' => true,
            'can_edit_global_styles' => true,
            'can_add_custom_css' => true,
            'can_add_custom_html' => true,
            'can_view_code' => true,
            'can_lock_content' => true,
            'can_unlock_content' => true,
            'allowed_blocks' => null,
            'disallowed_blocks' => [],
        ],
    ],

    // Map user roles to permission presets
    'role_permissions' => [
        'admin' => 'developer',
        'editor' => 'site_editor',
        'author' => 'content_editor',
        'contributor' => 'content_editor',
    ],

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
    | AI Assistant
    |--------------------------------------------------------------------------
    */

    'ai' => [
        'enabled' => env('VISUAL_EDITOR_AI_ENABLED', false),

        'provider' => env('VISUAL_EDITOR_AI_PROVIDER', 'openai'),

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => 'gpt-4',
            'max_tokens' => 1000,
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => 'claude-3-sonnet-20240229',
            'max_tokens' => 1000,
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
