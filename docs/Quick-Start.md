# Quick Start

Install the visual editor, register a model as editable content, mount the Blade component, and ship a first post — under an hour from `composer require` to a working editor.

If you also need site-wide chrome (templates, template parts, theme typography, menus, patterns), install `artisanpack-ui/cms-framework` alongside the editor. The two packages are a version pair (V1.x ↔ V1.x). See [[home#version-compatibility]].

---

## 1. Install

```bash
composer require artisanpack-ui/visual-editor
php artisan migrate
```

The package ships a `ve_contents` table for the legacy `VisualEditorPost` fallback model. Host-app resource models you register later don't touch this table — they store block content on their own.

### Peer dependencies

The editor UI is built on [`@artisanpack-ui/react`](https://www.npmjs.com/package/@artisanpack-ui/react), which is styled with DaisyUI + Tailwind CSS v4. Host apps must have:

- `tailwindcss` `^4.0.0`
- `daisyui` `^5.0.0`

Both are loaded once at the app shell level. The editor inherits them from the host page.

### Pair with cms-framework (recommended)

```bash
composer require artisanpack-ui/cms-framework
php artisan migrate
```

Adds a `block_content` column to cms-framework's `posts` and `pages` tables, seeds `visual_editor.*` permissions, and auto-registers `Post`/`Page` into the resource map. The site editor's install gate goes green and the `core/post-*`, `core/site-*`, `core/query`, taxonomy, and navigation blocks all resolve.

You can skip this step — the editor works standalone for a single resource model — but you'll have to wire entity blocks yourself.

---

## 2. Publish the config (optional)

```bash
php artisan vendor:publish --tag=artisanpack-visual-editor-config
```

Drops `config/artisanpack/visual-editor.php`. Edit it to register resources, swap the API middleware, tune the block allow/deny lists, or change global-styles theme/schema pinning. See [[Configuration]] for the full reference.

---

## 3. Register a resource model

A "resource" is any Eloquent model whose content is editable via the visual editor. Add the `HasBlockContent` trait and declare it in the config:

```php
// app/Models/Post.php
namespace App\Models;

use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasBlockContent;

    protected $fillable = ['title', 'slug', 'content'];
}
```

```php
// config/artisanpack/visual-editor.php
return [
    'resources' => [
        'posts' => App\Models\Post::class,
    ],
    // …
];
```

The trait adds a `content` JSON column convention, a Scout-friendly searchable accessor, and the `forVisualEditor` query scope used by the resource resolver. Full reference: [[Content Model]].

If your model already has a different column for block content:

```php
class Post extends Model
{
    use HasBlockContent;

    protected $blockContentColumn = 'body';
}
```

Run the migration that adds the column:

```bash
php artisan make:migration add_content_to_posts_table
```

```php
Schema::table('posts', function (Blueprint $table) {
    $table->json('content')->nullable();
});
```

---

## 4. Mount the editor

In whatever Blade view backs your edit screen:

```blade
{{-- resources/views/admin/posts/edit.blade.php --}}
<x-visual-editor
    :model="$post"
    :initialTitle="$post->title"
    :initialSlug="$post->slug"
    :initialStatus="$post->status"
/>
```

The Blade component emits a single `<div data-ap-visual-editor>` mount point. The React app boots against it on page load, fetches block content from `/visual-editor/api/posts/{id}/content`, and autosaves on every change.

Full attribute list and event contract: [[post-editor/Blade Component]].

---

## 5. Build assets

The editor JS bundle is registered automatically by the package's Vite plugin. From your app:

```bash
npm install
npm run build   # or: npm run dev
```

The editor mounts on every page that contains a `[data-ap-visual-editor]` element.

---

## 6. Try it

1. Run `php artisan serve` (or `composer run dev` in this dev app).
2. Visit the edit screen.
3. Insert a Paragraph block, type, watch the autosave indicator fire.
4. Reload — content persists.

If the editor doesn't appear, check the browser console for missing peer deps (Tailwind / DaisyUI) and confirm the resource is registered. See [[Troubleshooting]].

---

## 7. Add the site editor (optional)

If cms-framework is installed, the site editor shell is already mounted at `/visual-editor/site`. The default access gate fails closed — implement your own `SiteEditorAccessGate` or rely on cms-framework's `CmsFrameworkInstallGate`. See [[Site Editor]] and [[site-editor/Access Gate]].

---

## Where to go next

- **Authoring custom blocks:** [[blocks/Custom Blocks]]
- **Rendering saved content on the public site:** [[Renderers]]
- **Editing templates and theme styles:** [[Site Editor]], [[site-editor/Templates]], [[site-editor/Global Styles]]
- **Embedding inside Livewire:** [[post-editor/Livewire Integration]]
- **Embedding inside Inertia:** [[post-editor/Inertia Integration]]
- **Things that go wrong:** [[Troubleshooting]]
