/**
 * Renderer-static layout baseline CSS — shared between React's
 * `<LayoutBaseline />` component and its Vue counterpart so all three
 * renderers (Blade, React, Vue) emit byte-identical output.
 *
 * Mirrors the inline `<style data-ve-layout-baseline>` block in
 * `packages/visual-editor-renderer-blade/resources/views/components/
 * blocks-styles.blade.php`. Keep the strings in sync when changing one
 * — the renderer parity tests assert on it.
 *
 * `:where()` on the flow/constrained selectors keeps specificity at
 * (0,0,0) so any theme rule with higher specificity continues to win —
 * matches WordPress's own output. The `var(--wp--style--block-gap,
 * 24px)` fallback mirrors core's default when `theme.json` does not set
 * `styles.spacing.blockGap`.
 *
 * @since 1.0.0
 */

export const LAYOUT_BASELINE_CSS =
    ':where(.is-layout-flow) > :first-child { margin-block-start: 0; }\n' +
    ':where(.is-layout-flow) > :last-child { margin-block-end: 0; }\n' +
    ':where(.is-layout-flow) > * { margin-block-start: 0; margin-block-end: 0; }\n' +
    ':where(.is-layout-flow) > * + * { margin-block-start: var(--wp--style--block-gap, 24px); margin-block-end: 0; }\n' +
    ':where(.is-layout-constrained) > :first-child { margin-block-start: 0; }\n' +
    ':where(.is-layout-constrained) > :last-child { margin-block-end: 0; }\n' +
    ':where(.is-layout-constrained) > * { margin-block-start: 0; margin-block-end: 0; }\n' +
    ':where(.is-layout-constrained) > * + * { margin-block-start: var(--wp--style--block-gap, 24px); margin-block-end: 0; }\n' +
    '.is-layout-flex { display: flex; flex-wrap: wrap; align-items: center; }\n' +
    '.is-layout-flex > :is(*, div) { margin: 0; }\n' +
    '.is-layout-grid { display: grid; }\n' +
    '.is-layout-grid > :is(*, div) { margin: 0; }';
