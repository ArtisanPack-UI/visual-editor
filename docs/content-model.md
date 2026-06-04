# Content model

The visual editor stores content as a Gutenberg-shaped block tree (a JSON
array of `{ name, attributes, innerBlocks, … }` nodes) on any Eloquent
model that opts in via the `HasBlockContent` trait. Models are exposed to
the editor through the **resource map**, a slug → model class registry
read by the API layer.

This page covers the trait, the resource map, and the policy / authorization
surface.

---

## 1. The `HasBlockContent` trait

`ArtisanPackUI\VisualEditor\Concerns\HasBlockContent`

Add the trait to any model whose content should be editable:

```php
use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasBlockContent;
}
```

### Conventions and overrides

| Behaviour | Default | Override |
|-----------|---------|----------|
| Column storing the block tree | `content` (JSON) | `protected string $blockContentColumn = 'body'` |
| Query scope applied by the resource resolver | `forVisualEditor` (passthrough) | `protected string $blockContentScope = 'published'` (must exist on the model as a scope method) |
| Cast for the block column | `array` (auto-applied if not present) | Set `$casts['content'] = 'json'` explicitly to opt out of the auto-cast |
| Searchable text | Plain-text extract of the block tree | Override `blockContentSearchableText(): string` |

The trait's `initializeHasBlockContent()` hook auto-applies the `array`
cast at boot. If your model already casts the column, the trait leaves
your cast untouched.

### Public API

```php
$post->getBlockContent();          // array<int, array<string, mixed>>
$post->setBlockContent($blocks);   // void
$post->blockContentSearchableText(); // string
$post->toBlockContentSearchableArray(); // ['block_content' => '…'] for Scout
Post::query()->forVisualEditor()->get(); // optional content scope
```

The `forVisualEditor` scope is what the resource resolver applies before
fetching by id. By default it's a passthrough — override
`$blockContentScope` to filter by status, ownership, tenant, etc.

### Migration

Add the column when introducing the trait to an existing model:

```php
Schema::table('posts', function (Blueprint $table) {
    $table->json('content')->nullable();
});
```

The legacy `ve_contents` table the package ships is only used by the
fallback `VisualEditorPost` model and the `/editor` test route. Host-app
models never write to it.

---

## 2. The resource map

The resource map is a slug → model class array consulted by every
content-bearing API endpoint:

```
GET  /visual-editor/api/{resource}/{id}/content
PUT  /visual-editor/api/{resource}/{id}/content
```

`{resource}` is the slug. The map is read from two places, merged at boot:

1. **Static config** — `config('artisanpack.visual-editor.resources')`
   in `config/artisanpack/visual-editor.php`.
2. **Filter** — `ap.visual-editor.resources`, contributed by packages
   like cms-framework.

```php
// config/artisanpack/visual-editor.php
return [
    'resources' => [
        'posts' => App\Models\Post::class,
        'pages' => App\Models\Page::class,
    ],
];
```

```php
// From a package's service provider
addFilter('ap.visual-editor.resources', function (array $resources): array {
    return array_merge([
        'posts' => MyPackage\Models\Post::class,
    ], $resources);
});
```

**Collision wins:** static config beats filter contributions. The host
app's published config is authoritative — packages can suggest a default,
the app can override.

**Validation timing:** the map is not validated at boot. The first
request that resolves a missing resource raises `NotFoundHttpException`
(returned to the client as 404); a model that's registered but doesn't
use `HasBlockContent` raises `InvalidArgumentException`. This is
deliberate — contributor packages that aren't installed in a given
environment never trip boot.

### Resolution flow

1. Request comes in: `PUT /visual-editor/api/posts/42/content`.
2. `ResourceResolver` looks up `posts` → `App\Models\Post`.
3. It calls `Post::query()->forVisualEditor()->findOrFail(42)`.
4. The controller calls `$post->setBlockContent($blocks)` and `$post->save()`.

The model must use `HasBlockContent` — the resolver checks the trait
explicitly and throws otherwise.

---

## 3. Policies and authorization

Resource models use **their own Laravel policies**. The package does not
inject a "visual editor policy" on top of them. If your `PostPolicy`
already gates `update`, the editor's save endpoint inherits it.

The controllers call `Gate::authorize('update', $model)` (or
`Gate::authorize('view', $model)` on the show endpoint) before
reading/writing block content.

### Site-editor access gate

The site editor (templates, parts, patterns, global styles, navigation) is
gated by a single boot-time contract:
`ArtisanPackUI\VisualEditor\SiteEditor\Contracts\SiteEditorAccessGate`.

Default implementation: `DenyByDefaultGate` — fail-closed. Bind your own
to open it:

```php
// AppServiceProvider::register()
$this->app->bind(
    \ArtisanPackUI\VisualEditor\SiteEditor\Contracts\SiteEditorAccessGate::class,
    \App\Auth\AllowAdminsGate::class,
);
```

When cms-framework is installed it auto-binds `CmsFrameworkInstallGate`,
which checks that cms-framework is installed and migrations have run
before allowing access.

### API middleware

Every `/visual-editor/api/*` route runs through
`config('artisanpack.visual-editor.api.middleware')` (default:
`['api', 'auth']`). Replace the default if you need Sanctum, a different
guard, or unauthenticated read access.

---

## 4. Multiple resources, one editor

A single editor mount edits one resource at a time, scoped by the
`data-resource` and `data-id` attributes that the Blade component emits.
You can mount more than one editor on the same page — every editor event
includes `{ resource, id }` in its `detail`, so listeners can
disambiguate. See the [Livewire recipe](livewire.md) for an example of
multiple editors coexisting.

---

## 5. The `ve_contents` fallback table

The package's migration creates `ve_contents` (`id`, `author_id`, `title`,
`blocks`, `timestamps`). It backs the `VisualEditorPost` model used by the
default `/editor` route and a handful of tests. Production apps that
register their own resource models can ignore the table — it stays empty
and never grows.

---

## See also

- [Blade component reference](blade-component.md) — attribute-by-attribute
- [Custom blocks](custom-blocks.md) — extending what authors can insert
- [Renderers](renderers.md) — getting saved content back onto the page
