# Fonts

Status: **v1.7.0** — Issue [#627](https://github.com/ArtisanPack-UI/visual-editor/issues/627)

The **Font Library** is the site editor's front door to font management. From a
single modal you can browse Google Fonts and Bunny Fonts, upload your own font
files, install the weights and styles you need, and remove the ones you don't.
Every installed font is **self-hosted** — the files are downloaded once to your
own storage and served from your domain, so visitors never hit a third-party
CDN.

Once a font is installed it appears in every typography control in the editor
(global styles and per-block/per-element), so picking it is the same as picking
any theme preset.

> Extending the library with your own catalog — implementing the `FontProvider`
> contract and registering it through the `ap.visualEditor.registerFontSources`
> filter — is covered in [[Font Providers]]. This page is the user-facing guide.

---

## Opening the Font Library

The library opens from any font-family picker in the site editor:

1. Open the site editor (`/visual-editor/site`) and select a **Typography**
   control — either the global **Styles → Typography** panel or the typography
   section of any block or element's style panel.
2. In the font-family dropdown, click **"Manage fonts…"**.

A single modal instance backs every picker, so it opens the same way no matter
which control you launch it from. The button is always visible; if your account
cannot manage fonts the modal opens in read-only mode (see
[Permissions](#permissions)).

---

## The modal at a glance

The modal is organized into tabs:

| Tab | What it does |
|-----|--------------|
| **Installed** | Lists every font already installed on the site. Select one or more and uninstall them in bulk. |
| **Google Fonts** | Browse and search the Google Fonts catalog, preview a family, and install the weights/styles you want. |
| **Bunny Fonts** | The same catalog experience for [Bunny Fonts](https://fonts.bunny.net) — a GDPR-first, drop-in Google Fonts alternative. |
| **Custom Upload** | Upload your own font files (see [Uploading fonts](#uploading-fonts)). |
| *(other providers)* | Any source a package adds through the [`ap.visualEditor.registerFontSources`](Hooks-and-Events.md) filter gets its own tab here. |

Each provider tab can be disabled from configuration, in which case its tab
never appears — see [Configuration](#configuration).

---

## Installing fonts from a provider

On a provider tab (Google Fonts or Bunny Fonts):

1. **Browse or search.** The tab opens on the provider's default listing. Type
   in the search box to filter, and use **Load more** to page through results.
2. **Preview.** Each family shows a live preview rendered from the provider's
   actual faces. The preview is served **same-origin** — the modal streams the
   preview face through your own app rather than pointing a `<link>` at the
   provider's CDN — so nothing about the preview reaches a third party either.
   You can edit the sample text to see how a family looks with your own copy.
3. **Pick weights and styles.** Expand a family to choose which faces to install
   (for example 400 / 700, normal / italic). Variable fonts expose their axes.
4. **Install.** The editor fetches the selected face files server-side, stores
   them on your configured disk, and rebuilds the site's `fonts.css`. A progress
   indicator runs during the fetch and a confirmation shows when it completes.

Installed families are **global** to the site — they are available to every
theme and every entity, and are not tied to whichever theme was active when you
installed them.

---

## Uploading fonts

The **Custom Upload** tab lets you bring your own font files — useful for
licensed or bespoke typefaces that aren't in any catalog.

1. Enter the **family name** as it should appear in the editor.
2. Add one or more **face files**. Each uploaded face is registered at weight
   400, normal style; a variable font keeps its full axis range, read from the
   file itself.
3. Upload.

**Accepted formats:** `.woff2`, `.woff`, `.ttf`, `.otf`.
**Size limit:** each face file is capped at **5 MB** (5120 KB) by default, and
all faces in one upload together at **25 MB** (25600 KB).

These limits are configurable (`fonts.upload.extensions`,
`fonts.upload.max_kilobytes`, and `fonts.upload.max_total_kilobytes`). Uploaded
files are validated by their actual
file signature — not just the extension — before they are stored, and any
variable-axis metadata is read directly from the file. Like fetched fonts,
uploads are self-hosted on your configured disk.

---

## Using installed fonts

Every installed font is exposed to the editor as a font-family preset. In any
typography control, installed families appear in the font-family dropdown
alongside your theme's presets. Selecting one stores a
`var(--wp--preset--font-family--{slug})` custom property, which resolves against
the generated `fonts.css` on both the editor canvas and the public site — no
block or theme changes required.

If you need a value that isn't in the list, the **Custom…** option in the picker
still accepts a raw font stack (for example `system-ui, sans-serif`).

---

## Theme font bundles

A theme can declare the fonts it depends on so they're recognized when the theme
is activated. Declare them in a top-level `fonts` block of the theme's
`theme.json`, listing each family's provider and faces:

```json
{
  "fonts": [
    {
      "provider": "google",
      "family": "Inter",
      "faces": [
        { "weight": "400", "style": "normal" },
        { "weight": "700", "style": "normal" }
      ]
    }
  ]
}
```

On activation the `ThemeFontBundleResolver` links any of those families that are
**already installed** into the theme's bundle and records them. Whether missing
families are fetched automatically depends on configuration:

- **`fonts.bundles.auto_install` off (default)** — missing families are skipped
  on activation. Install them later from the Font Library once you're ready to
  confirm the network fetch.
- **`fonts.bundles.auto_install` on** — missing families are fetched from their
  provider during activation.

Deactivating or switching themes leaves installed families in place; they're
global and shared across the site.

---

## GDPR and self-hosting

The Font Library is built so that using a hosted catalog like Google Fonts does
**not** expose your visitors to that provider. This matters for GDPR: linking a
visitor's browser directly to a font CDN transmits their IP address to the
provider, which German and other EU courts have treated as a violation without
consent. Self-hosting removes that particular third-party request — it is one
piece of GDPR compliance, not the whole of it.

- **Self-hosting is the default and only mode.** Installing a font downloads its
  face files **once, server-side**, to your configured disk. Your pages then
  serve those files from your own domain. Visitors never connect to the
  provider's CDN — not for installed fonts, and not even for the in-modal
  previews, which are streamed through your app.
- **The modal says so.** Every remote-provider tab carries a persistent notice:

  > Fonts installed from *[provider]* are downloaded once to this site and
  > served locally, so your visitors' browsers never connect to the provider's
  > CDN.

- **Non-self-hostable providers are refused.** A provider advertises whether its
  faces can be fetched and stored locally via `FontProvider::isSelfHostable()`.
  If a provider returns `false`, the library will not install from it in this
  version — its install controls are disabled and the tab shows:

  > *[provider]* does not support self-hosting, so its fonts cannot be installed
  > in this version.

  The built-in Google Fonts, Bunny Fonts, and Custom Upload sources are all
  self-hostable.

---

## Permissions

Browsing the library is open to any authenticated user who can reach the site
editor. Every **mutating** action — installing, uploading, uninstalling, and
bulk-uninstalling — is gated by a capability, `manage_fonts` by default
(`fonts.capability`). It is checked by `FontPolicy` against the user's
`hasCapability()` method (falling back to RBAC permission checks), independently
of whichever `SiteEditorAccessGate` governs the rest of the site editor.

A user without the capability can still open the modal and browse both installed
fonts and provider catalogs, but every mutating control is disabled and a banner
explains why. The read-only state the UI shows is only a mirror — the server
enforces the same gate and returns a `403` for mutating requests, so the
capability is the real boundary.

---

## Configuration

All Font Library settings live under the `fonts` key of `config/visual-editor.php`:

| Key | Default | Purpose |
|-----|---------|---------|
| `fonts.disk` | `public` (env `VE_FONTS_DISK`) | Storage disk that self-hosted face files are written to. |
| `fonts.path` | `visual-editor/fonts` | Directory on the disk for face files. |
| `fonts.css_path` | `visual-editor/fonts/fonts.css` | The single generated stylesheet, rebuilt on every install/uninstall. |
| `fonts.capability` | `manage_fonts` | Capability required for mutating actions. |
| `fonts.bundles.auto_install` | `false` (env `VE_FONTS_BUNDLE_AUTO_INSTALL`) | Fetch a theme bundle's missing families on activation. |
| `fonts.providers.google.enabled` | `true` | Show the Google Fonts source. |
| `fonts.providers.bunny.enabled` | `true` | Show the Bunny Fonts source. |
| `fonts.providers.custom.enabled` | `true` | Show the Custom Upload source. |
| `fonts.upload.max_kilobytes` | `5120` | Per-face upload size cap, in KB. |
| `fonts.upload.extensions` | `woff2, woff, ttf, otf` | Accepted upload container formats. |

Neither built-in remote provider needs an API key — both browse keyless
endpoints. Each provider block also carries connection settings (`subset`,
`per_page`, `cache_ttl`, `timeout`, and the catalog/CSS URLs) so a host can
repoint a provider at a mirror or tune its caching; see the annotated block in
`config/visual-editor.php` for the full reference.

---

## REST API surface

The modal is a thin client over a REST surface handled by `FontLibraryController`
(under the visual-editor API prefix). Reads stay open and carry a `read_only`
flag; mutating actions are gated by `manage_fonts`.

| Method + path | Route name | Purpose |
|---------------|------------|---------|
| `GET fonts` | `visual-editor.api.fonts.index` | List installed fonts. |
| `GET fonts/sources` | `visual-editor.api.fonts.sources.index` | List registered providers. |
| `GET fonts/sources/{provider}/catalog` | `visual-editor.api.fonts.sources.catalog` | Browse/search a provider (`q`, `page`). |
| `GET fonts/sources/{provider}/preview/{slug}` | `visual-editor.api.fonts.sources.preview` | Same-origin `@font-face` preview stylesheet. |
| `GET fonts/sources/{provider}/preview/{slug}/{weight}/{style}` | `visual-editor.api.fonts.sources.preview-face` | Stream one preview face through the app. |
| `POST fonts` | `visual-editor.api.fonts.store` | Install selected faces. |
| `POST fonts/upload` | `visual-editor.api.fonts.upload` | Upload custom font files. |
| `POST fonts/bulk-uninstall` | `visual-editor.api.fonts.bulk-uninstall` | Uninstall several fonts at once. |
| `DELETE fonts/{font}` | `visual-editor.api.fonts.destroy` | Uninstall a single font. |

---

## See also

- [[Font Providers]] — implement `FontProvider` and register a custom catalog
- [[Hooks and Events]] — the `ap.visualEditor.registerFontSources` filter
- [[Site Editor]] — the surface the Font Library is launched from
- [[Configuration]] — the full `config/visual-editor.php` reference
