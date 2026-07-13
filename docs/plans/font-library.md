# Feature: Font Library & Font Management

## Problem Statement

The visual editor currently has no first-class way to manage web fonts. Users have to hand-edit theme CSS or drop external `<link>` tags to load Google Fonts, which bypasses the block typography pipeline, breaks GDPR compliance (fonts loaded from Google CDN), and can't be reused across themes. WordPress's Site Editor Font Library is the reference UX; we want the same first-class treatment inside ArtisanPack UI, but built on our existing Registry + hooks patterns and extensible to any provider.

## Target User

- **Site admins / designers** managing typography in the Site Editor who want to browse, install, and use web fonts without touching code.
- **Package developers** who want to register additional font sources (Adobe Fonts, Fontshare, internal libraries) without patching this package.

## User Stories

- As a site admin, I want to open a Font Library modal from the Site Editor and browse Google Fonts and Bunny Fonts so I can pick fonts by preview.
- As a site admin, I want to install a font by picking specific weights/styles so I don't download files I won't use.
- As a site admin, I want installed fonts to appear in the typography panel font-family dropdown for every block.
- As a site admin, I want fonts to be self-hosted automatically so I stay GDPR-compliant and my site works offline.
- As a site admin, I want to upload my own `.woff2`/`.ttf`/`.otf` files for licensed or custom fonts.
- As a theme author, I want the fonts my theme depends on to ship as a "font bundle" so the theme is portable.
- As a package developer, I want to implement a `FontProvider` interface and register it via `FontSourceRegistry` so my source shows up alongside Google/Bunny with no core changes.
- As a site owner, I want a dedicated `manage_fonts` capability so I can grant font management independently of full Site Editor access.

## Scope

### In scope (v1)

- Global site-wide **Font Library** (installed fonts) with theme-scoped **font bundles** that reference library entries.
- `FontProvider` interface + `FontSourceRegistry` extensibility layer (mirrors `BlockTypeRegistry` conventions), seeded via `ap.visual-editor.register-font-sources` filter hook.
- First-party providers: **Google Fonts** and **Bunny Fonts**.
- **Self-hosted storage**: on install, provider fetches `.woff2` files to the configured storage disk. No CDN linking in v1.
- **Custom font upload** (`.woff2`, `.woff`, `.ttf`, `.otf`) — treated as a synthetic `custom` provider.
- **Per-font weight/style selection** at install time (400, 700, italic, etc.).
- **Variable font support** — parse axes (`wght`, `slnt`, `wdth`, etc.) from font metadata, expose axis ranges in the picker.
- **Font Library modal** — browse/search catalog, live preview with editable sample text, install with weight selection, **bulk install/uninstall**.
- **Typography panel font picker** — populated from installed fonts, applied via existing block style pipeline.
- **Generated `fonts.css`** — rebuilt on install/uninstall/upload, contains all `@font-face` rules; enqueued in both editor iframe and public site. Exposes CSS custom properties + preset slugs consumable by the block style pipeline (theme.json-style presets).
- **`manage_fonts` capability** — new capability + policy, grantable independently of the Site Editor cap.
- **GDPR notice** — inline notice in the library modal when installing from remote providers, confirming self-hosting is on.

### Out of scope (v1 — deferred)

- **Font subsetting** by character set (Latin, Cyrillic, etc.) — install downloads the full subset the provider ships.
- CDN / remote linking mode — always self-host in v1.
- Font optimization (WOFF2 → smaller subsets, unicode-range trimming beyond what providers ship).
- Font pairing recommendations / AI suggestions.
- Import/export font bundles as `.zip` (theme bundles reference library entries only).
- Icon fonts (out of scope — handled by `artisanpack-ui/icons`).

## Behavior

### Happy path

1. Admin opens Site Editor → clicks **Styles → Typography → Manage Fonts**. Font Library modal opens.
2. Modal shows tabs: **Installed**, **Google Fonts**, **Bunny Fonts**, **Upload**, plus any provider registered via the registry.
3. Admin searches "Inter" in the Google tab. Provider returns catalog results with a live preview rendered in the browser using the provider's preview stylesheet (loaded only for the modal session).
4. Admin clicks **Add** on Inter → weight/style modal opens showing available weights (100–900 + italic variants). Admin selects 400, 500, 700 + 400-italic.
5. Admin clicks **Install**. Backend job (or sync request) fetches `.woff2` files from Google to the storage disk, records a `Font` row + `FontFace` rows per weight/style, regenerates `fonts.css`.
6. Modal closes. The typography panel font-family dropdown now includes "Inter" and every block's font-family control can select it. The editor iframe reloads `fonts.css` and previews accurately.
7. On the public site, `fonts.css` is enqueued once in `<head>`; blocks referencing Inter render with the correct `@font-face` rules already present.

### Font bundles (theme-scoped)

- A theme declares its **font bundle** in its theme manifest (list of `{provider, family, faces[]}`).
- On theme activation, missing library entries are installed automatically (with admin confirmation for provider fetches).
- Uninstalling a theme leaves library entries intact (they're global).

### Edge cases

- **Provider network failure during install**: rollback the DB row, remove partial files, surface a user-visible error with retry.
- **Font already installed at a different weight set**: adding new weights merges into the existing `Font`; removing weights that a block references warns before delete.
- **Uploaded file with invalid format**: reject at validation with a clear message; supported list documented.
- **`manage_fonts` capability missing on the current user**: modal opens read-only (browse allowed, install/upload/delete disabled) with an explanation.
- **Variable font uploaded without axis metadata**: fall back to treating it as a static font at its default weight.
- **Provider returns a remote-only license (no downloadable file)**: surface a "not self-hostable" message; block installation in v1.
- **Bulk uninstall of a font a block references**: warn with the list of affected blocks/pages; require confirmation.
- **Regenerating `fonts.css` fails mid-write**: keep the previous file in place; log and surface an error.

## Acceptance Criteria

- [ ] `FontProvider` interface published in `src/Fonts/Contracts/` with methods for catalog listing, search, family detail, and file fetching.
- [ ] `FontSourceRegistry` registers providers and is seeded via `ap.visual-editor.register-font-sources` filter.
- [ ] Google Fonts and Bunny Fonts providers register themselves in the package service provider.
- [ ] Installing a font from any provider downloads files to the configured disk and creates DB rows.
- [ ] A single generated `fonts.css` file is rebuilt after every install/uninstall/upload and is enqueued in both the editor iframe and the public site.
- [ ] Installed fonts appear in every block's typography font-family control via the existing preset pipeline.
- [ ] Bulk install/uninstall works from the modal.
- [ ] Custom fonts can be uploaded as `.woff2`, `.woff`, `.ttf`, `.otf`.
- [ ] Variable font axes are parsed and exposed to the picker.
- [ ] Live preview with editable sample text works in the modal.
- [ ] `manage_fonts` capability gates all mutating actions; missing capability → read-only modal.
- [ ] GDPR notice is visible in the modal for any remote provider.
- [ ] Theme font bundles install missing library entries on theme activation.
- [ ] All new user-facing strings are wrapped with `__()` / `trans_choice()`.
- [ ] Tests cover: provider registration, install/uninstall lifecycle, `fonts.css` regeneration, capability gating, variable font parsing, bulk operations.

## Implementation Notes

### New directory: `src/Fonts/`

Mirrors existing subsystem shape (`src/SiteEditor/`, `src/Registries/`, `src/MediaBridge/`):

- `src/Fonts/Contracts/FontProvider.php` — interface.
- `src/Fonts/Registries/FontSourceRegistry.php` — provider registry (follows `BlockTypeRegistry` conventions).
- `src/Fonts/Providers/GoogleFontsProvider.php`
- `src/Fonts/Providers/BunnyFontsProvider.php`
- `src/Fonts/Providers/CustomUploadProvider.php`
- `src/Fonts/Services/FontInstaller.php` — orchestrates provider fetch → storage → DB.
- `src/Fonts/Services/FontFileWriter.php` — writes to configured disk.
- `src/Fonts/Services/FontsCssGenerator.php` — rebuilds `fonts.css` bundle.
- `src/Fonts/Services/VariableFontMetadataParser.php` — reads axis metadata from font files.
- `src/Fonts/Services/ThemeFontBundleResolver.php` — applies theme bundles on activation.
- `src/Fonts/Http/Controllers/FontLibraryController.php` — REST endpoints for the modal.
- `src/Fonts/Http/Requests/InstallFontRequest.php`, `UploadFontRequest.php`.
- `src/Fonts/Models/Font.php`, `FontFace.php`, `ThemeFontBundle.php`.
- `src/Fonts/Policies/FontPolicy.php`.
- `src/Fonts/Exceptions/{FontProviderException,FontFileWriteException,VariableFontMetadataException}.php`.

### Database

New migrations under `database/migrations/`:

- `fonts` — `id, provider, family, slug, is_variable, license, source_url, installed_at`. Unique `(provider, slug)`.
- `font_faces` — `id, font_id, weight, style, format, disk, path, file_size, axes (json, nullable)`.
- `theme_font_bundles` — `id, theme_slug, font_id, faces (json)`.

### JS / editor UI (`resources/js/visual-editor/fonts/`)

- `FontLibraryModal.tsx` — tabbed modal, catalog browsing, previews, bulk actions.
- `FontFamilyPicker.tsx` — dropdown wired into the block typography panel.
- `FontPreview.tsx` — editable sample text component.
- `providers/` — thin JS adapters mirroring the PHP providers for browse/preview (fetch from our REST endpoints, not third-party APIs directly).
- Store slice for installed fonts + selected preview state.

### Routes / API

Add to `routes/` (follow existing REST pattern):

- `GET /visual-editor/fonts` — list installed fonts.
- `GET /visual-editor/fonts/sources` — list registered providers.
- `GET /visual-editor/fonts/sources/{provider}/catalog?q=…` — browse/search a provider.
- `POST /visual-editor/fonts` — install (body: `{provider, family, faces[]}`).
- `POST /visual-editor/fonts/upload` — upload custom font file.
- `POST /visual-editor/fonts/bulk-uninstall` — bulk delete.
- `DELETE /visual-editor/fonts/{font}` — uninstall single font.

All mutating routes gated by `FontPolicy` / `manage_fonts` capability.

### Config

Add to `config/visual-editor.php`:

```php
'fonts' => [
    'disk' => 'public',
    'path' => 'visual-editor/fonts',
    'css_path' => 'visual-editor/fonts/fonts.css',
    'providers' => [
        'google' => ['enabled' => true, 'api_key' => env('VE_GOOGLE_FONTS_API_KEY')],
        'bunny' => ['enabled' => true],
    ],
    'capability' => 'manage_fonts',
    'upload' => [
        'max_file_size' => 5120, // KB
        'allowed_mime_types' => ['font/woff2', 'font/woff', 'font/ttf', 'font/otf'],
    ],
],
```

### Dependencies

- No new PHP packages required for Google/Bunny (both expose REST/HTTPS endpoints usable with Laravel's HTTP client).
- Variable font metadata parsing: evaluate a lightweight OTF/TTF table reader; if none fits our style guidelines, ship a minimal reader in `VariableFontMetadataParser` handling just `fvar` (axes) + `name` table entries needed.
- JS: reuse existing `@artisanpack-ui/react` primitives where possible; note memory rule — avoid `Popover` / `Dropdown` from that package (they freeze the editor); use inline patterns.

### GDPR

Font Library modal shows a persistent notice explaining that any font installed from a remote provider is fetched once to local storage and then served from this site — no runtime requests to Google/Bunny CDN. If a provider is later added that requires runtime CDN linking, `FontProvider::isSelfHostable()` returns `false` and the installer refuses with a clear message.

### Documentation

- New page: `docs/fonts.md` — user-facing guide.
- New page: `docs/font-providers.md` — developer guide for `FontProvider` + `FontSourceRegistry`.
- Update `docs/site-editor.md` to reference the Font Library entry point.
- Update `docs/Hooks-and-Events.md` with the new filter hook.

## Open Questions

- Google Fonts REST API requires an API key. Confirm whether we ship a shared demo key (rate-limited) or require the site owner to provide one — currently planned as env-driven (`VE_GOOGLE_FONTS_API_KEY`).
- Whether `fonts.css` regeneration should run in a queued job by default (large installs) or synchronously (simpler UX for small installs). Current plan: sync, with a config toggle for async.
