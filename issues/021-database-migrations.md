/label ~"Type::Setup" ~"Status::Backlog" ~"Priority::High" ~"Area::Backend" ~"Phase::1"

## Task Description

Create all database migrations for the visual editor package. These migrations must be run before any other features can be implemented.

## Tables to Create

### ve_contents (Main content storage)

```php
Schema::create('ve_contents', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->string('content_type')->default('page');
    $table->foreignId('content_type_id')->nullable()->constrained('content_types');
    $table->string('title');
    $table->string('slug')->index();
    $table->text('excerpt')->nullable();
    $table->json('sections');
    $table->json('settings')->nullable();
    $table->string('template')->nullable();
    $table->json('template_overrides')->nullable();
    $table->enum('status', ['draft', 'pending', 'published', 'scheduled', 'private'])->default('draft');
    $table->timestamp('published_at')->nullable();
    $table->timestamp('scheduled_at')->nullable();
    $table->string('meta_title')->nullable();
    $table->text('meta_description')->nullable();
    $table->string('og_image')->nullable();
    $table->foreignId('featured_media_id')->nullable()->constrained('media');
    $table->foreignId('author_id')->constrained('users');
    $table->json('lock')->nullable();
    $table->softDeletes();
    $table->timestamps();
    $table->index(['content_type', 'status']);
    $table->index(['content_type', 'slug']);
    $table->unique(['content_type', 'slug']);
});
```

### ve_content_revisions

```php
Schema::create('ve_content_revisions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('content_id')->constrained('ve_contents')->onDelete('cascade');
    $table->foreignId('user_id')->constrained('users');
    $table->enum('type', ['autosave', 'manual', 'named', 'publish', 'pre_restore'])->default('autosave');
    $table->string('name')->nullable();
    $table->json('data');
    $table->text('change_summary')->nullable();
    $table->timestamp('created_at');
    $table->index(['content_id', 'created_at']);
    $table->index(['content_id', 'type']);
});
```

### ve_templates

```php
Schema::create('ve_templates', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('type')->default('page');
    $table->string('for_content_type')->nullable();
    $table->json('template_parts');
    $table->json('content_area_settings');
    $table->json('styles')->nullable();
    $table->boolean('is_custom')->default(false);
    $table->boolean('is_active')->default(true);
    $table->json('lock')->nullable();
    $table->timestamps();
});
```

### ve_template_parts

```php
Schema::create('ve_template_parts', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->string('name');
    $table->string('area')->default('header');
    $table->json('blocks');
    $table->json('styles')->nullable();
    $table->string('variation')->nullable();
    $table->boolean('is_custom')->default(false);
    $table->boolean('is_active')->default(true);
    $table->json('lock')->nullable();
    $table->timestamps();
    $table->index(['area', 'variation']);
});
```

### ve_user_sections

```php
Schema::create('ve_user_sections', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('category')->nullable();
    $table->json('blocks');
    $table->json('styles')->nullable();
    $table->string('preview_image')->nullable();
    $table->boolean('is_shared')->default(false);
    $table->integer('use_count')->default(0);
    $table->timestamps();
    $table->index(['user_id', 'category']);
});
```

### ve_global_styles

```php
Schema::create('ve_global_styles', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->json('value');
    $table->json('theme_default')->nullable();
    $table->boolean('is_customized')->default(false);
    $table->timestamps();
});
```

### ve_experiments

```php
Schema::create('ve_experiments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('content_id')->constrained('ve_contents')->onDelete('cascade');
    $table->foreignId('created_by')->constrained('users');
    $table->string('name');
    $table->text('description')->nullable();
    $table->enum('type', ['headline', 'section', 'full_page'])->default('headline');
    $table->integer('traffic_split')->default(50);
    $table->enum('goal_type', ['clicks', 'conversions', 'time_on_page', 'scroll_depth']);
    $table->string('goal_target')->nullable();
    $table->enum('status', ['draft', 'running', 'paused', 'ended'])->default('draft');
    $table->timestamp('started_at')->nullable();
    $table->timestamp('ended_at')->nullable();
    $table->foreignId('winner_variant_id')->nullable()->constrained('ve_experiment_variants')->nullOnDelete();
    $table->timestamps();
    $table->index(['content_id', 'status']);
});
```

### ve_experiment_variants

```php
Schema::create('ve_experiment_variants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('experiment_id')->constrained('ve_experiments')->onDelete('cascade');
    $table->string('name');
    $table->boolean('is_control')->default(false);
    $table->json('content_data');
    $table->unsignedBigInteger('impressions')->default(0);
    $table->unsignedBigInteger('conversions')->default(0);
    $table->timestamps();
    $table->index(['experiment_id', 'is_control']);
});
```

### ve_editor_locks

```php
Schema::create('ve_editor_locks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('content_id')->constrained('ve_contents')->onDelete('cascade');
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
    $table->string('session_id');
    $table->timestamp('started_at');
    $table->timestamp('last_heartbeat');
    $table->unique(['content_id', 'user_id']);
    $table->index(['content_id', 'last_heartbeat']);
});
```

## Acceptance Criteria

- [ ] All migrations created in correct order
- [ ] Foreign keys reference correct tables
- [ ] Indexes are added for performance
- [ ] Migrations run without errors
- [ ] Migrations can be rolled back
- [ ] Models are created for each table

## Context

These migrations follow the schema defined in the planning documentation. The `ve_` prefix is used to namespace tables for the visual editor package.

**Related Issues:**
- Blocks: All feature issues depend on these migrations
