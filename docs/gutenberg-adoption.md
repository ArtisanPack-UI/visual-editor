# Gutenberg adoption — V1 strategy

**Status:** In progress. See umbrella issue [`#309`](https://github.com/ArtisanPack-UI/visual-editor/issues/309) and milestones [`#310`–`#325`](https://github.com/ArtisanPack-UI/visual-editor/milestone/4).

## TL;DR

`artisanpack-ui/visual-editor` pivoted mid-Phase-2 from "rebuild every
block-editor primitive from scratch in React + Zustand + Tiptap" to **adopt
Gutenberg's React packages as a library dependency**. The WordPress runtime
is not a dependency; only the npm packages (`@wordpress/blocks`,
`@wordpress/block-editor`, `@wordpress/block-library`,
`@wordpress/components`, `@wordpress/i18n`) are. Everything that's not
Gutenberg — persistence, routing, auth, media integration, frontend
rendering, admin chrome — is Laravel-native.

## Why

- Gutenberg is the best open-source block editor available. Re-implementing
  its primitives from scratch takes years and never matches parity.
- The Phase 2 custom-React-editor work was running into a steady stream of
  UX/a11y/DnD/feature-parity issues that Gutenberg has already solved.
- Gutenberg's npm packages are independent of WordPress PHP and can be
  consumed in any React host app.

## Non-goals

- **No WordPress runtime.** No PHP WordPress, no WP REST API, no `wp_posts`
  monolith, no plugin/theme model.
- **No bidirectional WP compatibility.** One-way WordPress → Laravel content
  import is deferred to a separate companion package
  (`artisanpack-ui/visual-editor-wp-import`).
- **No Vue-native editor.** Gutenberg is React; Vue apps embed the React
  editor via a thin wrapper (M14).
- **No Gutenberg edit-post / edit-site shells.** The editor uses
  `@wordpress/block-editor` directly; the chrome is ours (M7).

## Architecture

```text
┌────────────────────────────────────────────────────────────────┐
│  Host Laravel app                                              │
│                                                                 │
│  Any Eloquent model with `HasBlockContent` trait:              │
│    Post, Page, LandingPage, Product, …                          │
│    └─ content column: json (block tree)                         │
│                                                                 │
│  Admin view (Blade / Livewire / Inertia+React / Inertia+Vue):  │
│    <x-visual-editor :model="$post" />                           │
│      │                                                          │
│      ▼                                                          │
│    React root mounts the editor                                 │
│      @wordpress/blocks         (registry)                       │
│      @wordpress/block-editor   (canvas, inserter, inspector,   │
│                                 DnD, RichText, InnerBlocks)    │
│      @wordpress/block-library  (core blocks)                    │
│      @wordpress/components     (themed to DaisyUI)              │
│      @wordpress/core-data      (local empty-state shim)         │
│      + own TopBar                                               │
│      + MediaUpload slot → artisanpack-ui/media-library          │
│      │                                                          │
│      │ JSON block tree                                          │
│      ▼                                                          │
│    REST: GET/PUT /visual-editor/api/{resource}/{id}/content    │
│          POST    /visual-editor/api/blocks/preview              │
│      │                                                          │
│      ▼                                                          │
│    PHP BlockTypeRegistry + DynamicBlock render callbacks        │
│      │                                                          │
│      ▼                                                          │
│    Laravel Scout (searchable text extracted per-block on save) │
│                                                                 │
│  Frontend renderer (any stack):                                │
│    artisanpack-ui/visual-editor-renderer-blade                  │
│    @artisanpack-ui/visual-editor-renderer-react                 │
│    @artisanpack-ui/visual-editor-renderer-vue                   │
└────────────────────────────────────────────────────────────────┘
```

## Key decisions (locked)

| Decision | Choice | Notes |
|---|---|---|
| Content format | JSON block tree (Gutenberg's in-memory shape) | No HTML-with-comments serialization |
| Content model | Polymorphic via `HasBlockContent` trait | Any Eloquent model; no `ve_posts` table |
| Gutenberg scope | `blocks`, `block-editor`, `block-library`, `components`, `i18n` | Minimal shim for `core-data`; own top bar |
| Resource routing | Laravel morph map + policies via `config/visual-editor.php` | Auto-registered REST endpoints |
| Admin embed | React editor works in Blade, Livewire (event bridge), Inertia+React, Inertia+Vue | Vue wrapper (M14) |
| Dynamic blocks | Hybrid render (server or client), closure or class registration | Generic preview endpoint |
| Block set | All `@wordpress/block-library` ships, site/post blocks disabled by default | Revisit in M5 |
| Frontend renderers | Sibling packages: Blade, React, Vue | Same JSON input |
| Bundle strategy | Lazy-load editor chunk via dynamic import | Non-editor admin pages pay 0 KB |
| Theming | Override `@wordpress/components` CSS tokens to DaisyUI/Artisanpack | Selective component swaps for Button/Modal |
| i18n | Dual-world: Gutenberg via `@wordpress/i18n`; Laravel chrome via `__()` | Custom blocks use namespaced text domain |
| Versioning | Exact pin `@wordpress/*`; Renovate grouped upgrade PRs every 2 weeks | Test before merge |
| Livewire | Event-based opt-in; no Livewire-specific package code in V1 | Documented recipe |
| Site editor | Deferred to Phase 3+ | V1 data model leaves room |

## Milestones

V1 ships 16 milestones tracked as sub-issues of [`#309`](https://github.com/ArtisanPack-UI/visual-editor/issues/309):

| # | Issue | Milestone |
|---|---|---|
| M0 | [`#310`](https://github.com/ArtisanPack-UI/visual-editor/issues/310) | Branch + issue housekeeping |
| M1 | [`#311`](https://github.com/ArtisanPack-UI/visual-editor/issues/311) | Gutenberg packages installed |
| M2 | [`#312`](https://github.com/ArtisanPack-UI/visual-editor/issues/312) | core-data shim + i18n scaffolding |
| M3 | [`#313`](https://github.com/ArtisanPack-UI/visual-editor/issues/313) | Persistence layer + Blade component |
| M4 | [`#314`](https://github.com/ArtisanPack-UI/visual-editor/issues/314) | Media bridge |
| M5 | [`#315`](https://github.com/ArtisanPack-UI/visual-editor/issues/315) | Block set audit |
| M6 | [`#316`](https://github.com/ArtisanPack-UI/visual-editor/issues/316) | Dynamic blocks API |
| M7 | [`#317`](https://github.com/ArtisanPack-UI/visual-editor/issues/317) | Own top bar |
| M8 | [`#318`](https://github.com/ArtisanPack-UI/visual-editor/issues/318) | Component theming |
| M9 | [`#319`](https://github.com/ArtisanPack-UI/visual-editor/issues/319) | Blade renderer package |
| M10 | [`#320`](https://github.com/ArtisanPack-UI/visual-editor/issues/320) | React renderer package |
| M11 | [`#321`](https://github.com/ArtisanPack-UI/visual-editor/issues/321) | Vue renderer package |
| M12 | [`#322`](https://github.com/ArtisanPack-UI/visual-editor/issues/322) | Search extraction + Scout |
| M13 | [`#323`](https://github.com/ArtisanPack-UI/visual-editor/issues/323) | Livewire documentation |
| M14 | [`#324`](https://github.com/ArtisanPack-UI/visual-editor/issues/324) | Vue admin embed wrapper |
| M15 | [`#325`](https://github.com/ArtisanPack-UI/visual-editor/issues/325) | Docs, website, release |

## Legacy work

The prior Phase 2 custom-React editor lives under
[`resources/js/visual-editor/_legacy/editor/`](../resources/js/visual-editor/_legacy/editor/)
as a reference for the transition and is deleted when V1 ships (M15). See
that directory's README for details.

Pre-Phase-2 planning documents in this `docs/` directory —
`phase-1-tiptap-strategy.md`, `phase-1-dnd-kit-findings.md`,
`phase-1-rest-api.md`, and the `plans/` folder — remain as historical
context for decisions made before the Gutenberg adoption. Their
recommendations no longer drive the active plan; the current roadmap is in
the milestone issues linked above.
