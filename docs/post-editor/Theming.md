# Visual editor theming

> **Milestone:** M8 · [#318](https://github.com/ArtisanPack-UI/visual-editor/issues/318)
> **Audience:** Laravel host apps that mount `<x-visual-editor>` and want the
> editor to look like a DaisyUI / Artisanpack admin instead of a WordPress
> page.

The editor embeds Gutenberg (`@wordpress/block-editor`, `@wordpress/block-library`,
`@wordpress/components`) as a dependency, which gives us a mature editing
experience for free — but Gutenberg ships with its own WP-blue palette, serif
chrome, and focus rings. Dropping that straight into a DaisyUI admin feels
alien.

This document captures the theming strategy we landed on: **override CSS
custom properties; swap high-visibility components where clean swap points
exist; leave the rest.**

## How it works

### 1. A single overlay stylesheet

[`resources/js/visual-editor/editor/visual-editor-theme.css`](../../resources/js/visual-editor/editor/visual-editor-theme.css)
is imported by `editor-app.tsx` **after** every `@wordpress/*/build-style/*.css`
import so the cascade always resolves in our favor:

```ts
// resources/js/visual-editor/editor/editor-app.tsx
import '@wordpress/components/build-style/style.css';
import '@wordpress/block-editor/build-style/style.css';
// ...
import './editor-app.css';
import './visual-editor-theme.css'; // last wins
```

Every rule is scoped under `.ap-visual-editor__shell`, so:

- The overrides can't leak into the host Laravel admin page.
- Host apps that render other DaisyUI UI alongside the editor are unaffected.

### 2. Token map

DaisyUI's theme tokens (`--color-primary`, `--color-base-100/200/300`,
`--color-base-content`, `--color-error`, `--rounded-btn`) drive two
namespaces:

| Consumer | Token | DaisyUI source | Set in |
|----------|-------|-----------------|--------|
| Gutenberg chrome | `--wp-admin-theme-color` | `--color-primary` | `visual-editor-theme.css` |
| Gutenberg chrome | `--wp-admin-theme-color-darker-10` / `-20` | `--color-primary` | `visual-editor-theme.css` |
| `@wordpress/components` 32.x | `--wp-components-color-accent` + variants | `--color-primary` | `visual-editor-theme.css` |
| `@wordpress/components` 32.x | `--wp-components-color-background` | `--color-base-100` | `visual-editor-theme.css` |
| `@wordpress/components` 32.x | `--wp-components-color-foreground` | `--color-base-content` | `visual-editor-theme.css` |
| `@wordpress/components` 32.x | `--wp-components-color-gray-*` | `--color-base-200` / `-300` / `-content` | `visual-editor-theme.css` |
| Block editor chrome | `--wp-block-editor-type-color` | `--color-base-content` | `visual-editor-theme.css` |
| Block editor chrome | `--wp-block-synced-color` | `--color-secondary` | `visual-editor-theme.css` |
| Editor shell | `--ap-visual-editor-shell-*` | `--color-base-*` | `editor-app.css` |
| Editor top bar | `--ap-visual-editor-top-bar-*` | `--color-primary`, `--color-base-*`, `--color-error` | `top-bar.css` |

Every mapping falls back to a sensible hex default so the editor still renders
outside a DaisyUI host:

```css
--wp-admin-theme-color: var(--color-primary, #2563eb);
```

Radii follow the same pattern, reading `--rounded-btn` from DaisyUI and
defaulting to `6px`:

```css
--ap-visual-editor-top-bar-radius: var(--rounded-btn, 6px);
```

### Why no `prefers-color-scheme`?

Earlier drafts had OS-level `@media (prefers-color-scheme: dark)` blocks
in `top-bar.css` and `editor-app.css`. That fights DaisyUI: if the host
runs in DaisyUI's light theme but the user's OS is dark, the editor's
chrome flips to dark while the surrounding admin stays light. Light/dark
now flows entirely through DaisyUI's `data-theme` attribute, which the
host controls.

### 3. Component swaps

For high-visibility surfaces where Gutenberg's WP-blue leaks through even
after CSS theming, we swap in `@artisanpack-ui/react` equivalents rather than
fight the CSS:

| Surface | Was | Now |
|---------|-----|-----|
| Keyboard-shortcuts dialog | `@wordpress/components` `Modal` | `@artisanpack-ui/react/layout` `Modal` + `@artisanpack-ui/react/form` `Button` ([`keyboard-shortcuts-modal.tsx`](../../resources/js/visual-editor/editor/keyboard-shortcuts-modal.tsx)) |
| Load-error banner | `<p class="…--error">` | `@artisanpack-ui/react/feedback` `Alert` |
| Save-error toasts | Gutenberg `Snackbar` | `@artisanpack-ui/react/feedback` `ToastProvider` + `useToast` ([`save-notifications.tsx`](../../resources/js/visual-editor/editor/save-notifications.tsx)) |

Internal Gutenberg components — `SelectControl`, `RangeControl`,
`ToolsPanel`, tooltips, the block toolbar — stay as-is and inherit DaisyUI
tokens via the overlay.

## Light and dark mode

DaisyUI 5 exposes light mode tokens at `:root` and dark mode tokens at
`[data-theme='dark']`. Because our overlay reads those tokens at runtime,
switching themes on the host element is enough — no editor-specific toggle
is needed.

Smoke-test before shipping a theme change:

1. Load a page containing `<x-visual-editor>` in DaisyUI's default light
   theme. Check: toolbar highlights, inserter accent, focus rings, and the
   canvas text color match the rest of the admin.
2. Flip `data-theme` to `dark` on the `<html>` or `<body>` element and
   repeat. Check: shell background, sidebar, inputs, and the error banner
   all read against the dark surface without WP-blue bleed.

## Extending the theme

Host apps can layer their own overrides by targeting
`.ap-visual-editor__shell` in their own stylesheet — the overlay's values
are just `:root`-level defaults. For example, to force a secondary accent in
the editor only:

```css
.ap-visual-editor__shell {
    --wp-admin-theme-color: var(--color-secondary);
    --wp-components-color-accent: var(--color-secondary);
}
```

If a component doesn't respond to CSS custom property overrides (Gutenberg
occasionally hardcodes colors inline, especially in experimental UI), the
pragmatic answer is:

1. File a follow-up issue with a screenshot and the offending selector.
2. Consider another React-level swap if the component is high-visibility.
3. Otherwise, accept the gap. Perfect parity with a Laravel admin is a
   post-V1 goal — see the guidance in issue [#318][318].

[318]: https://github.com/ArtisanPack-UI/visual-editor/issues/318

## Known gaps

- **Serif block typography inside the canvas.** Block content is
  intentionally left with its editorial serif stack; that is a content
  decision, not a theming miss.
- **Experimental `@wordpress/components` UI.** Some "experimental"
  components (e.g. navigator, tools panel) hardcode colors inline and
  don't respect `--wp-components-color-*`. Track in a follow-up if we
  start using them.
- **Popovers from `@wordpress/components`.** Their shadow and border
  radius are tokenized; their default background is `#fff` hardcoded in
  some cases. Monitor on upgrade; file a follow-up if it regresses.

## Breakpoints (#487)

The editor's viewport switcher and the responsive value resolver share
a single registry resolved in this order — highest layer wins:

1. **Active theme's `theme.json`** —
   `settings.custom.artisanpack.breakpoints`
2. **Application config** —
   `config('artisanpack.visual-editor.breakpoints')`
3. **Package defaults** — `BreakpointRegistry::DEFAULTS`
   (Tailwind v4: `sm 640`, `md 768`, `lg 1024`, `xl 1280`, `2xl 1536`)

Merging is by key, so a theme can resize an existing breakpoint
(`"lg": "1100px"`), add a new one (`"3xl": 1920`), or replace defaults
wholesale. Values are integer pixels or CSS `Npx` strings; anything
else throws a descriptive error at load time.

The implicit `base` slot (no min-width, applies everywhere) is
reserved and cannot be redefined.

See [Responsive Design Tools](../blocks/Responsive-Design-Tools.md) for the
editor + developer workflow built on top of this registry.
