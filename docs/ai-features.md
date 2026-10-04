---
title: AI Features
---

# AI Features

The visual editor ships five optional AI-powered authoring affordances
that build on top of the [`artisanpack-ui/ai`](https://github.com/ArtisanPack-UI/ai)
foundation. All five default to *off* — hosts opt in by enabling the
corresponding feature key from the AI package's settings surface, and
each affordance honors that toggle before rendering.

Every affordance surfaces as a **suggestion** — never an automatic
mutation. Accepting a suggestion is always explicit user action, per
the AI RFC.

> **Introduced in v1.3.0.**

---

## Features

| Feature key                          | Agent                                | What it does                                                                                                                     |
|--------------------------------------|--------------------------------------|----------------------------------------------------------------------------------------------------------------------------------|
| `visual_editor.suggest_next_block`   | `ContentBlockSuggestionAgent`        | Inline "+ suggest" affordance ranks likely next blocks given the document so far.                                                |
| `visual_editor.suggest_layout`       | `LayoutSuggestionAgent`              | Given a section's content + your available pattern library, ranks matching section patterns.                                     |
| `visual_editor.heading_hierarchy`    | `HeadingHierarchyAgent`              | Audits the document for skipped levels, duplicate h1s, and ambiguous headings; returns suggested fixes.                          |
| `ai.alt_text`                        | `AltTextGenerationAgent` (from `ai`) | Suggests accessibility-friendly alt text when an image block is added or its `src` changes and `alt` is empty.                   |
| `ai.content_rewrite`                 | `ContentRewriteAgent` (from `ai`)    | Selection-toolbar / slash-command surface for "make shorter", "more formal", "reading level 6", and similar rewrites.            |

The first three agents live in this package (`src/Ai/Agents/`); the
last two are cross-cutting agents consumed directly from
`artisanpack-ui/ai` so the same prompt and feature toggle power
alt-text and rewrites across every package that opts in.

---

## Server-side surface

The features are auto-registered with the AI package's
`FeatureRegistry` via `VisualEditorServiceProvider::aiFeatures()` — no
manual wiring required. Each has a JSON endpoint under
`/visual-editor/api/ai/*` for the React editor to hit:

```
GET  /visual-editor/api/ai/features             # { features: { <key>: bool, ... } }
POST /visual-editor/api/ai/suggest-next-block
POST /visual-editor/api/ai/suggest-layout
POST /visual-editor/api/ai/alt-text
POST /visual-editor/api/ai/rewrite
POST /visual-editor/api/ai/heading-hierarchy
```

Every endpoint is backed by a dedicated Form Request in
`src/Http/Requests/Ai/` and returns a consistent error envelope. The
route group is registered only when `artisanpack-ui/ai` is installed
(an `interface_exists()` check on its `FeatureRegistry` contract), so
hosts without the AI package simply don't expose the surface — no 500s.

> **Before v1.12.1** the guard used `class_exists()` on that interface,
> which always returns `false`, so the `/ai/*` routes were never
> registered. They are registered as of v1.12.1, behind the access gate
> and throttle described below.

On top of the visual-editor API middleware stack, the `/ai/*` group
adds two middleware:

- `can:visual-editor.use-ai` — see [Access control](#access-control).
- `throttle:{ai.throttle}` — see [Rate limiting](#rate-limiting).

### Livewire component

Blade / Livewire hosts can consume the same triggers through the
shipped Livewire component `artisanpack-visual-editor.ai.tools`, which
listens for `ap-ve-ai:*` browser events and re-dispatches shaped
results as `ap-ve-ai:{feature}:{status}` events (with statuses
`success`, `invalid-input`, `disabled`, `missing-credentials`,
`budget-exceeded`, and `error`). As of v1.12.1 the listeners also
enforce the same access gate and rate limit as the HTTP routes, and
dispatch `ap-ve-ai:{feature}:forbidden` or `ap-ve-ai:{feature}:throttled`
(each with `feature` and `message`) instead of running the agent.

```blade
<livewire:artisanpack-visual-editor.ai.tools />

<script>
    document.dispatchEvent(new CustomEvent('ap-ve-ai:suggest-next-block', {
        detail: { existingBlocks, cursorPosition },
    }));

    document.addEventListener('ap-ve-ai:suggest-next-block:success', (e) => {
        const { suggestions } = e.detail;
    });
</script>
```

---

## React surface

```tsx
import {
    createAiApiClient,
    useAiFeatures,
    SuggestNextBlockButton,
    SuggestLayoutPanel,
    AltTextSuggestionCard,
    RewriteToolbar,
    HeadingHierarchyPanel,
} from '../visual-editor/ai';

const client = createAiApiClient({ apiBase: '/visual-editor/api' });
const { isEnabled } = useAiFeatures(client);

{isEnabled('visual_editor.suggest_next_block') && (
    <SuggestNextBlockButton
        client={client}
        existingBlocks={blocks}
        cursorPosition={insertionIndex}
        onPick={(suggestion) => insertBlock(suggestion.block_type)}
    />
)}
```

Full component list and per-feature hooks live in
`resources/js/visual-editor/ai/index.ts`. Each feature ships:

- A React UI component (`<SuggestNextBlockButton />`,
  `<SuggestLayoutPanel />`, `<AltTextSuggestionCard />`,
  `<RewriteToolbar />`, `<HeadingHierarchyPanel />`).
- A dedicated hook (`useSuggestNextBlock`, `useSuggestLayout`, etc.)
  for wiring the affordance into your own UI.
- A shared `useAiFeatures(client)` gate that hides the surface until
  the host has enabled the corresponding feature key.

---

## Access control

> **Since v1.12.1.**

Every AI call spends the site's AI credentials, so the AI features are
**denied by default**. The `/ai/*` routes and the `AiTools` Livewire
listeners both check the `visual-editor.use-ai` gate ability
(`ArtisanPackUI\VisualEditor\Ai\Support\AiAccess::ABILITY`).

The package defines a default gate (unless the host has already defined
one) that allows a signed-in user only when they hold the capability
named by `artisanpack.visual-editor.ai.capability` (default
`use_ai_features`). The capability is checked through the first of these
methods the user model has:

1. `hasCapability()`
2. `hasPermissionTo()`
3. `hasPermission()`

Guests, users without any of these methods, and users without the
capability are denied. Setting `ai.capability` to `''` denies everyone.

To use your own rule instead, define the gate in a service provider. A
host definition replaces the package default:

```php
use Illuminate\Support\Facades\Gate;

Gate::define( 'visual-editor.use-ai', fn ( $user ) => $user?->is_editor === true );
```

The React surface needs no changes: when `GET /ai/features` is denied,
`useAiFeatures()` keeps every feature reported as disabled, so the
affordances stay hidden.

---

## Rate limiting

> **Since v1.12.1.**

The `/ai/*` routes are throttled per user with Laravel's `throttle`
middleware, using `artisanpack.visual-editor.ai.throttle` (`"max
attempts,minutes"`, default `'20,1'` — 20 calls per minute). Route
middleware doesn't reach Livewire's update endpoint, so `AiTools`
applies the same limit itself through `RateLimiter`, keyed by user id
(or IP for guests) and shared across all of its listeners.

---

## Payload limits

> **Since v1.12.1.**

The block trees and text forwarded to the model are bounded by
`artisanpack.visual-editor.ai.payload_limits`:

| Key              | Default  | Applies to                                                                                           |
|------------------|----------|------------------------------------------------------------------------------------------------------|
| `max_blocks`     | `1000`   | Total blocks (including nested) in `existing_blocks`, `section_content`, and `blocks`.               |
| `max_depth`      | `12`     | Nesting depth of those block trees.                                                                  |
| `max_bytes`      | `131072` | Serialized JSON sent to the model by suggest next block, suggest layout (patterns included), and heading hierarchy. |
| `max_text_chars` | `20000`  | Rewrite `content` length.                                                                            |

Over-limit input is **rejected, not truncated**: the Form Requests fail
validation, and the agents throw a `FeatureError` (`invalid_input`) if
they are called directly or through Livewire. Non-positive or
non-numeric config values fall back to the defaults above.

Other fixed caps:

- Suggest layout accepts at most 200 `available_patterns`, each at most
  128 characters.
- Rewrite `intent` is at most 256 characters (HTTP and Livewire).

The heading-hierarchy check sends a stripped tree: only each block's
`id` / `clientId`, `name`, heading `level`, plain text (markup removed)
and `innerBlocks`. Styling, media attributes and editor-only state are
dropped, and non-heading text is cut to 280 characters of context.
Headings are recognised by name (`core/heading`, `artisanpack/heading`,
or bare `heading`), read from the block's `name`, `blockName`, or
`type` key, so the editor's `getBlocks()` tree, `parse_blocks()` output,
and simplified payloads all work.

---

## Alt-text image sources

> **Since v1.12.1.**

The alt-text endpoint and the Livewire listener accept only:

- A `data:image/…` URI or base64 string (up to 7,000,000 characters,
  about 5 MB of image data), or `{ "source": "base64", "value": "…" }`.
- An `http(s)` URL (or `{ "source": "url", "value": "…" }`) whose host
  is the `app.url` host or listed in
  `artisanpack.visual-editor.ai.alt_text.allowed_hosts`, on the default
  port (80 / 443) or the `app.url` port.

Server file paths (`{ "source": "path", … }` or a bare path string) are
never accepted, and the request's `Host` header is not trusted. Add
your CDN or media-disk domain to `allowed_hosts` if images are served
from elsewhere. Some AI gateways fetch the URL from your server and
follow redirects, so never list a host that has an open redirect.

---

## Error responses

| Status | When                                                                                                   | Body                                                  |
|--------|--------------------------------------------------------------------------------------------------------|-------------------------------------------------------|
| `403`  | The user fails the `visual-editor.use-ai` gate.                                                        | Laravel's authorization response.                     |
| `403`  | The feature toggle is off.                                                                             | `{ feature, error: "feature_disabled", message }`     |
| `422`  | Request validation fails (missing fields, over a payload limit, disallowed image source).              | Laravel's validation envelope (`message`, `errors`).  |
| `422`  | The agent rejects its input (e.g. the serialized payload is over `max_bytes`).                         | `{ feature, error: "invalid_input", message }`        |
| `429`  | The `ai.throttle` limit is hit.                                                                        | Laravel's throttle response (with `Retry-After`).     |
| `429`  | The AI package's monthly budget is reached.                                                            | `{ feature, error: "budget_exceeded", message }`      |
| `503`  | No AI credentials are configured.                                                                      | `{ feature, error: "missing_credentials", message }`  |
| `500`  | Any other agent failure (logged; the raw message is not returned).                                     | `{ feature, error: "internal_error", message }`       |

---

## Configuration

All keys live under `artisanpack.visual-editor.ai` (see
[Configuration](Configuration.md)):

```php
'ai' => [
    'payload_limits' => [
        'max_blocks'     => 1000,
        'max_depth'      => 12,
        'max_bytes'      => 131072,
        'max_text_chars' => 20000,
    ],
    'capability' => 'use_ai_features',
    'throttle'   => '20,1',
    'alt_text'   => [
        'allowed_hosts' => [],
    ],
],
```

---

## Requirements

- `artisanpack-ui/ai` `^1.0` installed and configured with credentials.
- The individual feature toggle enabled (default: off) from the AI
  package's settings admin surface.
- Users granted the `use_ai_features` capability (or the capability
  named in `ai.capability`), or a host-defined `visual-editor.use-ai`
  gate. See [Access control](#access-control).
- CSRF middleware active on the `/visual-editor/api/*` route group so
  the shipped JS client's `X-CSRF-TOKEN` header is honored.

If `artisanpack-ui/ai` is not installed, the visual editor continues
to work as before — the AI feature registration is skipped, no routes
are registered, and the React affordances stay hidden behind
`useAiFeatures`.

---

## Upgrading to v1.12.1

- **Grant access.** The AI features are now denied unless the user
  holds the `use_ai_features` capability (or whatever `ai.capability`
  names), or the host defines its own `visual-editor.use-ai` gate.
  Without one of these, `/ai/*` returns 403, the Livewire listeners
  dispatch `:forbidden`, and the React affordances stay hidden — even
  with the feature toggles on.
- **Routes are live.** The `/ai/*` routes were never registered before
  v1.12.1, so any client that called them now reaches real endpoints,
  subject to the gate and the 20-per-minute default throttle.
- **Alt-text inputs.** Callers that sent a server path (`source:
  "path"`) or a URL on another host must switch to base64 / data URIs,
  a same-site URL, or add the host to `ai.alt_text.allowed_hosts`.
- **Large documents.** Documents over the payload limits now fail with
  422 / `invalid-input` rather than being sent whole. Raise
  `ai.payload_limits` if your content legitimately exceeds them.

---

## Related

- [[Hooks and Events]] — `ap-ve-ai:*` browser events and the AI
  package's `FeatureRegistry` extension points.
- [`artisanpack-ui/ai`](https://github.com/ArtisanPack-UI/ai) — The
  foundation package: agents, feature registry, credential
  management, and provider drivers.
