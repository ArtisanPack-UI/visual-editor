# core-data shim — surface and endpoint contract

The visual editor aliases `@wordpress/core-data` to an in-repo shim
(`resources/js/visual-editor/vendor/core-data-shim.ts`). The original
shim (#312) returned empty/no-op for every selector — enough to keep
the post-editor compiling while every data-backed core block lived on
`disabled_blocks`. B1 (#353) expands the shim to real storage, edits,
and mutation surfaces for the five entities the site editor (Phase C/D)
and the post-editor block re-enablement (Phase E) depend on.

This doc is the **contract Phase C codes against.** Every row in the
table names a REST endpoint the shim expects to exist at
`{apiBase}/...`, the HTTP method, and the request / response shape. B1
implements all of the client-side wiring; C1–C5 build the endpoints.
Until C lands, every resolver falls back silently to empty state.

## Entities

The shim registers these five entities by default via `addEntities()`:

| Kind       | Name                | `baseURL`         | Key  | Notes                                   |
| ---------- | ------------------- | ----------------- | ---- | --------------------------------------- |
| `postType` | `wp_template`       | `/templates`      | `id` | Template tree root.                     |
| `postType` | `wp_template_part`  | `/template-parts` | `id` | Header / footer / sidebar / etc.        |
| `postType` | `wp_navigation`     | `/navigation`     | `id` | Nav menu (content + locations).         |
| `postType` | `wp_block`          | `/patterns`       | `id` | Synced + unsynced patterns.             |
| `root`     | `globalStyles`      | `/global-styles`  | `id` | Singleton theme.json-shaped record.     |

Host applications can register additional entities at runtime with
`dispatch('core').addEntities([...])`.

The API base is configured at editor bootstrap with
`configureCoreDataShim({ apiBase })`; defaults to `/visual-editor/api`.

## Selectors

`getEntityRecord` and `getEntityRecords` are wired to resolvers that
dispatch `fetchEntityRecord` / `fetchEntityRecords` on first read for
each `(kind, name, id|query)` tuple (G0 / #395). Subsequent reads hit
the cache. Resolution metadata is exposed via `@wordpress/data`'s
auto-supplied `hasFinishedResolution(selectorName, args)` /
`isResolving(selectorName, args)` selectors so consumers (the
template-part placeholder picker, archives inserters, `core/post-*`
edit components, etc.) can render real loading states.

Other selectors read from the Redux store directly — they never
trigger fetches. Imperative fetches via the thunk actions
(`fetchEntityRecord`, `fetchEntityRecords`) remain available for
callers that need to refresh a tuple outside the resolver lifecycle
(saves, list invalidation, etc.).

| Selector                                        | Returns                                   | Purpose                                                   |
| ----------------------------------------------- | ----------------------------------------- | --------------------------------------------------------- |
| `getEntities()`                                 | `readonly EntityConfig[]`                 | Enumerate registered entities.                            |
| `getEntityConfig(kind, name)`                   | `EntityConfig \| null`                    | Look up a registered entity config.                       |
| `getEntityRecord(kind, name, id)`               | `EntityRecord \| null`                    | Cached single record.                                     |
| `getRawEntityRecord(kind, name, id)`            | `EntityRecord \| null`                    | Raw (unsanitized) record — same as `getEntityRecord` in the shim. |
| `getEntityRecords(kind, name, query?)`          | `readonly EntityRecord[]`                 | Cached record list. `undefined` query → all cached items. |
| `getEntityRecordsTotalItems(kind, name, query?)`| `number`                                  | From `meta.total` on list response.                       |
| `getEntityRecordsTotalPages(kind, name, query?)`| `number`                                  | From `meta.last_page` on list response.                   |
| `getEditedEntityRecord(kind, name, id)`         | `EntityRecord \| null`                    | Base record merged with any pending edits.                |
| `getEntityRecordEdits(kind, name, id)`          | `EntityRecord \| null`                    | The pending-edits bag for a record.                       |
| `getEntityRecordNonTransientEdits(…)`           | `EntityRecord \| null`                    | Alias of the above — the shim has no transient edits.     |
| `hasEditsForEntityRecord(kind, name, id)`       | `boolean`                                 | Whether any edits are staged for this record.             |
| `isSavingEntityRecord(kind, name, id)`          | `boolean`                                 | Save in flight.                                           |
| `isDeletingEntityRecord(kind, name, id)`        | `boolean`                                 | Delete in flight.                                         |
| `getLastEntitySaveError(kind, name, id)`        | `unknown \| null`                         | Last save-error payload.                                  |
| `getLastEntityDeleteError(kind, name, id)`      | `unknown \| null`                         | Last delete-error payload.                                |
| `__experimentalGetCurrentGlobalStylesId()`      | `EntityKey \| null`                       | Singleton global-styles record id.                        |
| `__experimentalGlobalStylesBaseStyles()`        | `Record<string, unknown> \| null`         | theme.json-shaped base styles (schema + defaults).        |

### Resolution-tracking selectors

`hasFinishedResolution(selectorName, args)`, `hasStartedResolution`, and
`isResolving(selectorName, args)` are auto-supplied by `@wordpress/data`
once resolvers are registered (G0 / #395). For `getEntityRecord` /
`getEntityRecords` / `getEditedEntityRecord` they reflect real fetch
lifecycle state — first read flips `isResolving` to `true`, the
resolver fetches, then `hasFinishedResolution` flips to `true` and the
cache is populated. Selectors without a registered resolver
(`getCurrentTheme`, `canUser`, etc.) report `hasFinishedResolution =
false` indefinitely; callers that need a real "resolved" signal for
those should not gate on it.

### Permissions stubs

`canUser(action, resource)` and `canUserEditEntityRecord` return
`true` in the shim — the editor route is already gated by the
package's auth middleware, so any user with editor access is treated
as having full CRUD on the entities the editor manages. Real
fine-grained permissions land in G5 (cms-framework #98); until then,
do not use these selectors as a security boundary.

### Back-compat stubs

`getCurrentUser`, `getUsers`, `getMedia`, `getMediaItems`,
`getAutosaves`, `getAutosave`, and `getReferenceByDistinctEdits`
retain the M2 stub behavior (always null / empty). Site-editor
features that need real values here should land them in a dedicated
follow-up.

## Actions + mutators

Dispatched via `dispatch('core').<name>(...)` (or directly inside a
thunk as `dispatch.<name>(...)`).

### Reducer actions (synchronous)

| Action                                                            | Effect                                                   |
| ----------------------------------------------------------------- | -------------------------------------------------------- |
| `addEntities(entities)`                                           | Registers additional entity configs.                     |
| `receiveEntityRecords(kind, name, records, query?, total?, pages?)` | Caches records. When `query !== undefined`, also caches the query→ids mapping. |
| `removeEntityRecord(kind, name, id)`                              | Evicts a record and its edits.                           |
| `editEntityRecord(kind, name, id, edits)`                         | Merges edits into the record's edits bag.                |
| `clearEntityRecordEdits(kind, name, id)`                          | Drops the edits bag.                                     |
| `setEntitySaving(kind, name, id, saving, error?)`                 | Saving flag + last save error.                           |
| `setEntityDeleting(kind, name, id, deleting, error?)`             | Deleting flag + last delete error.                       |
| `receiveGlobalStylesBase(styles)`                                 | Sets `__experimentalGlobalStylesBaseStyles`.             |
| `receiveCurrentGlobalStylesId(id)`                                | Sets `__experimentalGetCurrentGlobalStylesId`.           |
| `reset()`                                                         | Resets the store (tests / HMR).                          |

### Thunk actions (async)

| Thunk                                                   | REST call                                      | Success behavior                                      | Failure behavior                                                    |
| ------------------------------------------------------- | ---------------------------------------------- | ----------------------------------------------------- | ------------------------------------------------------------------- |
| `fetchEntityRecord(kind, name, id)`                     | `GET {apiBase}{baseURL}/{id}`                  | `receiveEntityRecords(…, [record])`                   | Returns `null`; cache unchanged.                                    |
| `fetchEntityRecords(kind, name, query?)`                | `GET {apiBase}{baseURL}?{query}`               | `receiveEntityRecords(…, records, query, total, pages)` | Caches an empty result at the query key; returns `[]`.            |
| `saveEntityRecord(kind, name, record)`                  | `POST {apiBase}{baseURL}` (no key) or `PUT {apiBase}{baseURL}/{id}` | `receiveEntityRecords(…, [saved])`; returns `saved`.  | `setEntitySaving(…, false, error)`; returns `null`.                 |
| `saveEditedEntityRecord(kind, name, id)`                | `PUT {apiBase}{baseURL}/{id}` with merged edits. | `clearEntityRecordEdits`; returns `saved`.            | Edits retained; `setEntitySaving(…, false, error)`; returns `null`. |
| `deleteEntityRecord(kind, name, id)`                    | `DELETE {apiBase}{baseURL}/{id}`               | `removeEntityRecord`; returns `true`.                 | `setEntityDeleting(…, false, error)`; returns `false`.              |

## Endpoint contract (for Phase C)

All routes sit under the package's existing `/visual-editor/api/`
prefix and use the auth middleware stack declared in
`config/artisanpack/visual-editor.php`.

### Templates — `wp_template`

- `GET  /visual-editor/api/templates`
- `GET  /visual-editor/api/templates/{id}`
- `POST /visual-editor/api/templates`
- `PUT  /visual-editor/api/templates/{id}`
- `DELETE /visual-editor/api/templates/{id}`

Single-record shape (to be finalized in C1):

```json
{
  "id": 1,
  "slug": "single-post",
  "title": { "rendered": "Single Post" },
  "description": "Fallback template for posts.",
  "content": { "raw": "<!-- wp:post-content /-->", "blocks": [] },
  "status": "publish",
  "theme": "artisanpack-base",
  "type": "wp_template",
  "source": "theme|custom",
  "origin": "theme|plugin|custom"
}
```

List response accepts either a bare array or Laravel's `{ data, meta }`
envelope (`meta.total` → `getEntityRecordsTotalItems`; `meta.last_page`
→ `getEntityRecordsTotalPages`).

### Template parts — `wp_template_part`

- `GET    /visual-editor/api/template-parts`
- `GET    /visual-editor/api/template-parts/{id}`
- `POST   /visual-editor/api/template-parts`
- `PUT    /visual-editor/api/template-parts/{id}`
- `DELETE /visual-editor/api/template-parts/{id}`

Single-record shape (finalized in C2):

```json
{
  "id": 10,
  "slug": "header",
  "title": { "rendered": "Header" },
  "content": { "raw": "<!-- wp:site-title /-->", "blocks": [] },
  "area": "header|footer|sidebar|uncategorized",
  "theme": "artisanpack-base",
  "type": "wp_template_part"
}
```

### Navigation — `wp_navigation`

- `GET    /visual-editor/api/navigation`
- `GET    /visual-editor/api/navigation/{id}`
- `POST   /visual-editor/api/navigation`
- `PUT    /visual-editor/api/navigation/{id}`
- `DELETE /visual-editor/api/navigation/{id}`

Single-record shape (finalized in C4):

```json
{
  "id": 3,
  "slug": "primary-nav",
  "title": { "rendered": "Primary" },
  "content": { "raw": "<!-- wp:navigation-link /-->", "blocks": [] },
  "status": "publish",
  "menu_order": 0
}
```

Menu locations and fallback menus attach through a sibling endpoint
(`/menu-locations`) — scope defer to C4 or D4.

### Patterns — `wp_block`

- `GET    /visual-editor/api/patterns`
- `GET    /visual-editor/api/patterns/{id}`
- `POST   /visual-editor/api/patterns`
- `PUT    /visual-editor/api/patterns/{id}`
- `DELETE /visual-editor/api/patterns/{id}`

Single-record shape (finalized in C5). The `synced` flag distinguishes
synced patterns (reusable blocks) from unsynced (one-shot inserts).

```json
{
  "id": 42,
  "slug": "hero",
  "title": { "rendered": "Hero" },
  "content": { "raw": "<!-- wp:cover /-->", "blocks": [] },
  "synced": true,
  "categories": ["featured"],
  "status": "publish"
}
```

#### Synced vs. unsynced rendering (E3)

The two pattern types reach the saved block tree by different paths, so
the front-end renderers (Blade, React, Vue) treat them differently:

- **Synced patterns** (`synced: true`) — the editor inserts a
  `core/block` reference, e.g. `{ "name": "core/block", "attributes":
  { "ref": 42 } }`, into the saved tree. Editing the pattern record
  propagates everywhere. At render time the renderers resolve the
  reference against the pattern API (PHP: `PatternInliner` —
  `src/Resources/PatternInliner.php`; JS: `inlinePatterns()` —
  `packages/visual-editor-renderer-react/src/patterns.ts` and the Vue
  twin). Resolution happens **before** the renderer walks the tree, so
  a single recursive pass produces the final HTML. Missing patterns,
  cycles, and depth-overflow conditions stamp a synthetic
  `_resolutionError` attribute that the registered `core/block`
  renderer surfaces as an empty wrapper in production and a
  `data-ve-resolution-error` / HTML comment in development.
- **Unsynced patterns** (`synced: false`) — the editor parses
  `pattern.content.raw` at insert time and pastes the resulting block
  list directly into the target (see
  `inserter-patterns-panel.tsx::patternBlocks`). The two diverge from
  that point on. Renderers never see a `core/block` reference for an
  unsynced pattern — the tree they receive already carries the
  pattern's blocks inline, so no runtime resolution is required.

Hosts wire synced-pattern resolution by passing a `patterns` collection
to `<x-ve-blocks>` (Blade resolves automatically through Eloquent),
`<BlockTree patterns={patterns} />` / `<Template patterns={patterns} />`
(React), or the same props on the Vue twins. Pattern inlining runs
after template-part inlining so a part that itself references a synced
pattern resolves in the same pass.

### Global styles — `globalStyles`

Global styles is a **singleton** per site. The `lookup` endpoint
discovers the current id (which the shim dispatches to
`receiveCurrentGlobalStylesId`); then read/write proceed through
the normal record endpoints.

- `GET  /visual-editor/api/global-styles/lookup` — resolves current id.
- `GET  /visual-editor/api/global-styles/{id}`   — user record.
- `PUT  /visual-editor/api/global-styles/{id}`   — user record.
- `GET  /visual-editor/api/global-styles/base`   — theme defaults (dispatched to `receiveGlobalStylesBase`).

Single-record shape (finalized in C3) mirrors theme.json:

```json
{
  "id": 7,
  "version": 3,
  "settings": {
    "color": { "palette": [ /* ... */ ] },
    "typography": { "fontSizes": [ /* ... */ ] }
  },
  "styles": {
    "color": { "background": "#ffffff" },
    "elements": { "link": { "color": { "text": "#3b82f6" } } }
  }
}
```

The shim does not issue `GET /lookup` or `GET /base` itself — a
site-editor bootstrap (Phase D3) will dispatch
`receiveCurrentGlobalStylesId` and `receiveGlobalStylesBase` once the
backend endpoints land.

## CSRF + auth

Mutating requests (`POST`, `PUT`, `DELETE`) include the Laravel CSRF
token from `<meta name="csrf-token">` in an `X-CSRF-TOKEN` header, plus
`credentials: 'same-origin'` so session cookies reach the server.

## Testing

`resources/js/visual-editor/vendor/__tests__/core-data-shim.test.tsx`
covers:

- entity-registry surface (default + `addEntities`)
- record cache (receive, read, list + query keys)
- edits pipeline (edit / clear / edited-record merging)
- fetch → cache (single record, list with envelope, 404 fallback, unregistered entity)
- save round-trip (POST create, PUT update, edits-cleared-on-success, edits-retained-on-failure)
- delete + evict (204 success, 500 leaves cache in place)
- global styles (base-styles + current-id selectors, user-styles PUT)
- React hooks (provider context, stub return shapes)

Unit tests inject a custom `fetcher` via `configureCoreDataShim({ fetcher })`,
so no real HTTP is required.
