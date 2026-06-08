/**
 * Emits the renderer-static layout baseline CSS — the `is-layout-flow`,
 * `is-layout-constrained`, `is-layout-flex`, and `is-layout-grid` rules
 * that make Gutenberg's flow / constrained / flex / grid containers
 * actually lay out their children correctly on the public site.
 *
 * Mirrors the `<style data-ve-layout-baseline>` block emitted by the
 * Blade renderer's `<x-ve-blocks-styles />` component byte-for-byte, so
 * a Vue host that does not load the published Blade CSS bundle can
 * still get the block-gap + flex-baseline behavior the visual editor's
 * canvas already shows.
 *
 * Mount once near the top of the layout (alongside `<GlobalStyles />`).
 * The component takes no props — the rules are renderer-static.
 *
 * @since 1.0.0
 */

import { defineComponent, h } from 'vue';
import type { VNode } from 'vue';

import { LAYOUT_BASELINE_CSS } from './support/layoutBaselineCss';

export const LayoutBaseline = defineComponent({
    name: 'LayoutBaseline',
    setup() {
        return (): VNode =>
            h(
                'style',
                {
                    'data-ve-layout-baseline': '',
                    innerHTML: LAYOUT_BASELINE_CSS,
                },
                []
            );
    },
});
