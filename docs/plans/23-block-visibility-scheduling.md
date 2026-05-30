# Visual Editor — Block Visibility (Date/Time Scheduling)

**Package:** `artisanpack-ui/visual-editor`
**Version Target:** TBD (Awaiting Review)
**Created:** 2026-05-30
**Status:** Planning
**Reference:** [Block Visibility (WordPress plugin, by Nick Diego)](https://github.com/ndiego/block-visibility)
**Related:**
 - [`21-block-visibility-contextual.md`](21-block-visibility-contextual.md) — primary visibility feature; this extends its panel.
 - [`22-block-visibility-user-and-auth.md`](22-block-visibility-user-and-auth.md) — sibling, deferred to same milestone.

---

## 1. Problem Statement

Post-level scheduling exists (cms-framework `published_at` / `unpublished_at`), but it can't help an editor say "show this 'Black Friday sale' banner only between Nov 24 09:00 and Nov 28 23:59" without duplicating the entire post. Marketing teams want block-level time windows for promotions, seasonal copy, countdowns, and "show after launch" reveals.

## 2. Target User

Marketing editors running time-bound campaigns, editors maintaining seasonal pages, and developers building any time-aware content composition without writing custom Blade conditionals.

## 3. User Stories

- As an editor, I want to schedule a block to start showing at a specific date and time.
- As an editor, I want to schedule a block to stop showing at a specific date and time.
- As an editor, I want to configure a recurring schedule (e.g. "every Saturday 09:00–17:00") for an event-related block.
- As an editor, I want the editor canvas to indicate whether a scheduled block is currently in its visible window, scheduled to show later, or already expired.
- As an editor, I want the Visibility Preview mode to let me mock "the current time" so I can preview what visitors will see at any moment.
- As a developer, I want schedules evaluated server-side in the host application's timezone (with explicit per-rule timezone override) so behavior is consistent.

## 4. Scope

### 4.1 In scope

- **Date/Time Window rule** — start and/or end datetime. Either can be unset (open-ended).
- **Recurring schedule rule** — weekly day-of-week + time-of-day windows. Up to N windows per week (tentative cap: 14, enough for two windows per day).
- **Per-rule timezone** — defaults to `config('app.timezone')`. Editor can override per rule (e.g. "schedule in PST regardless of app timezone").
- **Evaluation cadence** — purely server-side at render time. No JS countdown runtime.
- **Editor status indicators**:
  - Scheduled (start in the future): clock icon + tooltip "Visible starting {datetime}."
  - Active: green dot + tooltip "Currently visible. Ends {datetime}." (omit "ends" if open-ended).
  - Expired (end in the past): grey clock + tooltip "Expired on {datetime}." Includes a "Reactivate" affordance that clears the end date.
- **Visibility Preview "mock current time"** — extends the preview controls with a datetime picker. The picker only affects rule evaluation in the preview; never mutates `now()` for the rest of the app.
- **Audit command** — `visual-editor:audit-scheduled-blocks` lists every block with an active or upcoming schedule (post → block → window). Useful for ops planning around big launches.

### 4.2 Out of scope

- **Cron/scheduler-based proactive cache busting**. Documented as a known limitation: schedules don't auto-bust caches; ops should set cache TTL ≤ the smallest reasonable schedule resolution.
- **iCal feeds / external calendar integration.** Out of scope.
- **Holiday-aware scheduling** (e.g. "every US federal holiday"). Out of scope.
- **Countdown timer rendering** (the visible "starts in 4h 12m" widget). That's a separate Countdown block, not a visibility rule.

## 5. Behavior

### 5.1 Happy path (single window)

1. Editor sets a Banner block schedule: start `2026-11-24 09:00 PST`, end `2026-11-28 23:59 PST`.
2. Save. The block attribute stores the ISO datetimes + timezone.
3. Renderer evaluates `now()->between(start, end)`. Pre-launch: block is not emitted. During the window: block renders. Post-launch: block is not emitted.
4. Editor canvas shows "Scheduled (starts Nov 24)" while editing.

### 5.2 Happy path (recurring)

1. Editor sets a Saturday-Sunday `10:00–14:00` recurring window for a "Weekend Brunch" block.
2. Renderer evaluates against the visitor's request time vs. day-of-week + time-of-day.
3. Mid-week visitors see no block; weekend daytime visitors see it.

### 5.3 Edge cases

- **App timezone changes after schedules are authored.** Per-rule timezone override prevents drift. If no override was set, the audit command flags ambiguous schedules.
- **End date precedes start date.** Schema validator rejects on save with a descriptive error.
- **DST transitions inside a window.** Documented; `now()->between()` handles via Carbon timezone math.
- **Block placed inside a cached fragment.** Cache invalidation must align with the smallest schedule boundary; renderer auto-shortens `cache_until` when a scheduled block is inside a cached template part. Editor surfaces a warning when this auto-tightening happens.
- **Schedule with both single window and recurring rule.** Combined with AND — block is visible only when both the single window AND the recurring rule pass.

## 6. Acceptance Criteria

- [ ] Single date/time window rule works for: start-only, end-only, both, neither.
- [ ] Recurring weekly schedule supports up to 14 windows.
- [ ] Per-rule timezone override works; defaults to app timezone.
- [ ] Editor canvas indicators (scheduled / active / expired) render correctly with relative-time tooltips.
- [ ] Visibility Preview mock-time picker affects only preview evaluation.
- [ ] `visual-editor:audit-scheduled-blocks` lists every scheduled block.
- [ ] Cache-auto-tightening fires + warns when a scheduled block is inside a cached template part.
- [ ] Schema validator rejects nonsensical schedules (end before start, malformed times, unknown timezones).
- [ ] Pest tests cover: single window, recurring, timezone override, DST transition, combined window+recurring, schema validation.
- [ ] Vitest tests cover the schedule editor UI + status indicators.
- [ ] Playwright E2E covers: schedule a block, scrub the preview time, save+reload, observe expired-state UI.
- [ ] Docs in `docs/visibility.md` extend with a Scheduling section including the cache-invalidation guidance.

## 7. Implementation Notes

### 7.1 Files to create

- `src/Visibility/Rules/ScheduleRule.php` — handles both single window + recurring evaluation.
- `src/Visibility/Schedule/RecurringWindow.php` — value object.
- `src/Visibility/Schedule/CacheTightener.php` — adjusts surrounding `cache_until` directives.
- `src/Console/AuditScheduledBlocksCommand.php`
- `resources/js/visual-editor/visibility/ScheduleSection.tsx`
- `resources/js/visual-editor/visibility/ScheduleStatusIndicator.tsx`
- `tests/Unit/Visibility/Schedule*`, Vitest + Playwright suites.

### 7.2 Files to modify

- `src/Visibility/VisibilityEvaluator.php` — wire in `ScheduleRule`.
- `resources/js/visual-editor/visibility/VisibilityPanel.tsx` — render the Schedule section.
- `resources/js/visual-editor/visibility/PreviewControls.tsx` — add the mock-time picker.
- `docs/visibility.md` — extend.

### 7.3 Database / schema

No DB migrations. Rules live in the existing `artisanpackVisibility` attribute object.

### 7.4 Dependencies

None new. Carbon is already in the stack.

## 8. Open Questions

- Should the recurring schedule support monthly patterns ("first Tuesday of the month")? (Tentative: defer until requested; weekly covers most use cases.)
- Should we ship a "soft-expire" mode where an expired block stays visible until the next deploy/cache flush, never mid-request? (Tentative: yes, as an opt-in `mode: hard | soft` per rule. Default `hard`.)
