# Visual Editor — Issue Roadmap

**Package:** `artisanpack-ui/visual-editor`
**Created:** April 28, 2026
**Status:** Active
**Aggregates:** [`11-v1-expansion.md`](11-v1-expansion.md), [`12-cms-framework-integration.md`](12-cms-framework-integration.md), [`13-block-fork.md`](13-block-fork.md), [`14-cms-framework-site-editor-integration.md`](14-cms-framework-site-editor-integration.md)

---

## How to use this doc

Single source of truth for issue ordering across V1 ship, V2 block fork, and 1.1+ deferred work. Pull this up before starting a new work session — the wave structure tells you what's safe to start now without re-deriving dependencies from the plan docs.

When you say *"let's work on the next set of issues for the visual editor"*, read this file first, then verify with `gh issue view {number}` that the issue is still open (some may have closed since this doc was last edited).

This doc captures **ordering**, not detailed acceptance criteria. Per-issue specifics live in each issue body and the source plan docs.

---

## 1. Critical-path summary

```text
V1 ship (≈4–6 mo) ──→  V2 block fork (≈10–12 wk) ──→  1.1+ features
   ▲
   └── cms-framework V1.x (Phase G + Phase H backends, parallel)
```

V1 umbrella: [`#309`](https://github.com/ArtisanPack-UI/visual-editor/issues/309). V2 umbrella: [`#331`](https://github.com/ArtisanPack-UI/visual-editor/issues/331). Plan-14 site-editor umbrella: [`#406`](https://github.com/ArtisanPack-UI/visual-editor/issues/406).

---

## 2. Where to start right now

If you opened this doc with no other context, start one of:

1. **`#395`** (visual-editor) — G0 · core-data shim REST resolution. Unblocks the entire G-series.
2. **`#94`** (cms-framework) — G1a · Adopt `HasBlockContent` on Post and Page. Same — unblocks G-series.

These have no upstream dependencies and can run in parallel.

---

## 3. V1 ship — wave-by-wave

### Wave 1 — Foundation

| Issue | Repo | Title | Priority | Effort |
|---|---|---|---|---|
| `#395` | visual-editor | G0 · core-data shim REST resolution *(bug)* | High | Medium |
| `#94` | cms-framework | G1a · Adopt `HasBlockContent` on Post and Page | High | Medium |

### Wave 2 — Phase G resource bridge

After Wave 1.

| Issue | Repo | Title | Priority | Effort | Depends on |
|---|---|---|---|---|---|
| `#397` | visual-editor | G1b · `ap.visual-editor.resources` filter | High | Low | `#94` |
| `#99` | cms-framework | G1b' · Register Post/Page into filter | High | Low | `#94`, `#397` |
| `#95` | cms-framework | G1c · `ContentTypeManager` auto-register | Medium | Low | `#94` |
| `#96` | cms-framework | G2a · `site.*` settings + REST | High | Medium | — |
| `#398` | visual-editor | G2b · `core/site-*` block resolvers | High | Medium | `#96` |

### Wave 3 — Phase G editor surface

| Issue | Repo | Title | Priority | Effort | Depends on |
|---|---|---|---|---|---|
| `#399` | visual-editor | G3 · Editor entity adapter for Post/Page | High | High | `#395`, `#397`, `#99` |
| `#98` | cms-framework | G5 · `visual_editor.*` permissions | Medium | Low | `#94` *(parallel)* |

### Wave 4 — Phase G block re-enablement

After G3.

| Issue | Repo | Title | Priority | Effort | Depends on |
|---|---|---|---|---|---|
| `#400` | visual-editor | G4a · Verify `core/post-*` against G3 | Medium | Low | `#399` |
| `#401` | visual-editor | G4b · Re-enable archives/categories/tag-cloud | Medium | Medium | `#399` |
| `#97` | cms-framework | G4c-1 · `QueryRuntime` service | High | High | `#399` |
| `#402` | visual-editor | G4c-2 · query/resolve endpoint | High | High | `#97`, `#401` |

### Wave 5 — Phase H (cms-framework site-editor integration, plan 14)

After Wave 1. `#100` (H0) gates H1–H4. **H1–H4 in cms-framework and H6–H8 in visual-editor are not yet filed**; per plan 11 §6, file at phase kickoff.

| Issue | Repo | Title | Priority | Effort | Depends on |
|---|---|---|---|---|---|
| `#406` | visual-editor | Plan 14 umbrella | High | High | — |
| `#100` | cms-framework | H0 · `theme.json` schema extension | High | Medium | — |
| *not filed* | cms-framework | H1 · Templates + template-parts module | — | — | `#100` |
| *not filed* | cms-framework | H2 · Patterns module | — | — | `#100` |
| *not filed* | cms-framework | H3 · Global styles + CSS emission | — | — | `#100` |
| *not filed* | cms-framework | H4 · Menus module | — | — | `#100` |
| `#407` | visual-editor | H5 · Site-editor resource filters | High | Medium | H1–H4 *(any one)* |
| *not filed* | visual-editor | H6 · WP-shape adapters + `addEntities` + sidebars | — | — | `#407` |
| *not filed* | visual-editor | H7 · Rescope plan 11 Phase D UI | — | — | H6 |
| *not filed* | visual-editor | H8 · Dev-app sample theme + smoke flow | — | — | H7 |

### Parallel polish track

Independent of Phase G / Phase H. Slot in between blocked waits.

| Issue | Title | Priority | Effort | Notes |
|---|---|---|---|---|
| `#394` | Site editor inspector wrong registry *(bug)* | High | Low | Site-editor regression; do before `#382` |
| `#347` | A1 · Iframe the editor canvas | High | Medium | Before `#382` (legacy reference may still help) |
| `#348` | A1 · Restore contrast warning | Medium | Low | Defer if time-pressed; can ship in 1.0.1 |

### Wave 6 — Cleanup + ship

| Issue | Repo | Title | Priority | Effort | Depends on |
|---|---|---|---|---|---|
| `#403` | visual-editor | G6 · Integration docs + dev-app smoke flow | Medium | Low | All G work |
| `#382` | visual-editor | F1 · Delete `_legacy/` | High | High | `#347` |
| `#383` | visual-editor | F3 · Dev-app integration: surface site editor | High | High | H8, `#403` |
| `#325` | visual-editor | M15 · Docs, website, release | High | High | Everything |
| `#309` | visual-editor | V1 umbrella *(closes when 1.0.0 ships)* | Urgent | High | — |

---

## 4. V2 — Block fork (after V1.0.0 GA)

Plan 13. I0 pilot branches off `main`, then `release/2.0` cuts from `main` once V1 tags. Per-block child issues spawn at each cluster's kickoff (per plan 13 §7).

> **Naming note:** the V2 block-fork phases use the letter **I** (I0–I8) to avoid colliding with V1 plan 14's site-editor **H** phases (H0–H8). Plan 13 originally used H; renamed to I after H0 of plan 14 shipped against cms-framework. Issue titles use the `Block fork I{N}` prefix.

| Issue | Title | Priority | Effort |
|---|---|---|---|
| `#331` | V2 umbrella · Port core blocks → `artisanpack/*` | High | High |
| `#408` | I0 · Paragraph pilot *(gates I1–I6)* | High | Medium |
| `#409` | I1 · Content cluster (8 blocks) | Medium | High |
| `#410` | I2 · Media cluster (8 blocks) | Medium | High |
| `#411` | I3 · Layout cluster (6 source units / 8 logical) | Medium | High |
| `#412` | I4 · Widgets cluster (2 blocks) | Medium | Medium |
| `#413` | I5 · Entity cluster (11 blocks) | Medium | High |
| `#414` | I6 · Loop / feed cluster (5 blocks) | Medium | High |
| `#415` | I7 · Cutover | High | Medium |
| `#416` | I8 · Ship V2.0.0 | Medium | Low |

**V1 → V2 dependencies:**

- I5 reads through V1's `#399` (G3 entity adapter) + `#395` (G0 shim resolution).
- I6 hard-couples to cms-framework V1.x release tagging G4b (`#401`) and G4c-1 (cms-framework `#97`).
- `#338` carries forward into `artisanpack/search` at I4.

---

## 5. Deferred — 1.1+ (Awaiting Review milestone)

All under `Awaiting Review` per plan 11 §7.

| Issue | Title |
|---|---|
| `#384` | Block revisions / versioning |
| `#385` | A/B testing for blocks and templates |
| `#386` | AI assistant integration |
| `#387` | Offline editing support |
| `#388` | Fine-grained per-block permission locking |
| `#389` | Pattern directory / remote pattern import |

---

## 6. Sequencing rationale (the *why*)

Capture once so it doesn't have to be re-derived:

- **`#395` first**: shim-layer bug. G4 series all assume REST-backed entities work through the shim. Fixing this after G4 means re-testing everything.
- **G3 (`#399`) before G4***: G4 issues explicitly verify against G3 entities.
- **`#347` before `#382`**: iframe canvas refactor is the kind of thing where having the legacy reference around is useful. Delete `_legacy/` only after the new shape is settled.
- **`#383` last among features**: dev-app integration is a smoke test for everything else, so it consumes the finished surface.
- **V2 `#408` (I0 pilot) does not start until V1 GA**: per plan 13 §1, V1's premise is to *adopt* upstream blocks. Inverting that during V1 splits reviewer attention. The pilot branches off `main`, not `release/2.0`.

---

## 7. Outstanding tactical decisions

Documented so they're not re-litigated each session:

- **File H1–H4 (cms-framework) + H6–H8 (visual-editor) stub issues now, or wait for phase kickoff?** Plan 11 §6 says wait. Counter-argument: matches V2 I-series treatment, gives full visibility. **Current call:** wait — file at each phase kickoff so acceptance criteria are fresh.
- **Per-block V2 child issues filed at cluster kickoff, not preemptively** (plan 13 §7). 41 stale tickets in the backlog isn't worth the visibility.

---

## 8. Maintaining this doc

Update when:

- A wave completes (mark issues closed; trim wave from active section).
- A plan doc changes (re-aggregate from the plans).
- A new issue lands that doesn't fit the existing waves.
- A dependency assumption breaks (rebuild affected wave).

Don't update for:

- Individual issue-state changes — query `gh issue list` for current state.
- Per-issue detail edits — those live in the issue bodies.
