---
title: Visual Editor Documentation
---

# Visual Editor Documentation

Welcome to the ArtisanPack UI Visual Editor documentation! This Laravel package brings the WordPress Gutenberg block editor to any Eloquent model and ships a full site editor for templates, template parts, global styles, navigation menus, and patterns.

## Overview

The Visual Editor is designed to give Laravel apps a first-class block-based authoring experience. It provides:

- **Post editor** — A Gutenberg-powered block editor for any Eloquent model that opts in via the `HasBlockContent` trait
- **Site editor** — WordPress-style templates, template parts, `theme.json`-backed global styles, navigation menus, and patterns; mounted at `/visual-editor/site`
- **42 forked core blocks** under the `artisanpack/*` namespace — content, media, layout, widget, entity, loop/feed, comments, and query/pagination clusters
- **Three renderer packages** — Blade (PHP, server-side), React, and Vue — for rendering saved block content on the public site
- **Custom blocks** — Authoring API for shipping your own static or dynamic blocks under any namespace
- **Responsive design tools** — Per-breakpoint values on block controls, resolved through a theme-aware breakpoint registry
- **Interactive state tools** — Per-state (hover, focus, active, disabled, custom) overrides on block controls
- **Media bridge** — Pluggable picker + upload bridge that routes Gutenberg's `MediaUpload` hook through any host media library
- **Livewire & Inertia recipes** — Drop-in embedding patterns for both stacks, plus the same browser-event contract regardless of host
- **First-class pairing** with [`artisanpack-ui/cms-framework`](https://github.com/ArtisanPack-UI/cms-framework) for Post / Page / site-meta / navigation / global-styles persistence — both packages remain usable standalone
- **Pluggable access gate** — Fail-closed `SiteEditorAccessGate` contract for the site-editor surface; bind your own to integrate with any RBAC

---

## Getting Started

- [[Installation Guide]] — Setup and configuration instructions
- [[Quick Start]] — Get up and running with a working editor in under an hour
- [[Configuration]] — Configuring the visual editor for your application

---

## Core Concepts

- [[Content Model]] — The `HasBlockContent` trait, resource map, and authorization
- [[Renderers]] — Rendering saved block content on the public site
- [[Migration]] — Migrating content into the editor (including from WordPress)
- [[Troubleshooting]] — Common problems and how to resolve them

---

## Editing Surfaces

### Post Editor

The block editor for a single resource — a post, page, or any custom Eloquent model. Mounted with a single Blade component.

- [[Post Editor]] — Surface tour and what each region does
- [[post-editor/Getting Started]] — Mount your first editor in five minutes
- [[post-editor/Blade Component]] — `<x-visual-editor />` reference
- [[post-editor/Livewire Integration]] — Embedding inside Livewire components
- [[post-editor/Inertia Integration]] — Embedding inside Inertia (React or Vue)
- [[post-editor/Theming]] — Restyling editor chrome through DaisyUI tokens

### Site Editor

The surface for editing site-wide chrome — templates, template parts, global styles, navigation menus, and patterns. Mounted at `/visual-editor/site`. Requires [`artisanpack-ui/cms-framework`](https://github.com/ArtisanPack-UI/cms-framework).

- [[Site Editor]] — Module overview and layout tour
- [[site-editor/Getting Started]] — Mount the site editor and open the access gate
- [[site-editor/Access Gate]] — Fail-closed `SiteEditorAccessGate` contract and bundled gates
- [[site-editor/Templates]] — Template hierarchy, fallback chain, and template parts
- [[site-editor/Global Styles]] — `theme.json`-backed global styles, schema pinning, and CSS emission
- [[site-editor/Navigation]] — Menus, items, and theme-declared menu locations
- [[site-editor/Patterns]] — Synced and unsynced block patterns

### Blocks

The block library — what ships, how to author your own, and how to wire responsive + state-aware controls.

- [[Blocks]] — Block library overview and the `artisanpack/*` allow-list
- [[blocks/Custom Blocks]] — Authoring your own blocks (static and dynamic)
- [[blocks/Icon Block]] — The `artisanpack/icon` block (FA Free + admin upload)
- [[blocks/Responsive Design Tools]] — Per-breakpoint values on block controls
- [[blocks/State Design Tools]] — Per-state overrides (hover, focus, active, etc.)

---

## Developer Resources

- [[Developer Guide]] — Extending and customizing the editor
- [[Hooks and Events]] — Filters, actions, and browser events for extending functionality
- [[Renderers]] — Blade, React, and Vue renderers for the public site

---

## Surface Quick Reference

| Surface | Mount | Backed by | Status |
|---------|-------|-----------|--------|
| Post editor | `<x-visual-editor :model="..." />` | Any model using `HasBlockContent` | Stable |
| Site editor — Templates | `/visual-editor/site/templates/{slug}` | cms-framework `Template` model | Stable |
| Site editor — Template parts | `/visual-editor/site/template-parts/{slug}` | cms-framework `TemplatePart` model | Stable |
| Site editor — Patterns | `/visual-editor/site/patterns/{slug}` | cms-framework `Pattern` model | Stable |
| Site editor — Global styles | `/visual-editor/site/styles` | cms-framework `GlobalStyles` model | Stable |
| Site editor — Navigation | `/visual-editor/site/navigation/{slug}` | cms-framework `Menu` + `MenuItem` models | Stable |
| Blade renderer | `<x-ve-blocks :tree="..." />` | `artisanpack-ui/visual-editor-renderer-blade` | Stable |
| React renderer | `<BlockTree tree={...} />` | `@artisanpack-ui/visual-editor-renderer-react` | Stable |
| Vue renderer | `<BlockTree :tree="..." />` | `@artisanpack-ui/visual-editor-renderer-vue` | Stable |

---

## REST API Surface

All endpoints use the `/visual-editor/api/` prefix and run through the middleware stack in `config('artisanpack.visual-editor.api.middleware')` (default `['api', 'auth']`).

### Content
- `GET/PUT /{resource}/{id}/content` — Block content CRUD for any registered resource
- `POST /blocks/preview` — Dynamic block editor preview rendering
- `GET /blocks` — Enabled-block manifest (allow/deny lists applied)
- `POST /query/resolve` — `core/query` block resolution

### Site editor
- `GET/POST/PUT/DELETE /templates`, `/template-parts` — Templates and parts (wp_template shape)
- `GET/POST/PUT/DELETE /patterns` — Synced and unsynced patterns
- `GET /global-styles/lookup`, `GET /global-styles/base`, `GET /global-styles/css`, `GET/PUT /global-styles/{id}` — Global styles
- `GET/POST/PUT/DELETE /menus`, `/menu-items`, `GET /menu-locations` — Navigation

### Other
- `GET /attachments/{id}` — Media bridge (Gutenberg-shape attachment objects)
- `GET /search` — Link picker across all entity types
- `GET /site/{id}` — Site-meta envelope for `core/site-*` blocks

---

## Version Compatibility

The visual editor and `artisanpack-ui/cms-framework` ship as a version pair — both packages need to be present and on a compatible major version for the site-editor integration to work.

| visual-editor | cms-framework | Notes                                          |
| ------------- | ------------- | ---------------------------------------------- |
| v1.x          | v1.x          | Site-editor integration (this release)         |
| v0.x          | v0.x          | Pre-v1 — no site-editor integration            |

Bumping the major on either package without bumping the partner is unsupported.

---

## Configuration

The editor uses a single configuration file:

```php
// config/artisanpack/visual-editor.php
return [
    'resources' => [
        'posts' => App\Models\Post::class,
    ],
    'api' => [
        'middleware' => ['api', 'auth'],
    ],
    // ...
];
```

See [[Configuration]] for the full reference.

---

## Support

For issues, feature requests, and contributions:

- **GitHub**: https://github.com/ArtisanPack-UI/visual-editor
- **Documentation**: https://artisanpack.dev/packages/visual-editor

---

*This documentation covers visual-editor v1.0.0*
