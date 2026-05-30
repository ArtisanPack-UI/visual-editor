# Visual Editor — Block Animations

**Package:** `artisanpack-ui/visual-editor`
**Version Target:** 1.x
**Created:** 2026-05-30
**Status:** Planning
**Related:**
 - [`18-state-design-tools.md`](18-state-design-tools.md) — hover/focus animations layer on top of the state engine
 - [`17-responsive-design-tools.md`](17-responsive-design-tools.md) — animations should respect per-breakpoint enable/disable

---

## 1. Problem Statement

The editor renders blocks statically. There's no way for an editor to add an entrance animation (fade-in on scroll), a continuous animation (pulse on a CTA), a hover transition beyond what the state-design tools already emit, or a custom keyframe sequence. WordPress block plugins like Animate.css and Block Animator have established that this is core-table-stakes for marketing pages.

## 2. Target User

Content editors building landing pages, marketing sites, and product showcases who want motion to draw attention or signal interactivity, and developers/designers who want the motion vocabulary scoped to a curated set of safe defaults plus an escape hatch for bespoke keyframes.

## 3. User Stories

- As an editor, I want to add an entrance animation (fade, slide-up, slide-from-side, zoom, etc.) that plays once when the block scrolls into view.
- As an editor, I want to control the entrance animation's duration, delay, easing, and viewport threshold.
- As an editor, I want hover/focus transition curves I can tune (duration, easing) per block.
- As an editor, I want to add a continuous loop animation (pulse, bounce, spin, ping) for accents like CTAs or notification badges.
- As a developer, I want to author a custom `@keyframes` sequence in the editor UI and reuse it across blocks.
- As a developer, I want the motion vocabulary to default to `prefers-reduced-motion: reduce` (suppress animations) and to expose a per-block override only if I explicitly opt in.
- As a developer, I want to extend the animation library through config or theme.json so my design system's named motions show up in the dropdown.

## 4. Scope

### 4.1 In scope (v1.x)

- **Animation registry** with three families:
  1. **Entrance** — plays once when the block enters the viewport. Built-ins: `fade-in`, `fade-in-up`, `fade-in-down`, `fade-in-left`, `fade-in-right`, `zoom-in`, `zoom-out`, `slide-in-up`, `slide-in-down`, `slide-in-left`, `slide-in-right`, `flip-x`, `flip-y`, `rotate-in`.
  2. **Hover/Focus** — composable with [`18-state-design-tools.md`](18-state-design-tools.md). This feature adds a `transition` editor (duration / delay / easing / curve preview) plus prebuilt "motion presets" (`lift`, `press`, `glow`).
  3. **Continuous** — plays in a loop. Built-ins: `pulse`, `bounce`, `spin`, `ping`, `wiggle`, `float`.
- **Custom keyframe authoring** — a per-theme registry where developers can define named keyframes in `theme.json` → `settings.custom.artisanpack.keyframes` or via a programmatic API (`apRegisterKeyframes('confetti', [...])`). Custom keyframes appear in the dropdowns of the matching family.
- **Animation Inspector panel** added to every opted-in block. Three sub-panels (entrance / hover / continuous), each with the same per-family controls: motion, duration, delay, easing, repeat (for continuous), threshold (for entrance).
- **`prefers-reduced-motion` respect** — by default, the runtime suppresses entrance + continuous animations and shortens transitions to `0ms` when the user has reduced-motion on. A per-block override (`Respect reduced motion` boolean, default ON) is exposed.
- **Per-breakpoint enable/disable** — composable with [`17-responsive-design-tools.md`](17-responsive-design-tools.md). The animation attribute is a responsive-aware shape, so editors can disable entrance animations on mobile, for example.
- **CSS-first emission, no runtime by default** — entrance animations use `IntersectionObserver` + a single CSS class swap; continuous animations are pure CSS; transitions are pure CSS. The runtime is one shared module (~3 KB gzipped) loaded only on pages that use entrance animations.
- **Reusable across renderers** — Blade, React, Vue renderers all consume the same emitted class names + the same runtime module. The runtime is published to `dist/animations.js` and registered as a Vite chunk.
- **Block support opt-in** via `supports.artisanpackAnimations` in `block.json`. By default, all `artisanpack/*` blocks opt in; blocks can opt out (e.g. inline `core/list-item` would be noisy).
- **Custom keyframe authoring UI** — a "Custom Motion" editor reachable from the global Site Editor → Styles → Animations panel. UI lets the developer name a keyframe and define stops (0% / 50% / 100% at minimum, with `+ Add stop`); each stop edits the same set of transformable properties (transform, opacity, filter, color). Save persists into the `GlobalStyles` JSON; the keyframe immediately appears in the relevant dropdowns.

### 4.2 Out of scope

- **Scroll-linked animations** (parallax, scroll-driven `@keyframes`). Defer to a future release pending broader browser support for `scroll-timeline`.
- **Spring physics / FLIP / view transitions.** Defer; the CSS-first foundation must land first.
- **Per-character text animations.** Defer; would require a text-splitting runtime.
- **SVG morphing / Lottie integration.** Defer; out of scope for a markup-driven editor.

## 5. Behavior

### 5.1 Happy path

1. Editor selects a Hero `cover` block and opens the Animation panel.
2. Under **Entrance**, they pick `fade-in-up`, set duration `600ms`, delay `100ms`, easing `ease-out`, threshold `0.2`.
3. They save. The block markup gains `data-ap-anim-entrance="fade-in-up" data-ap-anim-duration="600" data-ap-anim-delay="100" data-ap-anim-easing="ease-out"` and an initial `ap-anim-pre` class that hides it offscreen.
4. The shared runtime (loaded once on the page) observes the block via `IntersectionObserver`. When it enters the viewport at the threshold, the runtime swaps `ap-anim-pre` for `ap-anim-play`, which has `animation: fade-in-up 600ms ease-out 100ms forwards`.
5. The user scrolls; the block fades up. The animation runs once.
6. If the user has `prefers-reduced-motion: reduce`, the runtime skips the class swap; the block appears statically.

### 5.2 Edge cases

- **Block enters viewport before the runtime loads** (slow JS). The block stays in its pre-animation state until the runtime initializes, then plays normally. Acceptable.
- **No JS available** (e.g. crawler, no-JS user). The renderer emits a `<noscript>` rule that resets `.ap-anim-pre` to the visible/final state — the block is shown statically.
- **Continuous animation set + reduced motion enabled.** Continuous animation is suppressed (no class added).
- **Custom keyframe name collides with a built-in.** Schema validator rejects with a descriptive error; built-in names are reserved.
- **User configures a 30-second duration.** Allowed. Warning shown in the InspectorControls if duration exceeds a soft threshold (5s) suggesting a continuous animation might be a better fit.
- **Entrance animation set inside a `display:none` collapsed parent** (e.g. closed accordion). Runtime re-checks on parent state change via `MutationObserver`; animation plays on first reveal.

## 6. Acceptance Criteria

- [ ] Entrance animations work for all built-in motions across Blade, React, and Vue renderers.
- [ ] Continuous animations work and respect `prefers-reduced-motion`.
- [ ] Hover transition control emits the correct `transition` CSS and composes with state-design tools.
- [ ] Custom keyframes registered via `theme.json` show in dropdowns with the configured name.
- [ ] Custom keyframes authored via the Site Editor UI persist into Global Styles and reappear on reload.
- [ ] `prefers-reduced-motion` is respected by default; per-block opt-out works.
- [ ] Per-breakpoint disable works (set `entrance: { base: 'fade-in', md: null }` → no animation under `md`).
- [ ] Runtime is loaded only on pages that have at least one entrance animation; bundle size <5 KB gzipped.
- [ ] Block opt-out via `supports.artisanpackAnimations: false` removes the Animation panel.
- [ ] No-JS fallback renders blocks in their final state via a `<noscript>` rule.
- [ ] Pest tests cover: registry resolution, schema validation, attribute migration, server emission per family.
- [ ] Vitest tests cover: runtime IntersectionObserver behavior, reduced-motion suppression, class-swap timing.
- [ ] Playwright E2E covers: entrance animation play on scroll, continuous animation persists, custom keyframe round-trip, reduced-motion preference suppresses entrance.
- [ ] Docs in `docs/animations.md` cover the registry, the runtime, custom keyframe authoring, and accessibility guarantees.

## 7. Implementation Notes

### 7.1 Files to create

- `src/Animations/AnimationRegistry.php` — resolves built-ins + theme.json + config + custom keyframes from Global Styles.
- `src/Animations/KeyframeRegistry.php` — built-in + custom keyframe CSS emission.
- `src/Animations/AnimationCssEmitter.php` — translates attribute storage to CSS class strings + scoped rules.
- `resources/js/visual-editor/animations/AnimationPanel.tsx` — three-tab inspector panel.
- `resources/js/visual-editor/animations/CustomKeyframeEditor.tsx` — Site Editor → Styles → Animations sub-page.
- `resources/js/visual-editor/animations/runtime.ts` — shared IntersectionObserver + reduced-motion runtime, published to `dist/animations.js`.
- `packages/visual-editor-renderer-{blade,react,vue}/src/animations/` — per-renderer integration.
- `tests/Unit/Animations/*`, `tests/Feature/Animations/RendererTest.php`, Vitest + Playwright suites.

### 7.2 Files to modify

- `config/visual-editor.php` — add the `animations` key (registry overrides, default reduced-motion behavior).
- `src/VisualEditorServiceProvider.php` — register `AnimationRegistry` + `KeyframeRegistry` singletons.
- All forked `block.json` files — add `supports.artisanpackAnimations: true` (default).
- `vite.config.ts` — emit the runtime as a separate chunk so it's lazy-loadable.
- `docs/theming.md` — document custom-keyframe registration.

### 7.3 Database / schema

Custom keyframes authored in the Site Editor live in the existing `GlobalStyles` JSON column (`styles.custom.artisanpack.keyframes`). No new migrations.

### 7.4 Dependencies

None new. IntersectionObserver and `prefers-reduced-motion` are baseline browser APIs.

## 8. Open Questions

- Should the runtime auto-pause continuous animations when the tab is hidden (`document.visibilityState`)? (Tentative: yes, default ON, can be turned off via `data-ap-anim-pause-when-hidden="false"`.)
- Do we ship "exit" animations (animation when block leaves viewport)? (Tentative: defer — limited real use, adds runtime complexity.)
