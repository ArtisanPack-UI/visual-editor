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
`src/Http/Requests/Ai/` and returns a consistent error envelope. All
routes are guarded on `class_exists(FeatureRegistry)`, so hosts
without `artisanpack-ui/ai` installed simply don't expose the surface —
no 500s.

### Livewire component

Blade / Livewire hosts can consume the same triggers through the
shipped Livewire component `artisanpack-visual-editor.ai.tools`, which
listens for `ap-ve-ai:*` browser events and re-dispatches shaped
results as `ap-ve-ai:{feature}:{status}` events (with statuses
`success`, `invalid-input`, `disabled`, `missing-credentials`, and
`error`).

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

## Requirements

- `artisanpack-ui/ai` `^1.0` installed and configured with credentials.
- The individual feature toggle enabled (default: off) from the AI
  package's settings admin surface.
- CSRF middleware active on the `/visual-editor/api/*` route group so
  the shipped JS client's `X-CSRF-TOKEN` header is honored.

If `artisanpack-ui/ai` is not installed, the visual editor continues
to work as before — the AI feature registration is skipped, no routes
are registered, and the React affordances stay hidden behind
`useAiFeatures`.

---

## Related

- [[Hooks and Events]] — `ap-ve-ai:*` browser events and the AI
  package's `FeatureRegistry` extension points.
- [`artisanpack-ui/ai`](https://github.com/ArtisanPack-UI/ai) — The
  foundation package: agents, feature registry, credential
  management, and provider drivers.
