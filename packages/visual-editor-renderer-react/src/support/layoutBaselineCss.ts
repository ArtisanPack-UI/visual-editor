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

/**
 * Package default spacing sizes. Mirrors
 * `PresetRegistry::DEFAULT_SPACING_SIZES` (and the editor's
 * `DEFAULT_SPACING_SIZES`) — the renderer tests guard the sync.
 *
 * @since 1.12.0
 */
export const DEFAULT_SPACING_SIZES: ReadonlyArray<{ slug: string; size: string }> = [
    { slug: '20', size: '0.5rem' },
    { slug: '30', size: '1rem' },
    { slug: '40', size: '1.5rem' },
    { slug: '50', size: '3rem' },
    { slug: '60', size: '5rem' },
    { slug: '70', size: '7rem' },
];

/**
 * Zero-specificity fallback declarations for {@link DEFAULT_SPACING_SIZES}.
 *
 * @since 1.12.0
 */
export const SPACING_PRESET_DEFAULTS_CSS =
    ':where(:root) { ' +
    DEFAULT_SPACING_SIZES.map(({ slug, size }) => `--wp--preset--spacing--${slug}: ${size};`).join(' ') +
    ' }';

/**
 * Package default font sizes and palette. Mirror
 * `PresetRegistry::DEFAULT_FONT_SIZES` / `DEFAULT_PALETTE` (#821).
 *
 * @since 1.12.1
 */
export const DEFAULT_FONT_SIZES: ReadonlyArray<{ slug: string; size: string }> = [
    { slug: 'small', size: '13px' },
    { slug: 'regular', size: '16px' },
    { slug: 'medium', size: '20px' },
    { slug: 'large', size: '28px' },
    { slug: 'huge', size: '36px' },
];

export const DEFAULT_PALETTE: ReadonlyArray<{ slug: string; color: string }> = [
    { slug: 'base-content', color: '#1f2937' },
    { slug: 'base-muted', color: '#6b7280' },
    { slug: 'primary', color: '#2563eb' },
    { slug: 'secondary', color: '#64748b' },
    { slug: 'accent', color: '#9333ea' },
    { slug: 'success', color: '#16a34a' },
    { slug: 'warning', color: '#d97706' },
    { slug: 'error', color: '#dc2626' },
];

/**
 * Zero-specificity layout tokens plus default font-size / palette presets
 * and their `.has-{slug}-*` utility classes (#821). Mirrors
 * `PresetRegistry::layoutDefaultsCss()` / `presetDefaultsCss()`.
 *
 * @since 1.12.1
 */
export const DEFAULT_TOKENS_CSS =
    ':where(:root) { --wp--style--global--content-size: 720px; --wp--style--global--wide-size: 1080px; --wp--style--block-gap: 24px; ' +
    '--wp--style--root--padding-top: 1.5rem; --wp--style--root--padding-right: 1.5rem; --wp--style--root--padding-bottom: 1.5rem; --wp--style--root--padding-left: 1.5rem; ' +
    DEFAULT_FONT_SIZES.map(({ slug, size }) => `--wp--preset--font-size--${slug}: ${size};`).join(' ') +
    ' ' +
    DEFAULT_PALETTE.map(({ slug, color }) => `--wp--preset--color--${slug}: ${color};`).join(' ') +
    ' }\n' +
    DEFAULT_FONT_SIZES.map(
        ({ slug }) => `.has-${slug}-font-size { font-size: var(--wp--preset--font-size--${slug}) !important; }`
    ).join('\n') +
    '\n' +
    DEFAULT_PALETTE.map(
        ({ slug }) =>
            `.has-${slug}-color { color: var(--wp--preset--color--${slug}) !important; }\n` +
            `.has-${slug}-background-color { background-color: var(--wp--preset--color--${slug}) !important; }\n` +
            `.has-${slug}-border-color { border-color: var(--wp--preset--color--${slug}) !important; }`
    ).join('\n');

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
    // #821 — the sizes fall back to the package defaults (720px / 1080px,
    // the editor's layout sizes) when no theme declares them, matching
    // the Blade renderer's `compileDefaults()` output.
    '.wp-block-group.wp-block-group-is-layout-constrained > :where(:not(.alignwide):not(.alignfull):not(.alignleft):not(.alignright)) { max-width: var(--wp--style--global--content-size, 720px); margin-left: auto; margin-right: auto; }\n' +
    '.wp-block-group.wp-block-group-is-layout-constrained > .alignwide { max-width: var(--wp--style--global--wide-size, 1080px); margin-left: auto; margin-right: auto; }\n' +
    '.wp-block-group.wp-block-group-is-layout-constrained > .alignfull { max-width: none; }\n' +
    // #819 — floated alignments line up with the content column and
    // stack at the Mobile breakpoint (767px, #820). Mirrors `floatRules()` in the Blade
    // renderer's `ThemeJsonTokensCompiler`.
    '.wp-block-group.wp-block-group-is-layout-constrained > .alignleft { float: left; margin-inline-start: max(0px, calc((100% - var(--wp--style--global--content-size, 720px)) / 2)); margin-inline-end: 2em; }\n' +
    '.wp-block-group.wp-block-group-is-layout-constrained > .alignright { float: right; margin-inline-start: 2em; margin-inline-end: max(0px, calc((100% - var(--wp--style--global--content-size, 720px)) / 2)); }\n' +
    '@media (max-width: 767px) { .wp-block-group.wp-block-group-is-layout-constrained > .alignleft, .wp-block-group.wp-block-group-is-layout-constrained > .alignright { float: none; margin-inline: auto; } }\n' +
    // #821 — gallery gap default, matching the Blade layout baseline.
    ':where(.wp-block-gallery.has-nested-images) { gap: var(--wp--style--unstable-gallery-gap, 16px); }\n' +
    // #819 — floats under any flow / constrained layout, matching
    // WordPress's layout definitions and the Blade layout baseline. The
    // `size-full` reset undoes Tailwind's `size-full` utility (width /
    // height 100%), which collides with the image block's size class and
    // kept aligned images from floating; unlayered CSS beats Tailwind's
    // `@layer utilities` even at zero specificity.
    ':where(.wp-block-image.size-full) { width: auto; height: auto; }\n' +
    ':where(.is-layout-constrained, .is-layout-flow) > .alignleft { float: left; margin-inline-start: 0; margin-inline-end: 2em; }\n' +
    ':where(.is-layout-constrained, .is-layout-flow) > .alignright { float: right; margin-inline-start: 2em; margin-inline-end: 0; }\n' +
    // A float takes the same top gap as the sibling beside it so the
    // two start on the same line; an opening float drops it on both.
    ':where(.is-layout-constrained, .is-layout-flow) > :is(.alignleft, .alignright) { margin-block-start: var(--wp--style--block-gap, 24px); }\n' +
    ':where(.is-layout-constrained, .is-layout-flow) > :is(.alignleft, .alignright):first-child { margin-block-start: 0; }\n' +
    '@media (min-width: 768px) { :where(.is-layout-constrained, .is-layout-flow) > :is(.alignleft, .alignright):first-child + * { margin-block-start: 0; } }\n' +
    '@media (max-width: 767px) { :where(.is-layout-constrained, .is-layout-flow) > :is(.alignleft, .alignright) { float: none; margin-inline: auto; } }\n' +
    // #821 — layout tokens and the font-size / palette presets the
    // editor's pickers offer, at zero specificity so `<GlobalStyles>`
    // theme values win.
    DEFAULT_TOKENS_CSS +
    '\n' +
    // #814 — the package-default spacing presets the editor's pickers
    // offer when a theme ships no `spacingSizes`, so a saved
    // `var:preset|spacing|40` resolves on React / Vue hosts too (Blade
    // declares them through `<x-ve-blocks-styles>` / `<x-ve-blocks>`).
    // `:where(:root)` keeps specificity at (0,0,0): any `:root`
    // declaration from `<GlobalStyles>` (theme, style variation, user
    // Global Styles, host presets) wins regardless of source order.
    SPACING_PRESET_DEFAULTS_CSS +
    '\n' +
    // #814 — navigation items' default gap. Mirrors the
    // `:where(.wp-block-navigation)` rule in the Blade renderer's
    // block-library `style.css`; the author value set inline as
    // `--wp--style--block-gap` / `row-gap` / `column-gap` wins over it.
    ':where(.wp-block-navigation) { gap: var(--wp--style--block-gap, 0.5em); }';
