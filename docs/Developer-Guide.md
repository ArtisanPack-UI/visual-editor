# Developer Guide

This page is the entry point for developers extending or integrating with the visual editor. It's a roadmap, not a reference — each section links into the deeper docs.

For a first-time install and a working post, start with [[Quick Start]].

---

## What you can extend

The visual editor exposes extension points at every layer of the stack.

| Layer | Extension point | Reference |
|-------|-----------------|-----------|
| **Content** | Add an editable resource model | [[Content Model]] |
| **Content** | Register a resource via filter | [[Hooks and Events#ap-visual-editor-resources]] |
| **Blocks** | Author a custom block (static or dynamic) | [[blocks/Custom Blocks]] |
| **Blocks** | Per-breakpoint values on a custom control | [[blocks/Responsive Design Tools]] |
| **Blocks** | Per-state overrides on a custom control | [[blocks/State Design Tools]] |
| **Site editor** | Register templates / parts / patterns / menus at runtime | [[Hooks and Events#ap-visual-editor-templates-template-parts-patterns-navigation]] |
| **Site editor** | Bind a custom access gate | [[site-editor/Access Gate]] |
| **Renderers** | Override a block partial (Blade) | [[Renderers#1-blade-renderer]] |
| **Renderers** | Register a client renderer (React / Vue) | [[Renderers]] |
| **Media** | Swap the media-library bridge | [[Post Editor#5-media-library-integration]] |
| **Auth** | Change the API middleware stack | [[Configuration#api]] |
| **Loginout** | Rewrite the login/logout envelope | [[Hooks and Events#ap-visual-editor-loginout-envelope]] |
| **Editor chrome** | Restyle through DaisyUI tokens | [[post-editor/Theming]] |

---

## Architectural overview

```text
┌────────────────────────────────────────────────────────────────┐
│  Host Laravel application                                      │
│  ├── config/artisanpack/visual-editor.php  (resource map)      │
│  ├── App\Models\Post  (HasBlockContent trait)                  │
│  └── Blade view  <x-visual-editor :model="$post" />            │
└────────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌────────────────────────────────────────────────────────────────┐
│  artisanpack-ui/visual-editor                                  │
│  ├── REST: /visual-editor/api/{resource}/{id}/content          │
│  ├── REST: /visual-editor/api/{templates,patterns,menus,...}   │
│  ├── React editor (Gutenberg + DaisyUI chrome)                 │
│  ├── Site-editor shell (/visual-editor/site)                   │
│  └── 42 forked blocks under artisanpack/*                      │
└────────────────────────────────────────────────────────────────┘
                            │
                ┌───────────┴───────────┐
                ▼                       ▼
┌─────────────────────────┐   ┌─────────────────────────────────┐
│  cms-framework (paired) │   │  Renderer packages              │
│  ├── Post / Page models │   │  ├── renderer-blade  (PHP)      │
│  ├── Templates, parts,  │   │  ├── renderer-react  (npm)      │
│  │   patterns, menus,   │   │  └── renderer-vue    (npm)      │
│  │   global styles      │   └─────────────────────────────────┘
│  └── site.* settings    │
└─────────────────────────┘
```

The post editor surface boots inside a `<div data-ap-visual-editor>` mount point. Block content is persisted to the host's Eloquent models via the REST surface; site-editor entities are persisted to cms-framework's models when the pair is installed.

Both packages are loosely coupled — every cms-framework hook into the editor is guarded by `class_exists(\ArtisanPackUI\VisualEditor\VisualEditor::class)`, and the editor's site-editor surface is guarded by a fail-closed `SiteEditorAccessGate`. Each remains usable on its own.

---

## Common integration tasks

### Add an editable model

1. Add the `HasBlockContent` trait to the model.
2. Add a JSON column (default name: `content`).
3. Register the slug → class mapping in `config/artisanpack/visual-editor.php`.
4. Mount `<x-visual-editor :model="$model" />` in your edit view.

Full walkthrough: [[Quick Start]].

### Author a custom block

1. Create `resources/js/visual-editor/blocks/{your-block}/` with `block.json`, `edit.tsx`, `index.ts`, and optionally `save.tsx`.
2. Call `VisualEditor::registerBlock()` from your service provider.
3. Ship a renderer partial / component in each renderer your front-end consumes.

Full walkthrough: [[blocks/Custom Blocks]].

### Render saved content on the public site

Pick the renderer matching your stack:

- **Blade / Livewire / Volt** — `<x-ve-blocks :tree="$post->getBlockContent()" />`
- **React (Inertia or SPA)** — `<BlockTree tree={blocks} />`
- **Vue (Inertia or SPA)** — `<BlockTree :tree="blocks" />`

Full reference: [[Renderers]].

### Bind a site-editor access gate

```php
// AppServiceProvider::register()
$this->app->bind(
    \ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate::class,
    \App\SiteEditor\MyGate::class,
);
```

Full contract and recipes: [[site-editor/Access Gate]].

### Swap the media library

```ts
import { registerArtisanpackMediaBridge } from '@artisanpack-ui/visual-editor';
import { MediaModal, uploadMedia } from '@your-org/media-library';

registerArtisanpackMediaBridge(MediaModal, uploadMedia);
```

Then point `media.adapter` in the config to a server-side adapter that converts your store's media records into Gutenberg-shape attachments. Full contract: [[Post Editor#5-media-library-integration]].

---

## Testing your integration

### PHP

The package ships against Pest 3 + Orchestra Testbench. Inside the package:

```bash
./vendor/bin/pest
```

In your host app, test custom blocks and renderers with regular Laravel feature tests against the REST endpoints. The `ResourceResolver` is container-bound, so you can swap it in tests.

### JavaScript

The editor's React tree uses Vitest + jsdom. Custom block tests live next to the block:

```text
resources/js/visual-editor/blocks/my-block/
├── block.json
├── edit.tsx
├── edit.test.tsx
└── index.ts
```

Run with `npm test`.

### Renderer parity

The three renderers must produce equivalent HTML for the same block tree. Add a fixture entry under `packages/renderer-parity.json` whenever you ship a custom block in more than one renderer, then run:

```bash
npm run verify:parity
```

---

## Where to dig deeper

- [[Content Model]] — How `HasBlockContent`, the resource map, and policies interact
- [[Configuration]] — All configuration keys
- [[Hooks and Events]] — Every filter, action, and browser event
- [[Post Editor]] — Post-editor surface tour
- [[Site Editor]] — Site-editor surface tour
- [[Blocks]] — Block library overview
- [[Renderers]] — Public-site rendering for Blade / React / Vue
- [[Troubleshooting]] — Things that go wrong and how to fix them
