# Plan 23 — Block Visibility: Date/Time Scheduling (#493)

Extends plan 21 with two scheduling rule families + the audit command.
Full runtime contract in [`docs/visibility.md`](../visibility.md).

## Scope

- Date/time window (`DateTimeWindowRule`) — start and/or end
  datetime, per-rule timezone override, either edge omissible.
- Recurring schedule (`RecurringScheduleRule`) — up to 14 weekly
  windows (day 0–6, `HH:MM` start/end), per-rule timezone override.
- Audit command — `php artisan visual-editor:audit-scheduled-blocks`
  enumerates every scheduled block across the registered resources.

## Timezones

Both rules default to `config('app.timezone')`; per-rule overrides
win. Wall-clock schedules stay stable across DST transitions:
"10:00 America/Chicago" matches at 10:30 whether the region is on CST
or CDT.

## Schema validation

Malformed inputs treat the block as **visible** (fail-open) so a
broken rule can never accidentally hide production content:

- `end < start` on a fixed window → visible.
- Unknown timezone → falls back to `config('app.timezone')`.
- Malformed `HH:MM` on a recurring window → the window is skipped.

## Files

- `src/Visibility/Rules/DateTimeWindowRule.php`
- `src/Visibility/Rules/RecurringScheduleRule.php`
- `src/Visibility/ScheduledBlockCollector.php`
- `src/Console/AuditScheduledBlocksCommand.php`

Tests:

- `tests/Unit/VisualEditor/Visibility/ScheduleRulesTest.php` (Pest,
  includes DST fall-back + spring-forward cases)

## Follow-ups

- Cache auto-tightening for scheduled blocks inside cached template
  parts, with editor warning (deferred — needs a cms-framework
  integration point that doesn't exist in visual-editor today).
- Editor canvas indicators (scheduled / active / expired badge on
  each scheduled block).
- Preview-mode "mock current time" datetime picker.
