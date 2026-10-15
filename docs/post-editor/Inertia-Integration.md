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
import { mountEditor } from '@artisanpack-ui/visual-editor';

export default function PostEditor({ post }) {
    const mountRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!mountRef.current) return;

        const editor = mountEditor(mountRef.current, {
            apiBase: '/visual-editor/api',
            resource: 'posts',
            id: String(post.id),
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

`mountEditor(element, config)` is the imperative equivalent of the Blade
component. It takes the same config keys in camelCase (`apiBase`,
`resource` and `id` are required) and returns a `MountedEditor` handle:

| Member | Description |
|--------|-------------|
| `unmount()` | Unmounts the React root and frees the element. Safe to call more than once. |
| `ready` | A promise that resolves once the editor bundle has loaded and the first render is scheduled. It rejects if the bundle fails to load. |

Mounting twice on the same element (React StrictMode, HMR) is a no-op
that returns a handle for the existing editor.

### Bridging editor events

The editor dispatches `ve:editor:change`, `ve:editor:autosave` and
`ve:editor:save` on `window`. Each `detail` carries `resource`, `id` and
the current `blocks`; autosave and save also carry `updatedAt`. The
event names are exported as `VE_EDITOR_CHANGE`, `VE_EDITOR_AUTOSAVE` and
`VE_EDITOR_SAVE`. Filter on `resource` and `id` when a page mounts more
than one editor:

```tsx
import { VE_EDITOR_SAVE, type VeEditorSaveEvent } from '@artisanpack-ui/visual-editor';

useEffect(() => {
    const onSave = (event: VeEditorSaveEvent) => {
        if (event.detail.resource !== 'posts') return;
        if (event.detail.id !== String(post.id)) return;
        // Sync Inertia state, show a toast, etc.
    };

    window.addEventListener(VE_EDITOR_SAVE, onSave as EventListener);
    return () => window.removeEventListener(VE_EDITOR_SAVE, onSave as EventListener);
}, [post.id]);
```

### Inertia navigation

Inertia has no `wire:ignore`. When you navigate between Inertia pages,
the React tree unmounts entirely, and the cleanup function in the
`useEffect` above unmounts the editor.

The handle has no dirty flag. To warn before leaving with unsaved edits,
track it from the events: a `ve:editor:change` marks the page dirty, and
a `ve:editor:save` or `ve:editor:autosave` marks it clean.

```tsx
import { router } from '@inertiajs/react';
import { VE_EDITOR_AUTOSAVE, VE_EDITOR_CHANGE, VE_EDITOR_SAVE } from '@artisanpack-ui/visual-editor';

const dirty = useRef(false);

useEffect(() => {
    const isSelf = (event: Event) => {
        const detail = (event as CustomEvent<{ resource: string; id: string }>).detail;
        return detail?.resource === 'posts' && detail?.id === String(post.id);
    };
    const markDirty = (event: Event) => { if (isSelf(event)) dirty.current = true; };
    const markClean = (event: Event) => { if (isSelf(event)) dirty.current = false; };

    window.addEventListener(VE_EDITOR_CHANGE, markDirty);
    window.addEventListener(VE_EDITOR_AUTOSAVE, markClean);
    window.addEventListener(VE_EDITOR_SAVE, markClean);

    return () => {
        window.removeEventListener(VE_EDITOR_CHANGE, markDirty);
        window.removeEventListener(VE_EDITOR_AUTOSAVE, markClean);
        window.removeEventListener(VE_EDITOR_SAVE, markClean);
    };
}, [post.id]);

const handleNavigate = (href: string) => {
    if (dirty.current && !confirm('You have unsaved changes. Leave?')) return;
    router.visit(href);
};
```

---

## 2. Inertia + Vue

The Vue wrapper at `@artisanpack-ui/visual-editor/vue` exports a
`<VisualEditor>` Vue component that wraps the React editor in a Vue tree.
The editor itself stays React; Vue manages mount, unmount and prop
updates.

### Server-side: same Inertia page

The controller is identical to the React recipe. Inertia doesn't care
which client renderer the page uses.

### Client-side: the Vue page

```vue
<!-- resources/js/Pages/Posts/Edit.vue -->
<script setup lang="ts">
import { VisualEditor } from '@artisanpack-ui/visual-editor/vue';

const props = defineProps<{
    post: {
        id: number;
        title: string;
        slug: string;
        status: string;
        previewUrl: string;
    };
}>();

const onSaved = (detail) => {
    // Sync Inertia state, show a toast, etc.
};

const onChanged = (detail) => {
    // Edits in flight: mark the page dirty, defer navigation, etc.
};
</script>

<template>
    <Layout>
        <VisualEditor
            api-base="/visual-editor/api"
            resource="posts"
            :model="{
                id: post.id,
                title: post.title,
                slug: post.slug,
                status: post.status,
            }"
            :preview-url="post.previewUrl"
            :supports="{ excerpt: true, featuredImage: true }"
            @saved="onSaved"
            @autosaved="onSaved"
            @changed="onChanged"
        />
    </Layout>
</template>
```

| Prop | Required | Description |
|------|----------|-------------|
| `model` | yes | The record: `id` plus any of `title`, `slug`, `status`, `excerpt`, `featuredImage`, `authorId`, `commentsOpen`. They become the editor's `initial*` config. |
| `api-base` | yes | The editor API base, e.g. `/visual-editor/api`. |
| `resource` | yes | The resource key, e.g. `posts`. |
| `preview-url` | no | The Preview button's URL. |
| `author-options` | no | Authors for the author picker. |
| `supports` | no | Which document panels to show. |
| `can-edit-block-css`, `custom-css-allow-urls`, `block-styles` | no | *Since v1.15.0.* See below. |

The component listens for the `ve:editor:*` browser events, keeps only
the ones for its own `resource` and `model.id`, and re-emits them as Vue
events: `changed`, `autosaved` and `saved`, each with the event's
`detail`.

### Vue → React data flow

Changing `resource`, `model.id` or `api-base` after mount unmounts the
editor and mounts a fresh one for the new record. The other props are
read at mount time. To apply new values, re-key the component
(`:key="post.id"`).

### Custom CSS access and theme block styles

*Since v1.15.0.* The Blade `<x-visual-editor>` mount works out the Custom
CSS permission and the theme's block style variations itself. An Inertia
host has to pass them in, or the editor fails closed: the Custom CSS
field stays hidden and the Styles picker shows no theme variations.

| Mount config key (React) | Vue prop | Default | Value to pass |
|--------------------------|----------|---------|---------------|
| `canEditBlockCss` | `can-edit-block-css` | `false` (field hidden) | The `visual-editor.edit-block-css` gate result: `Gate::allows( BlockCssAccess::ABILITY )`. |
| `customCssAllowUrls` | `custom-css-allow-urls` | `true` | Config `effects.custom_css_allow_urls`, so the canvas previews what the front end prints. |
| `blockStyles` | `block-styles` | — | `app( BlockStyleResolver::class )->editorPayload()`: the theme's block style variations for the Styles picker. |

```php
use ArtisanPackUI\VisualEditor\BlockStyles\BlockStyleResolver;
use ArtisanPackUI\VisualEditor\Effects\BlockCssAccess;
use Illuminate\Support\Facades\Gate;

return Inertia::render('Posts/Edit', [
    'post'               => [ /* … */ ],
    'canEditBlockCss'    => Gate::allows( BlockCssAccess::ABILITY ),
    'customCssAllowUrls' => (bool) config( 'artisanpack.visual-editor.effects.custom_css_allow_urls', true ),
    'blockStyles'        => app( BlockStyleResolver::class )->editorPayload(),
]);
```

```vue
<VisualEditor
    resource="posts"
    :can-edit-block-css="canEditBlockCss"
    :custom-css-allow-urls="customCssAllowUrls"
    :block-styles="blockStyles"
    …
/>
```

`canEditBlockCss` only shows or hides the field. Every save re-checks the
gate on the server, so a client that sets it to `true` without the
ability still can't store Custom CSS. See
[Access gate](../site-editor/Access-Gate.md) for how the gate is decided.

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

Since v1.15.0, pass the theme's duotone presets
(`duotonePresets`) and the `effects.custom_css_allow_urls` setting
(`customCssAllowUrls`) to `<BlockTree>`. If the host customizes
breakpoints, also call `setBreakpoints()` with the server's
`BreakpointRegistry::toArray()` once at bootstrap. See
[Styles `<BlockTree>` prints](../renderers.md#styles-blocktree-prints) and
[Breakpoints](../renderers.md#breakpoints).

See [Renderers](../renderers.md) for the full client renderer API.

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

- [Blade component reference](Blade-Component.md) — the underlying mount contract
- [Renderers](../renderers.md) — public-site rendering
- [Livewire](Livewire-Integration.md) — alternative embedding inside a server-rendered Blade page
