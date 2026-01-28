# Visual Editor - Database Schema

> **Phase:** 1 (Core Editor) — High Priority (Setup)
>
> Database migrations must be created first before any other implementation work.

---

## Tables Overview

| Table | Purpose |
|-------|---------|
| `ve_contents` | Main content storage (pages, posts, etc.) |
| `ve_content_revisions` | Revision history |
| `ve_templates` | Template definitions |
| `ve_template_parts` | Reusable template parts (header, footer) |
| `ve_user_sections` | User-created section patterns |
| `ve_global_styles` | Global style customizations |
| `ve_experiments` | A/B test definitions |
| `ve_experiment_variants` | A/B test variants |
| `ve_editor_locks` | Active editor locks |

---

## ve_contents

Main content storage table.

```php
Schema::create('ve_contents', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();

    // Content type (integrates with cms-framework)
    $table->string('content_type')->default('page'); // page, post, {custom_type}
    $table->foreignId('content_type_id')->nullable()->constrained('content_types');

    // Basic info
    $table->string('title');
    $table->string('slug')->index();
    $table->text('excerpt')->nullable();

    // Content structure
    $table->json('sections'); // Array of section objects with blocks
    $table->json('settings')->nullable(); // Page-level settings

    // Template
    $table->string('template')->nullable(); // Template slug
    $table->json('template_overrides')->nullable(); // Template part overrides

    // Status
    $table->enum('status', ['draft', 'pending', 'published', 'scheduled', 'private'])->default('draft');
    $table->timestamp('published_at')->nullable();
    $table->timestamp('scheduled_at')->nullable();

    // SEO (if seo package not installed)
    $table->string('meta_title')->nullable();
    $table->text('meta_description')->nullable();
    $table->string('og_image')->nullable();

    // Featured image
    $table->foreignId('featured_media_id')->nullable()->constrained('media');

    // Author
    $table->foreignId('author_id')->constrained('users');

    // Locking
    $table->json('lock')->nullable();

    // Soft deletes
    $table->softDeletes();
    $table->timestamps();

    // Indexes
    $table->index(['content_type', 'status']);
    $table->index(['content_type', 'slug']);
    $table->unique(['content_type', 'slug']);
});
```

### Sections JSON Structure

```json
{
    "sections": [
        {
            "id": "section-uuid-1",
            "type": "hero",
            "order": 0,
            "lock": null,
            "styles": {
                "background": "white",
                "padding": "large",
                "min_height": "80vh"
            },
            "blocks": [
                {
                    "id": "block-uuid-1",
                    "type": "heading",
                    "order": 0,
                    "lock": null,
                    "content": {
                        "text": "Welcome",
                        "level": "h1"
                    },
                    "styles": {
                        "alignment": "center",
                        "color": null
                    },
                    "advanced": {
                        "css_class": "",
                        "html_anchor": ""
                    }
                }
            ]
        }
    ]
}
```

---

## ve_content_revisions

Stores content revision history.

```php
Schema::create('ve_content_revisions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('content_id')->constrained('ve_contents')->onDelete('cascade');
    $table->foreignId('user_id')->constrained('users');

    // Revision type
    $table->enum('type', ['autosave', 'manual', 'named', 'publish', 'pre_restore'])->default('autosave');
    $table->string('name')->nullable(); // For named versions

    // Content snapshot
    $table->json('data'); // Full content state

    // Metadata
    $table->text('change_summary')->nullable();

    $table->timestamp('created_at');

    // Indexes
    $table->index(['content_id', 'created_at']);
    $table->index(['content_id', 'type']);
});
```

---

## ve_templates

Stores template definitions.

```php
Schema::create('ve_templates', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->string('name');
    $table->text('description')->nullable();

    // Template type
    $table->string('type')->default('page'); // page, post, archive, single, etc.
    $table->string('for_content_type')->nullable(); // Specific content type or null for all

    // Structure
    $table->json('template_parts'); // ['header', 'footer', 'sidebar']
    $table->json('content_area_settings'); // Layout settings for content area

    // Styles
    $table->json('styles')->nullable();

    // Status
    $table->boolean('is_custom')->default(false); // User-created vs built-in
    $table->boolean('is_active')->default(true);

    // Locking
    $table->json('lock')->nullable();

    $table->timestamps();
});
```

---

## ve_template_parts

Stores reusable template parts.

```php
Schema::create('ve_template_parts', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->string('name');
    $table->string('area')->default('header'); // header, footer, sidebar

    // Content
    $table->json('blocks');
    $table->json('styles')->nullable();

    // Variations
    $table->string('variation')->nullable(); // null = default, or variation name

    // Status
    $table->boolean('is_custom')->default(false);
    $table->boolean('is_active')->default(true);

    // Locking
    $table->json('lock')->nullable();

    $table->timestamps();

    // Index for finding variations
    $table->index(['area', 'variation']);
});
```

---

## ve_user_sections

Stores user-created section patterns.

```php
Schema::create('ve_user_sections', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');

    $table->string('name');
    $table->text('description')->nullable();
    $table->string('category')->nullable();

    // Section content
    $table->json('blocks');
    $table->json('styles')->nullable();

    // Preview
    $table->string('preview_image')->nullable();

    // Sharing
    $table->boolean('is_shared')->default(false);
    $table->integer('use_count')->default(0);

    $table->timestamps();

    // Indexes
    $table->index(['user_id', 'category']);
});
```

---

## ve_global_styles

Stores global style customizations.

```php
Schema::create('ve_global_styles', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique(); // colors, typography, spacing, layout, etc.

    // Values
    $table->json('value');
    $table->json('theme_default')->nullable(); // Original theme value

    // Tracking
    $table->boolean('is_customized')->default(false);

    $table->timestamps();
});
```

---

## ve_experiments

Stores A/B test definitions.

```php
Schema::create('ve_experiments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('content_id')->constrained('ve_contents')->onDelete('cascade');
    $table->foreignId('created_by')->constrained('users');

    $table->string('name');
    $table->text('description')->nullable();

    // Experiment type
    $table->enum('type', ['headline', 'section', 'full_page'])->default('headline');

    // Traffic allocation
    $table->integer('traffic_split')->default(50); // Percentage to treatment

    // Goal tracking
    $table->enum('goal_type', ['clicks', 'conversions', 'time_on_page', 'scroll_depth']);
    $table->string('goal_target')->nullable(); // CSS selector for click/conversion tracking

    // Status
    $table->enum('status', ['draft', 'running', 'paused', 'ended'])->default('draft');
    $table->timestamp('started_at')->nullable();
    $table->timestamp('ended_at')->nullable();

    // Results
    $table->foreignId('winner_variant_id')->nullable();

    $table->timestamps();

    // Indexes
    $table->index(['content_id', 'status']);
});
```

---

## ve_experiment_variants

Stores A/B test variants.

```php
Schema::create('ve_experiment_variants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('experiment_id')->constrained('ve_experiments')->onDelete('cascade');

    $table->string('name');
    $table->boolean('is_control')->default(false);

    // Variant content
    $table->json('content_data'); // The variant content

    // Statistics
    $table->unsignedBigInteger('impressions')->default(0);
    $table->unsignedBigInteger('conversions')->default(0);

    $table->timestamps();

    // Indexes
    $table->index(['experiment_id', 'is_control']);
});
```

---

## ve_editor_locks

Tracks active editor sessions for presence awareness.

```php
Schema::create('ve_editor_locks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('content_id')->constrained('ve_contents')->onDelete('cascade');
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

    $table->string('session_id');
    $table->timestamp('started_at');
    $table->timestamp('last_heartbeat');

    // Indexes
    $table->unique(['content_id', 'user_id']);
    $table->index(['content_id', 'last_heartbeat']);
});
```

---

## Indexes Summary

```php
// Performance indexes to add after table creation

// Content lookups
DB::statement('CREATE INDEX ve_contents_search ON ve_contents USING gin(to_tsvector(\'english\', title || \' \' || COALESCE(excerpt, \'\')))');

// Revision cleanup
DB::statement('CREATE INDEX ve_revisions_cleanup ON ve_content_revisions (content_id, type, created_at) WHERE type = \'autosave\'');

// Active experiments
DB::statement('CREATE INDEX ve_experiments_active ON ve_experiments (status, content_id) WHERE status = \'running\'');
```

---

## Model Relationships

```php
// Content.php
class Content extends Model
{
    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    public function experiments(): HasMany
    {
        return $this->hasMany(Experiment::class);
    }

    public function activeExperiment(): HasOne
    {
        return $this->hasOne(Experiment::class)->where('status', 'running');
    }
}

// Template.php
class Template extends Model
{
    public function parts(): BelongsToMany
    {
        return TemplatePart::whereIn('slug', $this->template_parts)->get();
    }
}

// Experiment.php
class Experiment extends Model
{
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ExperimentVariant::class);
    }

    public function controlVariant(): HasOne
    {
        return $this->hasOne(ExperimentVariant::class)->where('is_control', true);
    }

    public function treatmentVariant(): HasOne
    {
        return $this->hasOne(ExperimentVariant::class)->where('is_control', false);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(ExperimentVariant::class, 'winner_variant_id');
    }
}
```
