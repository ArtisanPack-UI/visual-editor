# Plan 21 — Block Visibility: Contextual Rules (#491)

The full runtime contract and developer reference for this feature
lives in [`docs/visibility.md`](../visibility.md). This file preserves
the plan-shaped view referenced from the issue body.

## Scope

Ships the five contextual rule families:

- Hide (`HideRule`) — master toggle.
- Screen size (`ScreenSizeRule`) — CSS-only, per-breakpoint.
- Query string (`QueryStringRule`) — key/value, wildcard, Any / All.
- Referrer (`ReferrerRule`) — literal, `*.` wildcard, `(direct)`.
- Browser / OS / Device (`BrowserOsDeviceRule`) — bundled regex
  parser with a `jenssegers/agent` upgrade path.

Plus the shared plumbing all three issues depend on:

- Site-wide kill switch: `artisanpack.visual-editor.visibility.enabled`.
- Debug hook: `ap.visual-editor.visibility.evaluated`.
- Rule-registry filter: `ap.visual-editor.visibility.register-rules`.
- Opt-out: `supports.artisanpackVisibility: false` in `block.json`.
- Shared Inspector panel (subsections render only when active).
- Server-side pre-render evaluator in the Blade renderer
  (`BlockRenderer::renderBlock`).
- `TreePruner` for the React and Vue renderers so hidden blocks are
  removed from the serialised payload before the client ever sees them.

## Files

Runtime:

- `src/Visibility/VisibilityEvaluator.php`
- `src/Visibility/VisibilityContext.php`
- `src/Visibility/VisibilityDecision.php`
- `src/Visibility/VisibilityRule.php`
- `src/Visibility/RuleRegistry.php`
- `src/Visibility/PreviewContext.php`
- `src/Visibility/UserAgentParser.php`
- `src/Visibility/Rules/HideRule.php`
- `src/Visibility/Rules/ScreenSizeRule.php`
- `src/Visibility/Rules/QueryStringRule.php`
- `src/Visibility/Rules/ReferrerRule.php`
- `src/Visibility/Rules/BrowserOsDeviceRule.php`
- `src/Visibility/TreePruner.php`
- `packages/visual-editor-renderer-blade/src/BlockRenderer.php` (integration)
- `packages/visual-editor-renderer-react/src/visibility.ts`
- `packages/visual-editor-renderer-vue/src/visibility.ts`

Editor UI:

- `resources/js/visual-editor/visibility/register-attribute.ts`
- `resources/js/visual-editor/visibility/with-visibility-panel.tsx`
- `resources/js/visual-editor/visibility/VisibilityPanel.tsx`
- `resources/js/visual-editor/visibility/types.ts`

Tests:

- `tests/Unit/VisualEditor/Visibility/*` (Pest — one file per rule)
- `packages/visual-editor-renderer-react/tests/visibility.test.ts` (Vitest)
- `tests/E2E/visibility.spec.ts` (Playwright plan document)

## Follow-ups

- Toolbar Eye badge (visual signal that any visibility rule is active
  on the currently-selected block).
- Site-editor Preview mode (mocks viewport / query string / referrer
  / user agent inputs on the canvas).
