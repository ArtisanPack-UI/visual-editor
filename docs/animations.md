# Block Animations

Block animations let editors attach motion to any block that opts into
`supports.artisanpackAnimations`. The system covers three families:

- **Entrance** — plays once when the block enters the viewport
  (IntersectionObserver-driven).
- **Hover** — a transition curve + composable preset
  (`lift`, `press`, `glow`) on `:hover`.
- **Continuous** — a CSS loop animation
  (`pulse`, `bounce`, `spin`, `ping`, `wiggle`, `float`).

It is CSS-first. The server emits a single `<style data-ve-animations>`
block per page that holds the `@keyframes` definitions plus the scoped
rules for every block on the page. A small (~3 KB gzipped) JS runtime
ships only on pages that have at least one entrance animation; it
swaps an `ap-anim-pre` class for `ap-anim-play` when the block scrolls
into view.

`prefers-reduced-motion: reduce` is respected by default. Editors can
opt a single block out by setting **Respect reduced motion** to off in
the inspector.

## Attribute shape

Stored on the block as `attributes.artisanpackAnimations`:

```json
{
    "entrance": {
        "name":      "fade-in-up",
        "duration":  600,
        "delay":     100,
        "easing":    "ease-out",
        "threshold": 0.2,
        "once":      true
    },
    "hover": {
        "name":     "lift",
        "duration": 200,
        "easing":   "ease-out"
    },
    "continuous": {
        "name":     "pulse",
        "duration": 2000,
        "easing":   "ease-in-out",
        "count":    "infinite"
    },
    "reducedMotion": "respect"
}
```

The `entrance.name` and `continuous.name` fields are
responsive-aware — pass a `{ base, sm, md, lg, xl, 2xl }` map to enable
different motions per breakpoint, or `null` at a specific breakpoint to
disable the animation there:

```json
{ "entrance": { "name": { "base": "fade-in", "md": null } } }
```

## Registry layers

Animations are resolved in priority order, highest layer wins on key
collision:

1. **`theme.json` →** `settings.custom.artisanpack.animations`
2. **app config →** `artisanpack.visual-editor.animations`
3. **package defaults →** `AnimationRegistry::DEFAULTS`

To remove a built-in animation, set its key to `null` in a higher
layer.

```php
// config/artisanpack/visual-editor.php
'animations' => [
    'entrance' => [
        'flip-x'        => null, // drop a built-in
        'fade-in-blur'  => [
            'label'    => 'Fade in (blur)',
            'keyframe' => 'apFadeInBlur',
            'duration' => 700,
            'easing'   => 'ease-out',
        ],
    ],
],
```

## Custom keyframes

Hosts and themes can author named `@keyframes` blocks two ways.

**Config** — declare them on the package config:

```php
'keyframes' => [
    [
        'name'  => 'confetti',
        'stops' => [
            [ 'at' => '0%',   'transform' => 'translateY(0)' ],
            [ 'at' => '50%',  'transform' => 'translateY(-12px) rotate(10deg)' ],
            [ 'at' => '100%', 'transform' => 'translateY(0)' ],
        ],
    ],
],
```

**Site Editor UI** — open Styles → Animations and use the Custom
Keyframe editor. Authored keyframes persist into the Global Styles
JSON (`styles.custom.artisanpack.keyframes`) and reappear on reload.

Allowed stop properties: `transform`, `opacity`, `filter`, `color`,
`background-color`, `box-shadow`. Built-in keyframe names (`apFadeIn`,
`apPulse`, etc.) are reserved.

## Renderer integration

The Blade renderer exposes
`AnimationMarkupResolver::resolve( $scope, $attributes )` which returns
the wrapper classes, the data attributes, and the scoped CSS the
partial drops onto the block. Per-block CSS is funnelled through
`AnimationCssAccumulator`, which drains once at the top of every
`<x-ve-blocks>` render into a single `<style data-ve-animations>` tag
plus a `<noscript>` reveal block.

The React and Vue renderers expose a framework-agnostic
`resolveAnimationMarkup( attributes )` that returns the same class list
+ data-attribute shape so the runtime sees identical markup regardless
of renderer.

## Runtime

Loaded once per page (`@artisanpack-ui/visual-editor/animations/runtime`).
On load it:

1. Reads `prefers-reduced-motion`. If reduce is set, suppresses
   entrance animations unless the block sets
   `data-ap-anim-reduced="allow"`.
2. Builds one `IntersectionObserver` per requested threshold; observes
   every `[data-ap-anim-entrance]` element.
3. When a block enters the viewport at its configured threshold,
   removes `ap-anim-pre` and adds `ap-anim-play`, which carries the
   real `animation` shorthand.
4. By default unobserves after one play. Blocks with
   `data-ap-anim-once="false"` are rearmed when they leave the
   viewport.
5. Watches `MutationObserver` for entrance blocks that arrive after
   bootstrap (e.g. revealed by an accordion expand) and observes them
   the same way.

Target bundle size: **<5 KB gzipped**. The runtime is intentionally
side-effecting at import time, so a `<script type="module">` tag is
all the renderer needs.

## Accessibility

- `prefers-reduced-motion: reduce` is respected by default at both the
  CSS layer (a `@media` block resets `animation` + `transition` to
  `none` and `opacity`/`transform` to their final state) and the JS
  layer (the runtime skips IntersectionObserver setup).
- A `<noscript>` rule reveals every entrance block in its final state
  when JS doesn't run.
- The inspector panel surfaces the reduced-motion preference per block
  so a designer can intentionally allow motion on essential animations.

## Testing

- Pest unit suites: `tests/Unit/VisualEditor/Animations/*`
- Pest feature suite: `packages/visual-editor-renderer-blade/tests/Feature/AnimationMarkupResolverTest.php`
- Vitest: `resources/js/visual-editor/animations/__tests__/*`
- Playwright spec (deactivated until the runner is wired in CI):
  `tests/E2E/animations.spec.ts`
