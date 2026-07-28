# Installation Guide

This guide covers installing the visual editor from scratch — Composer install, peer dependencies, configuration, and a smoke check that the editor mounts.

For a working post in five minutes, see [[Quick Start]].

---

## 1. Requirements

- PHP **8.2** or higher
- Laravel **12.0** or higher
- Node 18+ and a Vite-based asset pipeline
- `tailwindcss` **^4.0.0** loaded at the page level
- `daisyui` **^5.0.0** loaded at the page level

The editor UI is built on [`@artisanpack-ui/react`](https://www.npmjs.com/package/@artisanpack-ui/react), which is styled with DaisyUI on top of Tailwind. Both packages must be present in the host application's asset pipeline — the editor inherits them from the host page.

---

## 2. Install the package

```bash
composer require artisanpack-ui/visual-editor
php artisan migrate
```

The migration creates the legacy `ve_contents` table used by the package's fallback `VisualEditorPost` model and the demo `/editor` route. Host-app resource models you register later don't touch this table — they store their block content on their own.

---

## 3. Install the cms-framework pair (recommended)

The site editor surface (templates, parts, global styles, navigation, patterns) is hard-coupled to [`artisanpack-ui/cms-framework`](https://github.com/ArtisanPack-UI/cms-framework). Pairing the two packages also unlocks editable `Post` / `Page` content out of the box, `core/site-*` blocks backed by real site settings, and seeded `visual_editor.*` permissions.

```bash
composer require artisanpack-ui/cms-framework
php artisan migrate
```

The two packages ship as a version pair (v1.x ↔ v1.x). See [[home#version-compatibility]] for the support matrix.

You can skip this step — the editor works standalone for the post-editor surface — but you'll have to wire entity blocks (`core/post-*`, `core/site-*`, etc.) and the site-editor entities yourself.

---

## 4. Publish the configuration (optional)

```bash
php artisan vendor:publish --tag=artisanpack-visual-editor-config
```

This drops `config/artisanpack/visual-editor.php` into your host app. Edit it to register resources, swap the API middleware, tune the block allow/deny lists, change global-styles theme/schema pinning, or configure breakpoints and interactive states.

The full configuration reference lives in [[Configuration]].

---

## 5. Build assets

Two consumption patterns are supported. Pick the one that fits your host app's asset pipeline.

### Pattern A — host runs Vite (default)

The editor JS bundle is registered automatically by the package's Vite plugin. From the host app:

```bash
npm install
npm run dev      # development
# or
npm run build    # production
```

The editor mounts on every page that contains a `[data-ap-visual-editor]` element. Use this when the host already owns a Vite pipeline and you want editor sources to hot-reload during development.

### Pattern B — serve pre-built bundles from `vendor/`

Since v1.5.2 the Composer tarball ships pre-built editor bundles under `vendor/artisanpack-ui/visual-editor/dist/editor/`:

```
vendor/artisanpack-ui/visual-editor/dist/editor/
├── visual-editor.js
├── site-editor.js
├── sandbox.js
└── chunks/
    ├── gutenberg-*.js
    ├── editor-app-*.js
    └── …
```

Host apps that don't want Node in their asset pipeline (Keystone CMS uses this pattern — see #678) can serve those files directly through a Laravel controller. Route `/visual-editor/{file}` and `/visual-editor/chunks/{file}` to a controller that streams from `base_path('vendor/artisanpack-ui/visual-editor/dist/editor/')`, then point the editor's Blade views at those URLs instead of the Vite-managed asset paths.

`dist/lib/visual-editor.js` (the library entry from `package.json`'s `exports` map) also ships in the tarball, so NPM consumers that resolve the package via Composer's git-tarball install path see the same built artefacts as NPM installers.

**Sourcemaps.** Sourcemaps are stripped from the Composer tarball to keep it lean (~18 MB vs. ~48 MB with maps). Each release attaches a `dist-sourcemaps-vX.Y.Z.tar.gz` archive to the GitHub Release page — download and extract it into `vendor/artisanpack-ui/visual-editor/dist/` if you need to debug production bundles.

**Path-repository / dev-app installs.** When the package is installed via Composer's `path` repository (this dev app, or any sibling checkout), `dist/` reflects your local `npm run build` output — the release-workflow-baked bundles never enter your working tree. If a path-installed consumer needs the pre-built bundles, run `npm run build:lib && npm run build` inside the package checkout.

---

## 6. Confirm the install

A minimal smoke check:

1. Run `php artisan serve` (or `composer run dev` if you're inside the dev app).
2. Visit `/editor` — the package ships a demo edit screen against the fallback `VisualEditorPost` model.
3. Insert a Paragraph block, type, and watch the autosave indicator fire.
4. Reload — content persists.

If the editor doesn't appear, see [[Troubleshooting#1-editor-doesn-t-appear]].

---

## 7. Register your first resource

A "resource" is any Eloquent model whose content is editable via the visual editor. Once you've confirmed the install works, follow [[Quick Start]] to register a `Post` model, mount the editor on a real edit screen, and ship your first post.

---

## 8. Optional: media library bridge

The editor's media picker routes through Gutenberg's `MediaUpload` hook. By default a stub is registered that does nothing — useful for the dev sandbox or apps that haven't shipped a media library yet.

Host apps that ship [`artisanpack-ui/media-library`](https://github.com/ArtisanPack-UI/media-library) (or any compatible store) call `registerArtisanpackMediaBridge()` before mounting the editor:

```ts
import { registerArtisanpackMediaBridge } from '@artisanpack-ui/visual-editor';
import { MediaModal, uploadMedia } from '@artisanpack-ui/media-library';

registerArtisanpackMediaBridge(MediaModal, uploadMedia);
```

See [[Post Editor#5-media-library-integration]] for the bridge contract and how to swap in a different media library.

---

## 9. Optional: open the site editor

If cms-framework is installed, the site editor shell is already mounted at `/visual-editor/site`. The default access gate fails closed — every request returns a 503 view until you bind a permissive gate.

See [[site-editor/Getting Started]] for the access-gate setup and a tour of the site-editor surface.

---

## Where to go next

- [[Quick Start]] — Ship your first post end-to-end
- [[Configuration]] — Full configuration reference
- [[Content Model]] — `HasBlockContent`, resource map, and authorization
- [[post-editor/Blade Component]] — `<x-visual-editor />` attribute reference
- [[Troubleshooting]] — Things that commonly go wrong
