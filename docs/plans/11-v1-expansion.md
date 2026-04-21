# Visual Editor — V1 Expansion Plan

**Package:** `artisanpack-ui/visual-editor`
**Version Target:** 1.0.0
**Created:** April 20, 2026
**Status:** Planning
**Supersedes scope of:** `01-comprehensive-plan.md` (pre-Gutenberg), `05-template-system.md`, `06-global-styles.md` — referenced below where their data-model design still applies.

---

## 1. Why this plan exists

The original V1 scope (issue #309, "Gutenberg adoption — V1 editor core") deliberately punted site editing to Phase 3+, punted the block-settings sidebar to "a later milestone," and shipped a slash-only block inserter. Milestones M0–M14 are closed, #325 (docs + release) is the only remaining V1 work on paper.

In practice, the editor is a capable post-content experience but not a shippable CMS:

- Right sidebar is placeholder text ("Settings UI lands in a later milestone")
- Left sidebar is placeholder text ("Block library UI lands in a later milestone")
- No site editor, no templates, no global styles, no navigation, no patterns
- 17 core blocks are disabled because `core-data` is an empty shim
- Media bridge is a stub
- Zero custom ArtisanPack blocks

This plan widens V1 so that 1.0.0 ships a **solid, usable Laravel CMS** rather than a post-editor demo. The tradeoff — an additional ~5 months of work — is accepted explicitly in favor of shipping something credible.

---

## 2. V1 scope (final)

### 2.1 Post-editor completion

- **Inspector sidebar** — real block-settings panels via `@wordpress/components` `InspectorControls`: alignment, colors, typography, spacing, advanced (class, anchor). Document settings panel (status, slug, excerpt, featured image).
- **Block library UI** — browsable inserter using `@wordpress/block-editor`'s `Inserter` component. Slash command stays.
- **Media library integration** — replace the `media-bridge/` stub with real `artisanpack-ui/media-library` integration.
- **Custom block scaffolding** — one reference block under the `artisanpack/*` namespace plus documented authoring pattern. Not forking core blocks (that's #331/V2).

### 2.2 Site editor (own chrome)

We build the site-editor shell ourselves — not depend on `@wordpress/edit-site`. Preserves the #309 premise of owning admin chrome and avoids tying the package's upgrade cadence to WP-admin's REST / CPT assumptions.

- **Shell** — site-editor routing, navigator sidebar, canvas frame (iframe), and tabs for Templates / Template Parts / Patterns / Styles / Navigation.
- **Templates + template parts** — models, migrations, REST APIs, `HasBlockContent`-style serialization, fallback chain (see §2 of `05-template-system.md` for the hierarchy design; still valid). Enable `core/template-part`, `core/post-content`, `core/post-title`, `core/post-excerpt`, `core/post-date`, `core/post-featured-image`, `core/site-title`, `core/site-tagline`, `core/site-logo`.
- **Global styles — theme.json compatibility** — schema-compatible with a pinned WP version (TBD at kickoff, documented then). Full UI: typography, colors, layout, per-block overrides, style variations, element styles. CSS custom property emission on the front-end.
- **Navigation — full fidelity** — menu locations, fallback menus, `core/navigation` block enablement, nav editor screen in the site editor. Biggest single risk area (see §4.3).
- **Patterns — synced + unsynced** — both types supported from day one with that vocabulary, avoiding WP's "reusable blocks → synced patterns" rename legacy. Pattern library in site editor, patterns category in post-editor inserter.

### 2.3 Foundation + ship

- **Expand `core-data` shim** to cover templates, template parts, navigation, patterns, and global-styles entities. Prerequisite for almost everything in §2.2.
- **Front-end rendering** — emit global-styles CSS; render template + part hierarchy; resolve synced patterns by reference; enable newly-supported blocks in all three renderers (Blade, React, Vue).
- **Cleanup** — delete `resources/js/visual-editor/_legacy/`.
- **Fix #338** — `core/search` `buttonUseIcon` a11y bug in Blade + React renderers.
- **Rescope #325** — current acceptance criteria cover post-editor docs only; needs to expand to site editor, templates, global styles, nav, patterns, custom block authoring.

---

## 3. Phase ordering

```
Phase A — Post-editor polish (parallelizable)
  A1  Inspector sidebar (block + document settings)
  A2  Block library UI
  A3  Media library integration
  A4  Custom block scaffolding + reference block

Phase B — Foundation (sequential; gates Phase C)
  B1  core-data shim expansion: template, template-part, navigation,
      wp_block (patterns), global-styles entities
      (surface + endpoint contract — see docs/core-data-shim.md)
  B2  Dev-app sample content for above

Phase C — Site-editor backends (parallelizable after B)
  C1  Templates: model, migration, API, fallback chain
  C2  Template parts: model, migration, API
  C3  Global styles: theme.json schema, model, migration, API
  C4  Navigation: model, migration, API, menu locations
  C5  Patterns: model, migration (synced + unsynced), API

Phase D — UI (can begin as its C counterpart lands)
  D1  Site-editor shell: routing, navigator, canvas frame, tabs
  D2  Template browser + editor integration
  D3  Global styles UI: typography/colors/layout/blocks/variations
  D4  Nav editor screen + core/navigation enablement
  D5  Patterns library UI + inserter category

Phase E — Integration
  E1  Front-end: global-styles CSS emission
  E2  Front-end: template + part rendering
  E3  Front-end: synced-pattern resolution
  E4  Block re-enablement across all three renderers

Phase F — Ship
  F1  Delete _legacy/
  F2  Fix #338
  F3  Dev-app integration: surface site editor, sample templates/patterns
  F4  Rescoped docs (#325)
  F5  v1.0.0-beta.1 → v1.0.0
```

**Rough duration**: ~20–22 weeks (≈5 months). Phase B is the serialization point; everything downstream depends on it. A runs in parallel with B.

---

## 4. Risks of record

### 4.1 `@wordpress/*` version pinning over 5 months

Renovate PRs every 2 weeks (per #309) is aggressive for a codebase actively building on these APIs. A mid-stream minor version bump of `@wordpress/block-editor` could invalidate weeks of UI work.

**Decision:** Pause Renovate for this package during the V1 expansion build. Do one audited upgrade pass near the end of Phase E before beta tagging. Document the pinned versions in the v1.0.0 release notes.

### 4.2 theme.json is a moving target

WordPress has iterated theme.json schema through multiple versions. Committing to "compatibility" without naming a version means every schema change is an invisible scope increase.

**Decision:** Pin to the schema version shipped with the WP core version matching our pinned `@wordpress/*` packages. Document the version number in `docs/plans/` and in the global-styles docs. Schema upgrades post-1.0 are explicit work, not drift.

### 4.3 Navigation is the hardest single piece

`core/navigation`'s editor experience depends on significant `core-data` surface (`wp_navigation` entity, link-control menu picker, REST endpoints, fallback-menu resolution). It will absorb a disproportionate share of the shim work in Phase B.

**Decision:** Scope nav shim work explicitly in B1 rather than discovering it during D4. Budget 1–1.5 weeks of shim work before any UI starts. If it overruns, the escape valve is to reduce nav to "mid" scope (stored nav content, no menu locations) and ship full nav in 1.1 — but we don't pre-commit to that; we try for full.

---

## 5. Branching + release strategy

- `release/1.0` stays as the integration branch. All V1-expansion work merges into it as it has for M0–M14.
- Tag current `release/1.0` HEAD as **`v1.0.0-alpha.1`** before any expansion work lands — a stable marker for the Gutenberg-adoption alpha so the expansion doesn't obscure what's already been shipped.
- Ship intermediate alphas/betas as phases complete (e.g., `v1.0.0-alpha.2` when Phase A is in, `v1.0.0-beta.1` at the end of Phase E).
- `main` remains release-only; tagged releases merge back per existing workflow.

---

## 6. Issue tracking approach

Creating 60+ issues up-front for 5 months of work produces stale, under-specified tickets by month 3. Instead:

1. **Umbrella #309** — update the body to reflect the widened V1 scope. Stays open until 1.0.0 ships.
2. **Issue #325** — rescope acceptance criteria for the broader docs surface. Stays as the ship-issue.
3. **Create milestone-level tracking issues one phase at a time**, as each phase kicks off. Each phase's milestones get detailed acceptance criteria when they're actually about to be worked.
4. **#331 stays in v2.x.** Its own body says V1's premise is to adopt upstream blocks; inverting that in V1 is self-contradictory.
5. **#338 is folded into Phase F** (F2), not a separate milestone.

---

## 7. Out of scope for V1 (defer to 1.1+)

Explicitly parked so the line is clear:

- Forking core blocks into `artisanpack/*` namespace (#331) — V2
- Block revisions / versioning
- A/B testing
- AI assistant
- Offline editing
- Permissions-and-locking beyond Laravel policies (fine-grained per-block locking)
- Pattern directory / remote pattern import
- Import from WordPress (lives in companion package `visual-editor-wp-import`)

---

## 8. Open questions to answer at phase kickoff

Not blocking this plan, but worth naming:

- **Global-styles**: do we surface "style variations" (theme-level presets) in V1 UI, or save for 1.1? Schema-compat means we *can*; UI cost is real.
- **Nav menu locations**: config-driven (`config/visual-editor.php`) or database-driven (admin CRUD)? Leaning config for V1.
- **Patterns**: do unsynced patterns support variables/bindings, or are they pure block-tree copies? Leaning pure copies for V1 — bindings are a whole separate system.
