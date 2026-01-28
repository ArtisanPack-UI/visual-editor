/label ~"Type::Setup" ~"Status::Backlog" ~"Priority::High" ~"Area::Backend" ~"Phase::1"

## Task Description

Create the configuration architecture for the visual editor package, including config files, service provider, and facades.

## Configuration File Structure

### config/visual-editor.php

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Editor Settings
    |--------------------------------------------------------------------------
    */
    'editor' => [
        'autosave_interval' => env('VE_AUTOSAVE_INTERVAL', 60),
        'max_history_states' => 50,
        'default_template' => 'default',
        'show_preview_button' => true,
        'preview_mode' => 'new_tab', // new_tab, modal, iframe
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Settings
    |--------------------------------------------------------------------------
    */
    'content' => [
        'default_status' => 'draft',
        'require_featured_image' => false,
        'enable_revisions' => true,
        'enable_scheduling' => true,
        'slug_generation' => 'auto', // auto, manual
    ],

    /*
    |--------------------------------------------------------------------------
    | Block Settings
    |--------------------------------------------------------------------------
    */
    'blocks' => [
        'enable_custom_blocks' => true,
        'allowed_blocks' => [], // Empty = all allowed
        'disallowed_blocks' => [],
        'default_supports' => ['align', 'spacing'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Section Settings
    |--------------------------------------------------------------------------
    */
    'sections' => [
        'enable_user_sections' => true,
        'max_user_sections' => 50,
        'enable_section_sharing' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Settings
    |--------------------------------------------------------------------------
    */
    'templates' => [
        'enable_custom_templates' => true,
        'enable_template_editing' => true,
        'default_template_parts' => ['header', 'footer'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Styles
    |--------------------------------------------------------------------------
    */
    'styles' => [
        'enable_style_editing' => true,
        'enable_tailwind_export' => true,
        'css_output_path' => 'css/visual-editor-styles.css',
    ],

    /*
    |--------------------------------------------------------------------------
    | Revision Settings
    |--------------------------------------------------------------------------
    */
    'revisions' => [
        'autosave_retention_hours' => 24,
        'manual_retention_days' => 30,
        'max_autosaves_per_content' => 10,
        'keep_all_named' => true,
        'keep_all_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'use_cms_framework' => true, // Use cms-framework permissions
        'custom_capabilities' => [
            'visual_editor.access',
            'visual_editor.edit_content',
            'visual_editor.publish',
            'visual_editor.edit_templates',
            'visual_editor.edit_styles',
            'visual_editor.manage_blocks',
            'visual_editor.edit_locked',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Locking
    |--------------------------------------------------------------------------
    */
    'locking' => [
        'enable_content_locking' => true,
        'enable_block_locking' => true,
        'heartbeat_interval' => 30, // seconds
        'lock_timeout' => 120, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Assistant
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'enabled' => env('VE_AI_ENABLED', false),
        'default_provider' => env('VE_AI_PROVIDER', 'openai'),
        'providers' => [
            'openai' => [
                'api_key' => env('OPENAI_API_KEY'),
                'model' => env('VE_OPENAI_MODEL', 'gpt-4o-mini'),
            ],
            'anthropic' => [
                'api_key' => env('ANTHROPIC_API_KEY'),
                'model' => env('VE_ANTHROPIC_MODEL', 'claude-3-haiku-20240307'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | A/B Testing
    |--------------------------------------------------------------------------
    */
    'experiments' => [
        'enabled' => env('VE_EXPERIMENTS_ENABLED', false),
        'cookie_duration' => 30, // days
        'minimum_sample_size' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Accessibility
    |--------------------------------------------------------------------------
    */
    'accessibility' => [
        'enabled' => true,
        'minimum_score' => 80,
        'block_publish_on_errors' => false,
        'checks' => [
            'images' => true,
            'headings' => true,
            'links' => true,
            'color_contrast' => true,
            'forms' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'enable_lazy_loading' => true,
        'enable_asset_optimization' => true,
        'cache_rendered_content' => true,
        'cache_ttl' => 3600, // seconds
    ],
];
```

## Service Provider

```php
namespace ArtisanPackUI\VisualEditor;

class VisualEditorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/visual-editor.php', 'visual-editor');

        // Register singletons
        $this->app->singleton('visual-editor', fn () => new VisualEditor());
        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(SectionRegistry::class);
        $this->app->singleton(TemplateRegistry::class);
        $this->app->singleton(GlobalStylesManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'visual-editor');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->publishes([
            __DIR__.'/../config/visual-editor.php' => config_path('visual-editor.php'),
        ], 'visual-editor-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/visual-editor'),
        ], 'visual-editor-views');

        // Register Livewire components
        Livewire::component('visual-editor', VisualEditor::class);
        Livewire::component('ve-canvas', Canvas::class);
        // ... other components

        // Boot registries
        $this->bootBlocks();
        $this->bootSections();
        $this->bootTemplates();
    }
}
```

## Acceptance Criteria

- [ ] Config file created with all settings
- [ ] Service provider registers all services
- [ ] Config is publishable
- [ ] Views are publishable
- [ ] Migrations are loaded
- [ ] Routes are loaded
- [ ] Livewire components are registered
- [ ] Helper functions are available
- [ ] Facades work correctly

## Context

This is foundational setup required before any feature development.

**Related Issues:**
- Blocks: All features depend on this configuration
