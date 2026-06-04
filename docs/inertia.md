# Inertia embedding recipes

The visual editor is a React app under the hood, so embedding inside
Inertia is just a matter of mounting the Blade component on the Inertia
page and letting the React tree boot inside it. The same recipe works
inside Inertia + React (direct) and Inertia + Vue (via the Vue wrapper).

Both recipes assume the editor is being mounted inside an Inertia page
that the host app owns — the host controls auth, navigation, and
non-editor chrome; the editor takes over the canvas region.

---

## 1. Inertia + React

The Blade `<x-visual-editor />` mount works inside an Inertia-rendered
template because Inertia hydrates inside an outer Blade layout. Mount the
editor as a top-level page or inside a content slot, and import the
editor's React bundle from the host's entrypoint.

### Server-side: the Inertia page

```php
// app/Http/Controllers/PostEditorController.php
public function edit(Post $post)
{
    return Inertia::render('Posts/Edit', [
        'post' => [
            'id'             => $post->id,
            'title'          => $post->title,
            'slug'           => $post->slug,
            'status'         => $post->status,
            'previewUrl'     => route('posts.show', $post),
        ],
    ]);
}
```

### Client-side: the React page

```tsx
// resources/js/Pages/Posts/Edit.tsx
import { useEffect, useRef } from 'react';
import { mountVisualEditor } from '@artisanpack-ui/visual-editor';

export default function PostEditor({ post }) {
    const mountRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!mountRef.current) return;

        const editor = mountVisualEditor(mountRef.current, {
            resource: 'posts',
            id: String(post.id),
            apiBase: '/visual-editor/api',
            initialTitle: post.title,
            initialSlug: post.slug,
            initialStatus: post.status,
            previewUrl: post.previewUrl,
            supports: { excerpt: true, featuredImage: true },
        });

        return () => editor.unmount();
    }, [post.id]);

    return (
        <Layout>
            <div ref={mountRef} className="ap-visual-editor" />
        </Layout>
    );
}
```

The `mountVisualEditor(el, config)` helper is the imperative equivalent
of the Blade component — pass the same config keys (camelCase). It returns
an `{ unmount, save, getBlocks }` handle.

### Bridging editor events

The same `ve:editor:*` events the Blade component dispatches fire here.
Listen in the page component:

```tsx
useEffect(() => {
    const onSave = (event: CustomEvent) => {
        if (event.detail.resource !== 'posts') return;
        if (event.detail.id !== String(post.id)) return;
        // Sync Inertia state, show toast, etc.
    };

    window.addEventListener('ve:editor:save', onSave as EventListener);
    return () => window.removeEventListener('ve:editor:save', onSave as EventListener);
}, [post.id]);
```

### Inertia navigation and `wire:ignore`-equivalent

Inertia doesn't have `wire:ignore` — when navigating between Inertia
pages, the React tree unmounts entirely. The cleanup function in the
`useEffect` handles this; the editor's autosave debounce flushes before
unmount.

To preserve unsaved edits during programmatic navigation, gate
`Inertia.visit()` on the editor's `getBlocks()` returning a clean state:

Inside the `useEffect`, hold the editor handle in a ref so it survives
between renders:

```tsx
const editorRef = useRef<ReturnType<typeof mountVisualEditor>>();

useEffect(() => {
    if (!mountRef.current) return;
    editorRef.current = mountVisualEditor(mountRef.current, { /* …config */ });
    return () => editorRef.current?.unmount();
}, [post.id]);

const handleNavigate = (href: string) => {
    if (editorRef.current?.isDirty()) {
        if (!confirm('You have unsaved changes. Leave?')) return;
    }
    Inertia.visit(href);
};
```

---

## 2. Inertia + Vue

The Vue wrapper at `@artisanpack-ui/visual-editor/vue` exports a
`<VisualEditor>` Vue component that wraps the React editor in a Vue tree
(the editor itself stays React; Vue manages mount/unmount + prop
reactivity).

### Server-side: same Inertia page

The controller is identical to the React recipe — Inertia doesn't care
which client renderer the page uses.

### Client-side: the Vue page

```vue
<!-- resources/js/Pages/Posts/Edit.vue -->
<script setup lang="ts">
import { VisualEditor } from '@artisanpack-ui/visual-editor/vue';

defineProps<{
    post: {
        id: number;
        title: string;
        slug: string;
        status: string;
        previewUrl: string;
    };
}>();

const onSave = (detail) => {
    // Sync Inertia state, show toast, etc.
};

const onChange = (detail) => {
    // Edits in-flight — mark dirty, defer navigation, etc.
};
</script>

<template>
    <Layout>
        <VisualEditor
            resource="posts"
            :id="String(post.id)"
            :initial-title="post.title"
            :initial-slug="post.slug"
            :initial-status="post.status"
            :preview-url="post.previewUrl"
            :supports="{ excerpt: true, featuredImage: true }"
            @save="onSave"
            @autosave="onSave"
            @change="onChange"
        />
    </Layout>
</template>
```

The Vue component re-emits `ve:editor:*` browser events as Vue
component events (`save`, `autosave`, `change`) so you can wire them
declaratively.

### Vue → React data flow

Props on `<VisualEditor>` map 1:1 to the React editor's config. Changing
a prop after mount re-renders the React tree with the new config — but
the editor doesn't reload content on a re-render. Change `:id` or
`:resource` and the editor will detect it and fetch the new entity.

---

## 3. Front-end rendering

The Inertia + React / Inertia + Vue recipes above are for the **edit
surface**. Rendering saved content on the public site uses the renderer
packages:

- React: `@artisanpack-ui/visual-editor-renderer-react`
- Vue: `@artisanpack-ui/visual-editor-renderer-vue`

```tsx
// resources/js/Pages/Posts/Show.tsx
import { BlockTree, GlobalStyles } from '@artisanpack-ui/visual-editor-renderer-react';

export default function PostShow({ post }) {
    return (
        <>
            <GlobalStyles />
            <BlockTree tree={post.blocks} />
        </>
    );
}
```

See [Renderers](renderers.md) for the full client renderer API.

---

## 4. SSR

The editor itself is client-only (it relies on `window` and DOM APIs).
Inertia SSR will skip the editor's render pass — wrap the mount in a
`<ClientOnly>` (Inertia + Vue) or guard the `useEffect` (Inertia + React)
so server rendering produces an empty mount point.

Front-end **renderers** work in SSR — the React renderer renders to
static HTML, the Vue renderer renders to static markup. Use the Blade
renderer when you want SSR via Laravel itself (Inertia or not).

---

## 5. Authentication

The editor's API calls inherit the host page's session cookies — no
extra wiring needed when Inertia uses the standard `web` middleware. For
Sanctum / API-token auth, override the API middleware:

```php
// config/artisanpack/visual-editor.php
'api' => [
    'middleware' => ['api', 'auth:sanctum'],
],
```

---

## See also

- [Blade component reference](blade-component.md) — the underlying mount contract
- [Renderers](renderers.md) — public-site rendering
- [Livewire](livewire.md) — alternative embedding inside a server-rendered Blade page
