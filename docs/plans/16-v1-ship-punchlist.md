# Visual Editor — v1.0.0 Ship Punch List

**Package:** `artisanpack-ui/visual-editor`
**Target:** 1.0.0 GA
**Created:** 2026-05-24
**Status:** Active
**Companion to:** [`11-v1-expansion.md`](11-v1-expansion.md) (scope + rationale), [`13-block-fork.md`](13-block-fork.md) (block-fork detailed plan), [`15-issue-roadmap.md`](15-issue-roadmap.md) (wave-by-wave ordering — **stale on §4: block fork moved into 1.0.0 on 2026-05-21**)

---

## Purpose

Short, focused list of *what's left to ship 1.0.0* against the **current open issue set**. The plan docs above are still authoritative for scope and the long-arc roadmap — this doc is the punch list you scan before tagging.

If an issue isn't on this list, it isn't blocking 1.0.0.

### Important correction vs `15-issue-roadmap.md`

The roadmap doc (written April 28) lists the entire I0–I8 block fork as **V2** work. That's no longer accurate — per the bodies of `#331` and `#416`, the fork was **moved into 1.0.0 on 2026-05-21**. Rationale (from the issue): the CMS is the long pole on the 1.0.0 timeline so the fork is schedule-neutral, and shipping it pre-1.0.0 means no host app has persisted `core/*` block trees yet — so **no migration is ever required**. Roadmap §4 needs to be rewritten when this punch list is closed out.

---

## Reality check on the version number

A small drift to clear up before tagging:

- `composer.json` is already pinned at `"version": "1.0.0"`.
- `package.json` is at `"version": "1.0.0-spike"`.

That conflicts with the `v1.0.0-alpha.1 → -beta.1 → 1.0.0` cadence in `11-v1-expansion.md` §5. Either:

1. Roll both back to a pre-release tag (`1.0.0-beta.1`) for the next interim release, or
2. Accept the drift and ship `1.0.0` the moment the Must-Ship list closes — no more alphas/betas.

Pick one explicitly at the start of the next work session. Keeping the mismatch is the worst option.

---

## Must-Ship (blocks 1.0.0)

Open issues that genuinely block the release tag. If any of these slip, 1.0.0 slips.

### Editor / dev-app readiness

| Issue | Title | Effort | Why blocking |
|---|---|---|---|
| `#419` | Entity-backed core blocks crash when referenced record is missing *(bug)* | Small | Hard crash in a core code path — can't ship through a missing-record case. **May be revisited inside I5 fork**, but bug needs a fix one way or another |
| `#403` | G6 · cms-framework integration docs + dev-app smoke flow | Low | Closes Phase G; the smoke flow is the only end-to-end verification of the version-pair contract |
| `#383` | F3 · Dev-app integration: surface site editor + sample templates/patterns | High | The dev-app is the install gate's reference implementation; release demos and screenshots come from here |
| `#382` | F1 · Delete `resources/js/visual-editor/_legacy/` | High | Plan 11 §2.3 cleanup; release docs reference `_legacy/` as gone |
| `#434` | Delete plan-11-Phase-D legacy site-editor code (follow-up to H6) | Medium | Same theme as `#382` — gets the legacy code out before fork work touches the same files |

### Phase I — Block fork (entire I0–I8 series, all 1.0.0)

Per `#331`: ~10–12 engineer-weeks across two people, ~16–18 single-engineer. **Runs serialized — not interleaved — with the editor/dev-app readiness work above** (per `#331` sequencing note). Per-block child issues spawn at cluster kickoff, not preemptively.

| Issue | Title | Effort | Sequencing |
|---|---|---|---|
| `#408` | I0 · Paragraph pilot | Medium | Sequential gate — **must complete before any I1–I6 work**. Produces the cost estimate, workflow, and vendored primitives the cluster phases inherit |
| `#409` | I1 · Content cluster (8 blocks) | High | Parallelizable after I0 |
| `#410` | I2 · Media cluster (8 blocks) | High | Parallelizable after I0 |
| `#411` | I3 · Layout cluster (6 source units / 8 logical; includes `grid` + `grid-item` split) | High | Parallelizable after I0 |
| `#412` | I4 · Widgets cluster (search, latest-posts) | Medium | Parallelizable after I0 |
| `#413` | I5 · Entity cluster (11 blocks) | High | Parallelizable after I0; reads through V1 G3 (`#399`) |
| `#414` | I6 · Loop / feed cluster (5 blocks) | High | Parallelizable after I0; couples to cms-framework G4b/G4c |
| `#415` | I7 · Cutover (drop `core/*` registration, swap to `artisanpack/*`) | Medium | Sequential — **after all clusters land** |
| `#416` | I8 · Fork-completion gate (soak + hands off to `#325`) | Low | Sequential — last fork issue; verifies dev-app soak on forked namespace |

### Release readiness

| Issue | Title | Effort | Why blocking |
|---|---|---|---|
| `#450` | Accessibility audit — full WCAG pass on post editor + site editor | High | Phase/Polish + Priority/High; runs **after** the fork because audit findings on `core/*` markup are wasted effort if we fork the same blocks. An editor that fails a11y is not a credible 1.0 |
| `#325` | M15 · Docs, website, release | High | The ship issue itself — release notes, website copy, migration guide. Consumes `#416` handoff |
| `#309` | V1 umbrella *(closes when 1.0.0 ships)* | — | Tracking only |

## Should-Ship (strongly preferred, slip to 1.0.1 only under duress)

| Issue | Title | Effort | Note |
|---|---|---|---|
| `#428` | Refactor `core/query` inliner to emit semantic `<ul>/<li>` markup | Medium | **May be obviated by I6 (loop/feed cluster)** — if `#414` rewrites the markup as part of the fork, close `#428` as duplicate. Otherwise bundle with `#450` |

## Defer to 1.0.1+ (and beyond)

Already-labeled deferrals, repeated here so the line is unambiguous at tag time.

| Issue | Title | Reason |
|---|---|---|
| `#384` | Block revisions / versioning | Plan 11 §7 — 1.1+ |
| `#385` | A/B testing | Plan 11 §7 — 1.1+ |
| `#386` | AI assistant | Plan 11 §7 — 1.1+ |
| `#387` | Offline editing | Plan 11 §7 — 1.1+ |
| `#388` | Per-block permission locking | Plan 11 §7 — 1.1+ |
| `#389` | Pattern directory / remote import | Plan 11 §7 — 1.1+ |
| — | `core/latest-comments` fork | Per `#331` scope note — deferred to 1.x (needs cms-framework Comments module) |

---

## Sequenced execution order

Per the sequencing note in `#331`: editor/dev-app readiness work runs first; block fork runs after; release work runs last. **No interleaving** — the fork wants undivided reviewer attention.

### Phase 1 — Editor / dev-app readiness (current focus)

1. **`#419`** first — bugs before features; small effort, removes a known crash that would surface during fork testing anyway.
2. **`#403` + `#383`** in parallel — backend docs/smoke and frontend dev-app surfacing move independently.
3. **`#382` + `#434`** in parallel after `#383` lands — both delete superseded code; safer to do once the new dev-app surface is proven.

### Phase 2 — Block fork (I0–I8)

4. **`#408` (I0 paragraph pilot)** — sequential gate; produces cost estimate that should be folded back into this doc before I1–I6 spawn.
5. **`#409`–`#414` (I1–I6)** — parallelizable across engineers if available; otherwise pick the order that minimizes context-switching (e.g., all text-ish clusters first).
6. **`#415` (I7 cutover)** — sequential after every cluster lands.
7. **`#416` (I8 completion gate)** — sequential after I7; dev-app soak on forked namespace, then hands off to `#325`.

### Phase 3 — Release

8. **`#450`** — a11y audit runs after the fork because findings on `core/*` markup would be wasted effort.
9. **`#428`** — only if I6 didn't already obviate it; bundle with `#450`.
10. **`#325`** — release notes, website copy, migration guide. Last thing in.

### Critical-path estimate

Editor/dev-app readiness (Phase 1) + fork (`#331` estimates 10–12 engineer-weeks @ 2 engineers, 16–18 single-engineer) + release readiness. The I0 pilot's cost estimate will refine the fork number — re-evaluate this doc once `#408` closes.

---

## Pre-tag checklist

Run through this before cutting `v1.0.0`:

- [ ] All Must-Ship issues closed
- [ ] Should-Ship issues closed *or* explicit decision logged to defer
- [ ] [`docs/h8-smoke-flow.md`](../h8-smoke-flow.md) runs green against the `artisanpack-ui/cms-framework` version pair
- [ ] `composer.json` and `package.json` versions both set to `1.0.0` (no `-spike`, no drift between PHP and JS)
- [ ] `CHANGELOG.md` populated with the 1.0.0 entry (currently empty placeholder)
- [ ] Release notes drafted in `#325`
- [ ] [`15-issue-roadmap.md`](15-issue-roadmap.md) **§4 rewritten** to reflect block fork shipping in 1.0.0 (currently lists it as V2); Wave 6 trimmed; V1 critical-path section marked complete
- [ ] Renovate `@wordpress/*` updates re-enabled (paused during V1 expansion per plan 11 §4.1); first per-block upstream-diff CI cycle runs green (per `#416` acceptance criteria)

---

## Maintaining this doc

Re-open and edit when:

- A new bug or polish item lands that changes the Must-Ship list
- A Must-Ship issue is reclassified (e.g., scope widens enough to defer)
- The pre-tag checklist gains or loses an item

Once `v1.0.0` ships, **archive this doc** — don't keep updating it for 1.0.1+. Cut a fresh punch list for the next release if you want one.
