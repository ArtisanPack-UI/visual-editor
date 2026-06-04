# Blade component reference

`<x-visual-editor />` is the single Blade entry point. It renders a
`<div data-ap-visual-editor>` mount point with data-attributes that the
React editor reads on boot.

```blade
<x-visual-editor
    :model="$post"
    :initialTitle="$post->title"
    :initialSlug="$post->slug"
    :initialStatus="$post->status"
    :initialExcerpt="$post->excerpt"
    :initialFeaturedImage="['id' => 12, 'url' => '/uploads/cover.jpg', 'alt' => 'Cover']"
    :initialAuthorId="$post->author_id"
    :initialCommentsOpen="$post->comments_open"
    :authorOptions="$authors"
    :supports="['excerpt' => true, 'featuredImage' => true, 'comments' => false]"
    :previewUrl="route('posts.show', $post)"
    resource="posts"
/>
```

---

## Attributes

| Attribute | Type | Required | Default | Purpose |
|-----------|------|----------|---------|---------|
| `:model` | Eloquent `Model` | **yes** | — | The model being edited. Must use `HasBlockContent`. |
| `resource` | `string` | no | reverse-looked-up from the resource map | The slug under which the model is registered. Provide explicitly if your config maps multiple slugs to the same class (or to override). |
| `:apiBase` | `string` | no | `/visual-editor/api` | API root the editor talks to. Change if you've mounted the package routes under a custom prefix. |
| `:initialTitle` | `?string` | no | `null` | Pre-fills the document-title field in the editor topbar. |
| `:initialSlug` | `?string` | no | `null` | Pre-fills the slug field in the document panel. |
| `:initialStatus` | `?string` | no | `null` | Pre-fills the status pill (`draft`, `published`, custom values). |
| `:initialExcerpt` | `?string` | no | `null` | Pre-fills the excerpt field. Requires `supports.excerpt = true`. |
| `:initialFeaturedImage` | `?array` | no | `null` | Pre-fills the featured-image picker. Shape: `['id' => int, 'url' => string, 'alt' => ?string]`. |
| `:initialAuthorId` | `int\|string\|null` | no | `null` | Pre-selects the author. |
| `:initialCommentsOpen` | `?bool` | no | `null` | Pre-fills the comments toggle. |
| `:authorOptions` | `?array` | no | `null` | Dropdown options for the author picker. Shape: `[['value' => 1, 'label' => 'Jane'], …]`. |
| `:supports` | `?array` | no | `null` | Which document-panel fields to render. Keys: `excerpt`, `featuredImage`, `comments` (all `bool`). Omit to show everything the trait supports. |
| `:previewUrl` | `?string` | no | `null` | Front-end preview URL. Opens in a new tab from the topbar Preview button. |

The component passes its `$attributes` bag through to the root div, so
`class`, `id`, `style`, and `wire:ignore` are all forwarded.

---

## Resource resolution

If you omit `resource`, the component reverse-looks-up the model class in
`config('artisanpack.visual-editor.resources')` and uses the first
matching slug. If the class isn't in the map, it throws
`RuntimeException` at render time so misconfiguration surfaces
loudly.

Always set `resource="…"` explicitly when:

- The same model class is registered under multiple slugs.
- You want render to fail fast at template time rather than at API time.
- A future config change might add a sibling mapping.

---

## API surface used by the editor

| Method | Path | When |
|--------|------|------|
| `GET` | `{apiBase}/{resource}/{id}/content` | On mount, fetches the persisted block tree. |
| `PUT` | `{apiBase}/{resource}/{id}/content` | On autosave (debounced ~1s after the last change) and on explicit save (⌘S). |
| `POST` | `{apiBase}/blocks/preview` | When a dynamic block requests an editor preview render. |
| `POST` | `{apiBase}/query/resolve` | When a `core/query` block needs paginated results. |
| `GET` | `{apiBase}/blocks` | Once on mount, to fetch the enabled-block manifest. |

All routes run through the middleware stack in
`config('artisanpack.visual-editor.api.middleware')` (default
`['api', 'auth']`).

---

## Browser events

The editor dispatches three `CustomEvent`s on `window`:

| Event | When | `detail` shape |
|-------|------|----------------|
| `ve:editor:change` | Debounce window closes, right before autosave fires. | `{ resource, id, blocks }` |
| `ve:editor:autosave` | Debounce-triggered save returns 200. | `{ resource, id, blocks, updatedAt }` |
| `ve:editor:save` | Explicit save (⌘S or topbar) returns 200. | `{ resource, id, blocks, updatedAt }` |

`resource` and `id` match the component's `data-resource` / `data-id`
attributes, so a single listener can disambiguate multiple editors on the
same page. See [Livewire](livewire.md) and [Inertia](inertia.md) for
embedding recipes.

---

## Mounting multiple editors

Each mount is a separate React root with its own store. There's no
cross-talk between them — autosaves don't race, undo histories don't
share. Use distinct DOM ids if you need to scroll to one programmatically.

```blade
@foreach ($posts as $post)
    <x-visual-editor :model="$post" id="editor-{{ $post->id }}" />
@endforeach
```

---

## Theming and styling

The root div gets `class="ap-visual-editor"` plus any classes you pass in
via `$attributes`. Editor chrome is themed through DaisyUI tokens — see
[Theming](theming.md) for how to override colors and typography.

---

## See also

- [Content model](content-model.md) — `HasBlockContent`, resource map, policies
- [Getting started](getting-started.md) — end-to-end install
- [Livewire](livewire.md) / [Inertia](inertia.md) — embedding recipes
