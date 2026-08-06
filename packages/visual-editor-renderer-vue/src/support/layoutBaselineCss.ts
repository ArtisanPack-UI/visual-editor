/**
 * Renderer-static layout baseline CSS — shared between Vue's
 * `<LayoutBaseline />` component and its React counterpart so all three
 * renderers (Blade, React, Vue) emit byte-identical output.
 *
 * Mirrors the inline `<style data-ve-layout-baseline>` block in
 * `packages/visual-editor-renderer-blade/resources/views/components/
 * blocks-styles.blade.php`. Keep the strings in sync when changing one
 * — the renderer parity tests assert on it.
 *
 * One deliberate exception (#702): the constrained-group containment
 * rules at the end have no counterpart in the Blade baseline, because
 * Blade emits them from `ThemeJsonTokensCompiler::compileLayoutRules()`
 * gated on `theme.json` layout sizes. This renderer has no equivalent
 * theme-token compiler, so they live here instead. See the inline note
 * on those rules for how the gating is preserved.
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
    '.is-layout-grid > :is(*, div) { margin: 0; }\n' +
    // #702 — constrained-group containment, mirroring rule set C in the
    // Blade renderer's `ThemeJsonTokensCompiler::compileLayoutRules()`.
    // Keyed on the per-block compound rather than the shared modifier so
    // only wrappers this renderer emits are constrained; host markup that
    // hand-writes `is-layout-constrained` keeps its own behavior.
    //
    // The Blade side gates these on `theme.json` declaring `contentSize`
    // / `wideSize`; this string is static, so the gating falls out of
    // `var()` semantics instead — an undefined custom property makes the
    // declaration invalid at computed-value time, which resolves
    // `max-width` to its initial `none`.
    //
    // The auto margins are NOT gated the same way: with no `contentSize`
    // configured they still apply, which centers any child that sets its
    // own width instead of leaving it start-aligned. That matches what
    // WordPress core emits for a constrained layout, and a constrained
    // group on a host that declares no content size is a misconfiguration
    // either way — but it is the one place this baseline goes further
    // than the Blade renderer's gated output.
    '.wp-block-group.wp-block-group-is-layout-constrained > :where(:not(.alignwide):not(.alignfull):not(.alignleft):not(.alignright)) { max-width: var(--wp--style--global--content-size); margin-left: auto; margin-right: auto; }\n' +
    '.wp-block-group.wp-block-group-is-layout-constrained > .alignwide { max-width: var(--wp--style--global--wide-size); margin-left: auto; margin-right: auto; }\n' +
    '.wp-block-group.wp-block-group-is-layout-constrained > .alignfull { max-width: none; }';
