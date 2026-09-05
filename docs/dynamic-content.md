# Dynamic Content

Dynamic Content lets authors reference and reuse centrally-managed pieces of content — business info, team records, testimonials, whole snippets of block markup — inside visual-editor blocks without leaving the flow. Data lives in **cms-framework**'s Dynamic Content module (see `artisanpack-ui/cms-framework` v2.4+); this page describes the **editor UX** side that lives in visual-editor.

Introduced in v1.4 (issue #650).

## Concepts

### Tokens

A Dynamic Content **token** is a dotted-path reference to a field on a registered source:

- `business_info.phone` — the `phone` field on the `business_info` singleton source.
- `team[0].name` — the `name` field on record 0 of the `team` collection source.
- `team.name` — implicit index 0 (equivalent to `team[0].name`), also the "current record" reference inside a `<Dynamic Loop>` block.

Tokens can appear:

- **Inline in RichText** — `{{business_info.phone}}` inside a paragraph. Autocomplete fires on `{{`; the raw token persists in the block content and is resolved at render time by cms-framework's `DynamicContentResolver`.
- **As a block-attribute binding** — a token in `block.bindings.<attr>.args.token` resolves through the `dynamic_content` binding source. Example on an Image block:

  ```json
  {
    "name": "artisanpack/image",
    "attrs": { "url": "", "alt": "" },
    "bindings": {
      "url": { "source": "dynamic_content", "args": { "token": "business_info.logo" } },
      "alt": { "source": "dynamic_content", "args": { "token": "business_info.logo_alt" } }
    }
  }
  ```

### Sources

Sources are defined in cms-framework via `apRegisterDynamicContentType()` (code) or the DC admin (DB). Each source is either a **singleton** (`business_info` — one bag of fields) or a **collection** (`team` — an ordered list of records with the same field schema).

VE reads the merged code + DB universe through `GET /visual-editor/api/dynamic-content/sources`.

### Snippets

A **snippet** is a reusable block tree stored in VE's own `ve_snippets` table. Placements of `artisanpack/snippet` reference the snippet by slug; edits to the source snippet propagate to every placement. Snippets are always-synced in v1 — there is no local override.

### Loop block

`artisanpack/dynamic-loop` iterates a collection source and renders its inner-block template once per record. Bindings inside the template that reference the loop's source (e.g. `team.name` under a `team` loop) resolve to the current record's value through the `EXTRAS_INDEX_KEY` scope.

## Editor UX

### `{{` autocomplete

Typing `{{` inside any RichText field opens an autocompleter grouped by source. Fuzzy matches source label, field label, and token slug. Suppressed inside `artisanpack/code` and `artisanpack/preformatted` (and their `core/*` equivalents) so tokens never fire inside literal-content blocks.

### Token Inserter modal

Every text-bearing block (paragraph, heading, list-item, quote, pullquote, verse) gains a **BlockControls** toolbar button (icon `editor-code`) that opens a modal with search, source grouping, per-token type badge, and a live preview of the resolved value. Insert appends the wrapped `{{token}}` to the block's content.

Advanced: the same modal is available as a slash-command variation `/token` on the block inserter.

### Chip decoration

The `artisanpack/dynamic-content-chip` RichText format decorates every `{{token}}` occurrence with a `.ve-dc-chip` span showing the resolved preview. Unresolved tokens surface as `[Missing: token]`. The chip is presentational — the raw `{{token}}` is what persists.

### Link picker → Dynamic Content tab

`<ArtisanPackLinkControl>` (`resources/js/visual-editor/dynamic-content/link-control.tsx`) wraps `__experimentalLinkControl` with a second **Dynamic Content** tab listing DC URL / email / phone / address fields. Picking a field writes a scheme-appropriate raw-token href:

- email → `mailto:{{source.field}}`
- phone → `tel:{{source.field}}`
- URL / address / text → bare `{{source.field}}`

The whole href flows through cms-framework's `DynamicContentResolver` at render, so the token is rewritten to its resolved value. The field list and the href helpers (`buildDynamicContentHref`, `schemeForFieldType`, `DynamicContentLinkPicker`) live in `dynamic-content/dynamic-link-picker.tsx` — free of any `@wordpress/block-editor` import so surfaces that must not pull the block-editor bundle can reuse them.

The tab is wired into three seams:

- **Inline RichText link popover** — the built-in `core/link` format edit is swapped for a Dynamic-Content-aware fork (`formats/dynamic-link/`, registered by `registerDynamicLinkFormat()` in the editor bootstrap). Select text in a paragraph / heading / list item, click the toolbar **Link** button (or press <kbd>⌘</kbd>/<kbd>Ctrl</kbd>+<kbd>K</kbd>), and switch to the **Dynamic Content** tab. The fork re-registers under the `core/link` name with the same tag / attribute mapping, so existing `<a>` content round-trips and the serialized output is unchanged.
- **Button block** — the inline link popover uses `<ArtisanPackLinkControl>` directly (`blocks/button/edit.tsx`). This complements the Button block's sidebar Dynamic Content binding panel; the inline flow is now consistent with prose links.
- **Navigation link picker** — the Custom URL branch exposes a collapsible **Use Dynamic Content** field list (`site-editor/navigation/link-picker.tsx`) that writes the token href into the menu item's `url`.

This replaces the earlier manual workaround (typing `mailto:{{business_info.email}}` / `tel:{{business_info.phone}}` directly into the URL field), which still works but is no longer necessary.

### Image block → Dynamic Content binding

The Image block InspectorControls now shows a **Dynamic Content** panel listing image fields. Selecting one binds `url`, `alt`, and `id` attributes to the DC source. Clearing removes all three bindings.

### Snippet block

`artisanpack/snippet` — attribute `slug`. Editor renders inline as a read-only preview with an "Edit snippet →" link into the Snippets admin.

### Dynamic Loop block

`artisanpack/dynamic-loop` — attribute `collection`. The block's inner tree is the per-record template; the editor renders one iteration inline (against the first record's values). SSR walks every record.

## HTTP endpoints

Prefixed by `/visual-editor/api`, guarded by the same middleware stack (`api`, `auth` by default).

| Method | URI | Purpose |
|--------|-----|---------|
| `GET`  | `/dynamic-content/sources` | List DC types + fields (feeds inserter/autocomplete). |
| `POST` | `/dynamic-content/resolve` | Batched resolve. Body: `{ tokens: string[] }` (≤200). Response: `{ values: { token: value } }`. Missing/unresolved → `null`. |
| `GET`  | `/snippets` | List snippets (optional `?search=`). |
| `POST` | `/snippets` | Create. Body: `{ slug, title?, blocks? }`. Cycle detection runs before the write. |
| `GET`  | `/snippets/{id}` | Read one. |
| `PUT`  | `/snippets/{id}` | Update. |
| `DELETE` | `/snippets/{id}` | Delete. Returns 204. |

## Admin surface

`/visual-editor/admin/snippets` — gated by `SiteEditorAccessGate`. Standalone Blade page (no Vite bundle) that lists, creates, and deletes snippets against the JSON API above.

Field / source management still lives in cms-framework's admin — this package only manages **snippets**.

## Cycle guard

`SnippetCycleGuard` walks a snippet's block tree at save time and again on render. Any placement whose target slug is already in the enclosing visited-set fails the write with a 422 error (editor surfaces it inline) and renders as a `[Snippet cycle detected: "slug"]` placeholder on SSR. Nesting is capped at `SnippetCycleGuard::MAX_DEPTH = 32`.

## SSR

The Blade renderer (`packages/visual-editor-renderer-blade`) resolves bindings once at the top of the render call via `BlockRenderer::resolveBindings()`. When cms-framework or the bindings layer is absent, the resolver silently no-ops and blocks render with their static attrs.

Dynamic blocks that need their inner tree at render time (like `artisanpack/dynamic-loop` iterating a template) implement `ArtisanPackUI\VisualEditor\Blocks\WantsInnerBlocks` — the renderer forwards the tree via `renderWithInner()` instead of the plain `render()`.

## Standalone / soft-dep behavior

- **Without cms-framework:** the `dynamic_content` binding source registers but resolves to `null`; the sources endpoint returns `{ sources: [] }`; snippet CRUD still works (snippets can hold any static block tree without DC bindings).
- **Without the Blade renderer:** the editor UX still works; SSR bindings resolution is a no-op.

## Registering a source (in cms-framework)

```php
apRegisterDynamicContentType( 'business_info', [
    'name'        => 'Business Info',
    'cardinality' => \ArtisanPackUI\CMSFramework\Modules\DynamicContent\Enums\Cardinality::Singleton,
    'fields'      => [
        [ 'slug' => 'phone', 'label' => 'Phone',  'type' => 'phone' ],
        [ 'slug' => 'logo',  'label' => 'Logo',   'type' => 'image' ],
    ],
] );
```

Fields become tokens immediately — no VE restart needed.

## Naming note

The block-bindings source name on the wire is `dynamic_content` (snake_case, to satisfy the registry's `/^[a-z][a-z0-9_]*$/` pattern), not the friendlier `artisanpack/dynamic-content` label used in the issue prose.

## Registering a source from a host app (v1.9+)

**Since v1.9 (#762).** Hosts, packages, plugins, and themes can register their own token sources without going through cms-framework's DB-authored types. Use `VisualEditor::registerDynamicContentSource()`:

```php
use ArtisanPackUI\VisualEditor\Facades\VisualEditor;

VisualEditor::registerDynamicContentSource( [
    'slug'        => 'business',
    'label'       => 'Business',
    'cardinality' => 'singleton', // or 'collection'
    'fields'      => [
        [ 'slug' => 'name',  'label' => 'Name',  'type' => 'text' ],
        [ 'slug' => 'phone', 'label' => 'Phone', 'type' => 'phone' ],
    ],
    'resolver'    => static function (): array {
        return [
            'name'  => config( 'business.name' ),
            'phone' => config( 'business.phone' ),
        ];
    },
] );
```

### Definition shape

| Key           | Type            | Notes |
|---------------|-----------------|-------|
| `slug`        | string          | Lowercase snake_case. Required. |
| `label`       | string          | Defaults to the slug. |
| `cardinality` | string          | `singleton` (one bag of fields) or `collection` (list of rows). Required. |
| `fields`      | array           | List of `{slug, label, type, description?}`. |
| `resolver`    | callable        | Returns the source's data — a `array<string, mixed>` (or `null`) for singletons; a `list<array<string, mixed>>` for collections. |
| `description` | string          | Optional prose for the inserter panel. |
| `icon`        | string          | Optional icon slug for the inserter panel. |

### Precedence

Host sources merge with cms-framework's DB-authored types in the editor's inserter and the render-time binding resolver. On a slug collision the host registration wins — an app can intentionally shadow a cms-framework type.
