---
title: "Block fork I0 — cost estimate for I1–I6"
status: published
relates-to: [plan-13, issue-408]
---

# I0 cost estimate for clusters I1–I6

This document refines plan 13's provisional "1.5–2 weeks per cluster"
budget against the *measured* time it took to ship the paragraph pilot
(I0, issue #408).

## Method

The paragraph pilot includes everything a representative I-phase block
needs: TypeScript port of `edit` / `save`, full 7-entry deprecation
chain, raw + bidirectional transforms, `useOnEnter` block-private hook,
combined frontend + editor CSS, three-renderer parity (blade + react +
vue), and matching tests. Paragraph specifically does **not** need
vendored block-library-private utilities — every dependency is on the
public `@wordpress/*` surface.

The estimates below use paragraph's engineering hours as the base unit
and apply per-cluster multipliers for the blocks that materially deviate
from the paragraph baseline (richer attribute surface area, dynamic
behaviour, vendored primitives, integration with the cms-framework
adapter).

## Per-block baseline (from paragraph)

| Activity                                              | Hours |
|-------------------------------------------------------|------:|
| Read upstream + map dependencies                      |   0.5 |
| Port `block.json` + namespace swap                    |   0.5 |
| Port `save` + lock golden-path serialization          |   1.0 |
| Port deprecation chain + write deprecation tests      |   2.5 |
| Port `edit` + write edit tests                        |   2.5 |
| Port transforms (raw + bidirectional) + tests         |   1.0 |
| Port block-private hooks                              |   1.0 |
| Combine + adapt stylesheets                           |   0.5 |
| Three-renderer fork + update parity manifest          |   1.0 |
| Write `upstream-state.json`                           |   0.5 |
| Wire into auto-discovery + verify in dev-app          |   0.5 |
| Buffer for review feedback                            |   1.5 |
| **Total**                                             |  13.0 |

That's **~1.6 engineer-days per "paragraph-like" block**, or roughly
**2 days** padded for unknowns. Use 2 days as the per-block working
estimate.

## Cluster multipliers

| Cluster | Blocks | Per-block factor | Why                                                                                                            |
|---------|-------:|-----------------:|----------------------------------------------------------------------------------------------------------------|
| I1 Content    |  8 | 1.0× | Heading, quote, list, code, preformatted, pullquote, verse, table. Shape similar to paragraph. Table adds a richer attribute surface (~1.3× for table alone). |
| I2 Media      |  8 | 1.4× | Image/gallery/video/audio/file/embed/cover/media-text touch the media-bridge contract. No bridge changes per plan 13 §3, but each block has 2–3× paragraph's attribute count. |
| I3 Layout     |  8 logical / 6 source | 1.3× | Columns, group (+row +stack variations), buttons (+button), separator, spacer, details. Variations halve the porting cost for row/stack. The `grid`/`grid-item` split is a structural change worth +1 day on top of the cluster. |
| I4 Widgets    |  2 | 1.2× | Search, latest-posts. Latest-posts is a dynamic block that requires a Blade dynamic renderer in addition to the static three-way parity. |
| I5 Entity     | 11 | 1.5× | All `post-*`, `site-*`, `template-part`, `navigation`. Each block consumes `useEntityRecord` / `useEntityRecords`. The wiring is already in place via plan 12 G0/G3, but every block needs an integration test against the cms-framework adapter. Navigation alone is ~2× a paragraph-block — budget it as a 4-day block. |
| I6 Loop/feed  |  5 | 1.8× | Archives, categories, tag-cloud, query, query-loop. Hard-couples to cms-framework G4b/G4c. Cannot start until cms-framework ships the matching release. Query + query-loop together are roughly an 8-day pair. |

## Cluster budgets

The numbers below assume a single engineer with the paragraph baseline
and the cluster multiplier above, working in focused 6-hour days.
Calendar weeks add **+25%** for code review latency, CI churn, and the
weekly drift-check ritual.

| Cluster | Block-days | + 25% review overhead | Engineer-weeks (5-day weeks) |
|---------|-----------:|----------------------:|-----------------------------:|
| I1 Content    | 8 × 2 × 1.0 = 16 | 20 | **4.0** |
| I2 Media      | 8 × 2 × 1.4 = 22.4 | 28 | **5.6** |
| I3 Layout     | 8 × 2 × 1.3 = 20.8 + 1 (grid split) = 21.8 | 27.3 | **5.5** |
| I4 Widgets    | 2 × 2 × 1.2 = 4.8 | 6 | **1.2** |
| I5 Entity     | 11 × 2 × 1.5 = 33 | 41.3 | **8.3** |
| I6 Loop/feed  | 5 × 2 × 1.8 = 18 | 22.5 | **4.5** |
| **Total**     |                | | **~29.1 engineer-weeks** |

## Parallelization

The clusters partition cleanly between two engineers with the following
critical-path constraints:

- **I0 → any one cluster** (plan 13 §critical path)
- **I6 must wait** on cms-framework G4b/G4c

A two-engineer schedule with I5 and I6 left for the engineer who's
landed cms-framework integration before, plus I1–I4 split across both
engineers:

| Eng A          | Eng B                |
|----------------|----------------------|
| I1 (4.0 wks)   | I2 (5.6 wks)         |
| I3 (5.5 wks)   | I4 (1.2 wks)         |
| —              | I5 first half (4 wks)|
| I5 second half (4.3 wks) | I6 (4.5 wks; waits on cms-framework) |

That lands at **~13 calendar weeks** for the cluster phases plus 1 week
for I7 cutover — **~14 weeks** total once I0 ships, or **~15 weeks**
including I0 itself.

Single engineer: **~30 weeks** including I0 and I7.

## Risks & multipliers not modelled

These would push the estimate up:

- **WordPress upstream change during the phase.** A minor version bump
  to `@wordpress/block-library` between I1 and I7 means re-porting
  every `adapted` file the bump touched. Budget +1 week per major bump
  absorbed mid-phase; pin the upstream version for the duration of
  each cluster.
- **Site editor integration regressions** (plan 14). I5 + I6 forks
  touch site-editor surfaces and may surface latent integration bugs
  in plan 14's work. Budget +1 week buffer for I5 + I6 combined.
- **CodeRabbit / human review surfacing structural rewrites.** The
  paragraph pilot is a clean baseline; later clusters may surface
  patterns we'd rather promote into `_shared/` retroactively. Each
  such promotion is ~2 days work + a re-test sweep of every dependent
  block.

## Comparison vs. plan 13's provisional estimate

| Source                 | Single-engineer | Two-engineer |
|------------------------|----------------:|-------------:|
| Plan 13 (provisional)  | 16–18 weeks     | 10–12 weeks  |
| This estimate (I0-based) | ~30 weeks     | ~14 weeks    |

The two-engineer number is roughly consistent with plan 13. The
single-engineer number is materially higher because plan 13 modelled
clusters as 1.5–2 weeks each without per-block weighting — most
clusters bear richer blocks than paragraph (notably I2 media and I5
entity), so the baseline-times-multiplier model produces a more
defensible number.

**Recommendation:** plan around the 14-week two-engineer schedule for
calendar planning; budget the 30-week single-engineer number for cost
exposure if Eng B is reassigned mid-phase.

## Pilot retrospective notes (I0 outcomes)

- The paragraph pilot needed **zero** vendored primitives. Plan 13's
  expectation of 5–10 vendored primitives by I6 still holds, but they
  will all materialize during I2–I5. `_shared/` ships with a README
  and the convention is documented for future clusters.
- The `upstream-state.json` shape went through one revision during
  I0 — adding the `adapted` status. Future clusters should not need
  schema changes; if they do, bump the schema and migrate every
  existing state file in the same PR.
- The renderer-parity manifest required a `bladeDynamic` carve-out
  for `core/query` + `core/post-template`. I6 will land
  `artisanpack/query` + `artisanpack/post-template`; expect to extend
  `bladeDynamic` accordingly.
- CodeRabbit produced no actionable feedback on the pilot pass that
  required rewriting any of the ported files (TypeScript types were
  the largest delta).
