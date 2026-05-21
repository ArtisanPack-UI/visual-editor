# Visual Editor — Issue Roadmap

**Package:** `artisanpack-ui/visual-editor`
**Created:** April 28, 2026
**Status:** Active
**Aggregates:** [`11-v1-expansion.md`](11-v1-expansion.md), [`12-cms-framework-integration.md`](12-cms-framework-integration.md), [`13-block-fork.md`](13-block-fork.md), [`14-cms-framework-site-editor-integration.md`](14-cms-framework-site-editor-integration.md)

---

## How to use this doc

Single source of truth for issue ordering across the 1.0.0 ship (including the Phase I block fork) and 1.x+ deferred work. Pull this up before starting a new work session — the wave structure tells you what's safe to start now without re-deriving dependencies from the plan docs.

When you say *"let's work on the next set of issues for the visual editor"*, read this file first, then verify with `gh issue view {number}` that the issue is still open (some may have closed since this doc was last edited).

This doc captures **ordering**, not detailed acceptance criteria. Per-issue specifics live in each issue body and the source plan docs.

---

## 1. Critical-path summary

```text
V1 ship ─ editor core + Phase G/H integration + F-series cleanup
            │
            └─→ Phase I block fork (≈10–12 wk, serialized) ─→ M15 ship 1.0.0 ─→ 1.x features
   ▲
   └── cms-framework V1.x (Phase G + Phase H backends, parallel)
```

> **2026-05-21 — block fork moved into 1.0.0.** Phase I (#331, #408–#416, plan 13) was previously V2/post-GA; it now ships inside 1.0.0, serialized after the editor core + Phase G/H + F-series cleanup. CMS integration is the long pole, so the fork is schedule-neutral, and shipping it before any host persists `core/*` trees eliminates the migration problem. The `v2.x` milestone is retained (empty) for genuinely breaking work down the line.

V1 umbrella: [`#309`](https://github.com/ArtisanPack-UI/visual-editor/issues/309). Phase I block-fork umbrella: [`#331`](https://github.com/ArtisanPack-UI/visual-editor/issues/331) (rolls up under #309). Plan-14 site-editor umbrella: [`#406`](https://github.com/ArtisanPack-UI/visual-editor/issues/406).

---

## 2. Where to start right now

If you opened this doc with no other context, start one of:

1. **`#395`** (visual-editor) — G0 · core-data shim REST resolution. Unblocks the entire G-series.
2. **`#94`** (cms-framework) — G1a · Adopt `HasBlockContent` on Post and Page. Same — unblocks G-series.

These have no upstream dependencies and can run in parallel.

---

## 3. 1.0.0 ship — wave-by-wave

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
| ~~`#100`~~ | cms-framework | H0 · `theme.json` schema extension *(closed)* | High | Medium | — |
| ~~`#108`~~ | cms-framework | H1 · Templates + template-parts module *(closed)* | High | High | `#100` |
| ~~`#110`~~ | cms-framework | H2 · Patterns module *(closed)* | High | High | `#100` |
| ~~`#112`~~ | cms-framework | H3 · Global styles + CSS emission *(closed)* | High | High | `#100` |
| ~~`#114`~~ | cms-framework | H4 · Menus module *(closed)* | High | High | `#100` |
| ~~`#407`~~ | visual-editor | H5 · Site-editor resource filters *(closed)* | High | Medium | H1–H4 *(any one)* |
| ~~`#431`~~ | visual-editor | H6 · WP-shape adapters + `addEntities` + sidebars *(closed)* | High | High | `#407` |
| ~~`#432`~~ | visual-editor | H7 · Site-editor shell wiring + install gate *(closed)* | High | Medium | H6 |
| `#433` | visual-editor | H8 · Dev-app sample theme + smoke flow + version-pair docs | High | Medium | H7 |
| `#434` | visual-editor | Follow-up · delete plan-11-Phase-D legacy site-editor code | Medium | Medium | H7 |

### Parallel polish track

Independent of Phase G / Phase H. Slot in between blocked waits.

| Issue | Title | Priority | Effort | Notes |
|---|---|---|---|---|
| `#394` | Site editor inspector wrong registry *(bug)* | High | Low | Site-editor regression; do before `#382` |
| `#347` | A1 · Iframe the editor canvas | High | Medium | Before `#382` (legacy reference may still help) |
| `#348` | A1 · Restore contrast warning | Medium | Low | Defer if time-pressed; can ship in 1.0.1 |

### Wave 6 — Cleanup (pre-fork)

| Issue | Repo | Title | Priority | Effort | Depends on |
|---|---|---|---|---|---|
| `#403` | visual-editor | G6 · Integration docs + dev-app smoke flow | Medium | Low | All G work |
| `#382` | visual-editor | F1 · Delete `_legacy/` | High | High | `#347` |
| `#383` | visual-editor | F3 · Dev-app integration: surface site editor | High | High | H8, `#403` |

### Wave 7 — Phase I block fork

Plan 13. Serialized after Wave 6 — editor core + Phase G/H + F-series cleanup must be settled first (preserves the "don't split reviewer attention" concern). I0 pilot and all cluster branches cut from + merge into `release/1.0`. Per-block child issues spawn at each cluster's kickoff (per plan 13 §7).

> **Naming note:** the block-fork phases use the letter **I** (I0–I8) to avoid colliding with plan 14's site-editor **H** phases (H0–H8). Issue titles use the `Block fork I{N}` prefix.

| Issue | Title | Priority | Effort | Depends on |
|---|---|---|---|---|
| `#331` | Phase I umbrella · Port core blocks → `artisanpack/*` | High | High | Wave 6 |
| `#408` | I0 · Paragraph pilot *(gates I1–I6)* | High | Medium | `#331` |
| `#409` | I1 · Content cluster (8 blocks) | Medium | High | `#408` |
| `#410` | I2 · Media cluster (8 blocks) | Medium | High | `#408` |
| `#411` | I3 · Layout cluster (6 source units / 8 logical; + `grid`/`grid-item` split) | Medium | High | `#408` |
| `#412` | I4 · Widgets cluster (2 blocks) | Medium | Medium | `#408` |
| `#413` | I5 · Entity cluster (11 blocks) | Medium | High | `#408`, `#399`, `#395` |
| `#414` | I6 · Loop / feed cluster (5 blocks) | Medium | High | `#401`, cms `#97` |
| `#415` | I7 · Cutover | High | Medium | I1–I6 |
| `#416` | I8 · Fork-completion gate → hands to #325 | Medium | Low | `#415` |

**Intra-fork dependencies:**

- I5 reads through `#399` (G3 entity adapter) + `#395` (G0 shim resolution) — both land in Waves 1–4.
- I6 couples to cms-framework V1.x release tagging G4b (`#401`) and G4c-1 (cms-framework `#97`).
- `#338` carries forward into `artisanpack/search` at I4.

### Wave 8 — Ship 1.0.0

| Issue | Repo | Title | Priority | Effort | Depends on |
|---|---|---|---|---|---|
| `#325` | visual-editor | M15 · Docs, website, release | High | High | Everything incl. Phase I (`#416`) |
| `#309` | visual-editor | V1 umbrella *(closes when 1.0.0 ships)* | Urgent | High | — |

---

## 4. Deferred — 1.x+ features

Additive features with no breaking changes, so they bucket as future point releases — not a 2.0. `#388` (per-block locking) sits in the `v1.x` milestone as the likely next minor; the rest sit in `Future Release`, unversioned until pulled into a concrete 1.x. The `v2.x` milestone is retained but empty — reserved for genuinely breaking work, of which none is currently on the horizon (the namespace fork ships in 1.0.0, so it is *not* breaking).

| Issue | Title | Milestone |
|---|---|---|
| `#384` | Block revisions / versioning | Future Release |
| `#385` | A/B testing for blocks and templates | Future Release |
| `#386` | AI assistant integration | Future Release |
| `#387` | Offline editing support | Future Release |
| `#388` | Fine-grained per-block permission locking | v1.x |
| `#389` | Pattern directory / remote pattern import | Future Release |

---

## 5. Sequencing rationale (the *why*)

Capture once so it doesn't have to be re-derived:

- **`#395` first**: shim-layer bug. G4 series all assume REST-backed entities work through the shim. Fixing this after G4 means re-testing everything.
- **G3 (`#399`) before G4***: G4 issues explicitly verify against G3 entities.
- **`#347` before `#382`**: iframe canvas refactor is the kind of thing where having the legacy reference around is useful. Delete `_legacy/` only after the new shape is settled.
- **`#383` last among features**: dev-app integration is a smoke test for everything else, so it consumes the finished surface.
- **Phase I (block fork) serialized after Wave 6, not interleaved**: plan 13 §1's original concern was that inverting the "adopt upstream blocks" bet mid-V1 splits reviewer attention. Moving the fork into 1.0.0 keeps that intact by running it as a distinct phase *after* the editor core + Phase G/H + F-series cleanup settle. It branches off `release/1.0`, not a separate `release/2.0`.
- **Why the fork is in 1.0.0 at all**: CMS integration is the long pole on the timeline, so the ~10–12 wk fork is schedule-neutral; and forking before any host persists `core/*` trees means there is never a migration to perform.

---

## 6. Outstanding tactical decisions

Documented so they're not re-litigated each session:

- **File H1–H4 (cms-framework) + H6–H8 (visual-editor) stub issues now, or wait for phase kickoff?** Plan 11 §6 says wait. Counter-argument: matches the Phase I treatment, gives full visibility. **Current call:** wait — file at each phase kickoff so acceptance criteria are fresh.
- **Per-block Phase I child issues filed at cluster kickoff, not preemptively** (plan 13 §7). ~41 stale tickets in the backlog isn't worth the visibility.

---

## 7. Maintaining this doc

Update when:

- A wave completes (mark issues closed; trim wave from active section).
- A plan doc changes (re-aggregate from the plans).
- A new issue lands that doesn't fit the existing waves.
- A dependency assumption breaks (rebuild affected wave).

Don't update for:

- Individual issue-state changes — query `gh issue list` for current state.
- Per-issue detail edits — those live in the issue bodies.
