# Site editor

The site editor is the surface for editing site-wide chrome — templates,
template parts, global styles, navigation menus, and patterns — without
touching post content. It mounts at `/visual-editor/site` and uses the
same Gutenberg block-editor primitives as the post editor, wrapped in a
custom shell that owns the navigation, tab structure, and entity
switching.

The site editor requires `artisanpack-ui/cms-framework` to be installed —
templates, template parts, global styles, patterns, and navigation menus
are persisted by cms-framework's models. When cms-framework is missing,
the install gate (#432) surfaces a "cms-framework required" page instead
of mounting the editor.

---

## 1. Layout

```text
┌─────────────────────────────────────────────────────────────────────┐
│  Topbar: Site · entity title · Save · Preview                      │
├─────────────────────┬───────────────────────────────────────────────┤
│                     │                                               │
│   Navigator         │            Canvas frame                       │
│   sidebar           │            (iframe + block editor)            │
│                     │                                               │
│   ── Templates      │                                               │
│   ── Template parts │                                               │
│   ── Patterns       │                                               │
│   ── Styles         │                                               │
│   ── Navigation     │                                               │
│                     │                                               │
└─────────────────────┴───────────────────────────────────────────────┘
```

- **Topbar** — entity-title, save, preview, "back to site" link.
- **Navigator sidebar** (left) — tree of all editable entities, grouped
  into five sections: Templates, Template Parts, Patterns, Styles,
  Navigation. Click an entity to load it into the canvas.
- **Canvas frame** (center) — iframe-wrapped block editor. Same component
  set as the post editor, scoped to the selected entity.

---

## 2. Access gate

Site-editor access is fail-closed. The default
`SiteEditorAccessGate` binding is `DenyByDefaultGate` — every request
returns 403 until you bind something permissive:

```php
// AppServiceProvider::register()
$this->app->bind(
    \ArtisanPackUI\VisualEditor\SiteEditor\Contracts\SiteEditorAccessGate::class,
    \App\Auth\AllowAdminsGate::class,
);
```

When cms-framework is installed it auto-binds `CmsFrameworkInstallGate`,
which:

1. Confirms cms-framework is installed and migrations have run.
2. Confirms the current user has the `visual_editor.site.edit`
   permission (or the legacy "any authenticated" baseline in V1.0).

Override the binding to integrate with your own RBAC. See
[Content model §3](content-model.md#3-policies-and-authorization) and
[Access Gate](site-editor/Access-Gate.md).

---

## 3. Sections

### Templates

Server-stored template records (`wp_template` shape) that drive
full-page rendering. The navigator lists every template the resolver
knows about — both theme-provided defaults and user overrides — and the
editor lets authors edit either.

See [Templates](site-editor/Templates.md) for the hierarchy, fallback chain, and
how the front-end renderer picks a template for a route.

### Template parts

Reusable chunks (`wp_template_part` shape) that get included into
templates via the `core/template-part` block. Headers, footers, sidebars
— anything shared across templates lives here.

See [Templates §3](site-editor/Templates.md#3-template-parts) for the
`core/template-part` contract.

### Patterns

Reusable block snippets. Two flavours:

- **Synced** — pattern lives in the pattern store; every reference
  updates when the pattern changes.
- **Unsynced** — the snippet is dropped inline at insert time; later
  edits to the pattern don't propagate.

See [Patterns](site-editor/Patterns.md).

### Styles

The theme-wide style record (theme.json-shaped). Edits here change typography,
color palette, spacing scale, layout defaults, and per-block style overrides
for every page on the site.

See [Global styles](site-editor/Global-Styles.md).

### Navigation

Menus and menu items. Each menu can be assigned to one or more theme-declared
menu locations; the `core/navigation` block resolves location → menu at
render time.

See [Navigation](site-editor/Navigation.md).

---

## 4. Canvas and entity loading

The canvas is the same iframe-wrapped block editor as the post editor.
When you select an entity in the navigator:

1. The site-editor shell dispatches `core/editor` to load the entity by id.
2. Gutenberg's core-data shim fetches the entity from the REST surface
   (`/visual-editor/api/templates/{slug}`, `/template-parts/{slug}`, etc.).
3. The canvas renders the entity's block tree.
4. Edits autosave back through the same REST endpoint.

Switching entities saves pending edits to the previous entity first
(blocking on the network round-trip), then loads the new entity.

---

## 5. URL routing

The site editor is a single Laravel route (`GET /visual-editor/site/{path?}`)
that hands off to React routing client-side. Direct links to a specific
entity work:

```text
/visual-editor/site/templates/single
/visual-editor/site/template-parts/header
/visual-editor/site/patterns/hero
/visual-editor/site/styles
/visual-editor/site/navigation/primary
```

These are shareable — bookmark or send to a colleague to jump straight
into editing that entity.

---

## 6. Preview

The topbar Preview button opens the entity's front-end rendering in a new
tab. For templates, this is the slug rendered against a representative
record (e.g. `single` → most recent post). For template parts and
patterns, it's an isolated preview page. For global styles, it's the
home page.

The preview URL is computed by `apGetSiteEditorPreviewUrl()` and can be
overridden per-section by binding your own preview resolver in
`config('artisanpack.visual-editor.site-editor.previews')`.

---

## 7. REST API surface

| Section | Endpoints |
|---------|-----------|
| Templates | `GET/POST /templates`, `GET/PUT/DELETE /templates/{slug}` |
| Template parts | `GET/POST /template-parts`, `GET/PUT/DELETE /template-parts/{slug}` |
| Patterns | `GET/POST /patterns`, `GET/PUT/DELETE /patterns/{slug}` |
| Global styles | `GET /global-styles/lookup`, `GET /global-styles/base`, `GET /global-styles/css`, `GET/PUT /global-styles/{id}` |
| Navigation | `GET/POST /menus`, `GET/PUT/DELETE /menus/{id}`, `GET/POST/PUT/DELETE /menu-items`, `GET /menu-locations` |
| Entity search | `GET /search` — backs the link-control picker across all entity types |

All under the `/visual-editor/api/` prefix, all behind the API middleware
stack.

---

## 8. Static configuration

Some site-editor entities can be declared in config rather than the
database — useful for templates and patterns that ship with the host
app's theme:

```php
// config/artisanpack/visual-editor.php
'site-editor' => [
    'templates' => [
        'single' => ['title' => 'Single Post', 'content' => '...'],
    ],
    'template-parts' => [
        'header' => ['title' => 'Header', 'content' => '...'],
    ],
    'patterns' => [
        'hero' => ['title' => 'Hero', 'content' => '...'],
    ],
    'navigation' => [
        'primary' => ['title' => 'Primary Menu'],
    ],
],
```

Static configs are merged with DB-stored user overrides via the fallback
chain — user records win on the same slug. See [Templates §4](site-editor/Templates.md#4-fallback-chain).

---

## See also

- [Templates](site-editor/Templates.md) · [Global styles](site-editor/Global-Styles.md) · [Navigation](site-editor/Navigation.md) · [Patterns](site-editor/Patterns.md)
- [Access Gate](site-editor/Access-Gate.md) — site-editor access gate contract
- [Content model](content-model.md) — `HasBlockContent` and authorization
- [Renderers](renderers.md) — render saved entities on the public site
- [Photo Grid](photo-grid.md) — container-level Photo Grid setting (group / columns / grid). theme.json defaults live under `settings.artisanpack.photoGrid` and ride the same global-styles plumbing.
