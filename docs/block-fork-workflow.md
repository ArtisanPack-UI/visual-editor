---
title: Block fork workflow
---

# Block fork workflow

The V2 block-fork phase (plan 13) ports each `core/*` block in
`@wordpress/block-library` into the `artisanpack/*` namespace so the visual
editor no longer takes a hard runtime dependency on the upstream package.
This document describes the day-to-day workflow for keeping forked blocks
in sync with upstream after I0 lands.

> **Pilot reference:** `resources/js/visual-editor/blocks/paragraph/` is the
> reference implementation produced by phase I0 (issue #408). When in
> doubt about file layout, naming, or state-file shape — copy paragraph.

## Anatomy of a forked block

```
resources/js/visual-editor/blocks/<name>/
├── block.json                  # name swapped to artisanpack/<name>
├── index.ts                    # auto-discovery entrypoint
├── edit.tsx                    # TypeScript port of upstream edit.js
├── save.tsx                    # TypeScript port of upstream save.js
├── deprecated.ts               # full upstream deprecation chain
├── transforms.ts               # raw + bidirectional core/* transforms
├── <block>.css                 # combined style.scss + editor.scss
├── inserter-icon.tsx           # inline SVG (editor lacks dashicons.css)
├── <block-private-hooks>.ts    # use-enter, use-deprecated-align, …
├── upstream-state.json         # the source of truth for drift tracking
├── __tests__/
│   ├── edit.test.tsx
│   ├── save.test.tsx
│   ├── deprecated.test.tsx
│   └── transforms.test.ts
```

Shared primitives (used by 2+ forked blocks) live under
`resources/js/visual-editor/blocks/_shared/`. The decision tree for when to
vendor vs re-import is in that directory's `README.md`.

Three companion renderers must register the fork on the same commit:

- `packages/visual-editor-renderer-blade/resources/views/blocks/artisanpack/<name>.blade.php`
- `packages/visual-editor-renderer-react/src/blocks/registerCoreBlocks.ts` — add `'artisanpack/<name>': Renderer,`
- `packages/visual-editor-renderer-vue/src/blocks/registerCoreBlocks.ts` — add `'artisanpack/<name>': Renderer,`

And the canonical block list at `packages/renderer-parity.json` must include
the new namespace. `npm run verify:parity` (CI) catches drift.

## `upstream-state.json` schema

Every forked block ships an `upstream-state.json` next to its `index.ts`.
The full JSON Schema lives at `scripts/upstream-state.schema.json`; the
short version is:

```json
{
    "block": "artisanpack/paragraph",
    "upstream": {
        "package": "@wordpress/block-library",
        "namespace": "core/paragraph",
        "pinnedVersion": "9.43.0",
        "subpath": "paragraph"
    },
    "forkedAt": "2026-05-27",
    "files": [
        { "fork": "edit.tsx", "upstream": "edit.js", "status": "adapted", "notes": "TypeScript port." }
    ],
    "vendoredPrimitives": [],
    "knownDivergences": [],
    "triage": { "label": "in-sync", "lastReviewed": "2026-05-27", "reviewer": "I0 pilot" }
}
```

### Status values

| status      | Meaning                                                                                  | Drift detector behaviour |
|-------------|------------------------------------------------------------------------------------------|--------------------------|
| `ported`    | Byte-equivalent copy of upstream (rare; usually only for `block.json` snippets).         | CI fails on any diff.    |
| `adapted`   | TypeScript port and/or namespace swap. Logically equivalent but expected to differ text. | Skipped by detector.     |
| `extended`  | Fork adds behaviour on top of upstream (e.g. bidirectional transforms).                  | Skipped by detector.     |
| `rewritten` | Fork replaced upstream entirely (e.g. our auto-discovery `index.ts`).                    | Skipped by detector.     |
| `added`     | File unique to the fork; `upstream` field is `"n/a"`.                                    | Skipped by detector.     |

### Triage labels

| label                  | When to use                                                                                          |
|------------------------|------------------------------------------------------------------------------------------------------|
| `in-sync`              | Drift detector clean; no review needed.                                                              |
| `drift-acknowledged`   | Upstream changed in a way we deliberately don't want to absorb. `knownDivergences` documents why.    |
| `drift-pending`        | Upstream changed and a port is queued. Issue link belongs in `knownDivergences`.                     |
| `blocked`              | Drift cannot be resolved without an upstream/core-data/cms-framework decision. Escalate before next ship. |

## Day-to-day commands

```bash
# Inspect drift for all forks
npm run upstream-diff

# Inspect drift for one block, with unified diff
npm run upstream-diff -- --block paragraph --diff

# Verify every renderer registers every block in the parity manifest
npm run verify:parity
```

Both scripts return non-zero on failure and are safe to wire into CI.

## Diff cadence

| Event                                          | Required action                                                                                    |
|------------------------------------------------|----------------------------------------------------------------------------------------------------|
| Bumping `@wordpress/block-library` patch       | Run `upstream-diff --json` in CI; investigate any new `drift` row on `ported` files.               |
| Bumping minor or major                         | Manual review of every forked block; update `pinnedVersion` + `lastReviewed` after triage.         |
| Closing an `artisanpack/*` PR                  | Re-run `verify:parity` and `upstream-diff --json` locally before requesting review.                |
| Quarterly                                      | Open a "block-fork drift sweep" issue; assign each block to a reviewer; bump every `lastReviewed`. |

## Adding a new fork (post-I0 checklist)

1. **Decide the cluster.** Plan 13 §3 groups blocks into I1–I6. Pick the
   correct cluster's milestone for the issue.
2. **Create the directory** under `resources/js/visual-editor/blocks/<name>/`.
   Start by copying `paragraph/` and stripping behaviour you don't need.
3. **Port the upstream files** in this order:
   - `block.json` (namespace + textdomain swap, everything else verbatim)
   - `save.tsx` (golden-path serialization first)
   - `deprecated.ts` (lock down the deprecation chain ASAP — this is the
     hardest thing to regenerate later)
   - `edit.tsx`
   - `transforms.ts` (always add bidirectional `core/* ↔ artisanpack/*`)
   - block-private helpers (use-enter, etc.)
   - `<name>.css` (combine `style.scss` + `editor.scss`, resolve Sass
     vars inline)
   - `inserter-icon.tsx`
   - `index.ts`
4. **Write the four tests:** `edit.test.tsx`, `save.test.tsx`,
   `deprecated.test.tsx`, `transforms.test.ts`. Mock `@wordpress/*` to
   the minimum surface paragraph's tests use.
5. **Write `upstream-state.json`.** Mark every TS-ported file `adapted`.
   Mark `transforms.ts` `extended`. Mark `index.ts` `rewritten`.
6. **Add renderers** in all three packages + update
   `packages/renderer-parity.json`.
7. **Run `npm run verify:parity` and `npm run upstream-diff`.** Both
   must be green.
8. **Tests:** `npm test`. CI runs the same.

## When upstream changes a `ported` file

The diff detector flagged a drift on a `ported` file. Three valid responses:

1. **Absorb the change.** Re-port the upstream file. Re-run tests. Commit.
2. **Acknowledge the divergence.** Flip the file's `status` to `adapted`
   (or `extended` if you added behaviour). Add an entry to
   `knownDivergences`. Update `lastReviewed`.
3. **Block on a larger decision.** Set `triage.label = "blocked"`. Open
   an issue referencing the diff output. Tag the V2 phase milestone.

## Out of scope (post-V2 work)

- Customizing the edit experience (e.g. swapping `RichText` for Tiptap)
  — separate post-V2 effort. Forks ship behaviour parity first.
- Removing `@wordpress/block-library` as a runtime dependency. That's
  phase I7 (cutover), after every cluster I1–I6 ships.
