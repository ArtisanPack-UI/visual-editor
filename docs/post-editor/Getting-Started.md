# Getting Started — Post Editor

Mount the post editor on any Eloquent model in a few steps. This page assumes the package is already installed — if not, see [[Installation Guide]] first.

For the end-to-end install + post walkthrough, see [[Quick Start]].

---

## 1. Add the `HasBlockContent` trait

```php
// app/Models/Post.php
namespace App\Models;

use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasBlockContent;
}
```

The trait auto-applies a JSON cast to the `content` column at boot. To override the column name:

```php
class Post extends Model
{
    use HasBlockContent;

    protected string $blockContentColumn = 'body';
}
```

Add the column via migration if your model doesn't have one yet:

```php
Schema::table('posts', function (Blueprint $table) {
    $table->json('content')->nullable();
});
```

Full trait reference: [[Content Model#1-the-hasblockcontent-trait]].

---

## 2. Register the model in the resource map

```php
// config/artisanpack/visual-editor.php
return [
    'resources' => [
        'posts' => App\Models\Post::class,
    ],
];
```

The map's slug (`posts` here) becomes part of the REST URL: `/visual-editor/api/posts/{id}/content`. Multiple models can register under different slugs — `posts`, `pages`, `products`, anything.

Models can also be registered at runtime via the `ap.visual-editor.resources` filter — see [[Hooks and Events#ap-visual-editor-resources]].

---

## 3. Mount the editor

```blade
{{-- resources/views/admin/posts/edit.blade.php --}}
<x-visual-editor
    :model="$post"
    :initialTitle="$post->title"
    :initialSlug="$post->slug"
    :initialStatus="$post->status"
/>
```

The Blade component emits a `<div data-ap-visual-editor>` mount point with all the data-attributes the React app needs to boot. On page load, the editor fetches the persisted block tree from `/visual-editor/api/posts/{id}/content` and autosaves on every change.

Full attribute reference: [[Blade Component]].

---

## 4. Build assets

```bash
npm install
npm run dev      # or: npm run build
```

The editor's Vite plugin registers its bundle automatically — host apps don't import the editor JS manually.

---

## 5. Confirm it works

1. Visit your edit screen.
2. Insert a Paragraph block, type some content.
3. Watch the autosave indicator fire after ~1s of idle.
4. Reload — the content persists.

If the editor doesn't appear, see [[Troubleshooting#1-editor-doesn-t-appear]].

---

## What to do next

- **Tour the surface:** [[Post Editor]] walks through every region (topbar, sidebars, canvas, footer).
- **Customize what authors can insert:** [[blocks/Custom Blocks]] for shipping your own blocks.
- **Wire up a media library:** [[Post Editor#5-media-library-integration]].
- **Render the saved content on the public site:** [[Renderers]].
- **Embed inside Livewire:** [[Livewire Integration]].
- **Embed inside Inertia:** [[Inertia Integration]].
- **Restyle the editor chrome:** [[Theming]].
