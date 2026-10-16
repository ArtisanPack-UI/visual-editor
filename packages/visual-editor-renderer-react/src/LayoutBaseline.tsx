/**
 * Emits the renderer-static layout baseline CSS — the `is-layout-flow`,
 * `is-layout-constrained`, `is-layout-flex`, and `is-layout-grid` rules
 * that make Gutenberg's flow / constrained / flex / grid containers
 * actually lay out their children correctly on the public site.
 *
 * Mirrors the `<style data-ve-layout-baseline>` block emitted by the
 * Blade renderer's `<x-ve-blocks-styles />` component byte-for-byte, so
 * a React host that does not load the published Blade CSS bundle can
 * still get the block-gap + flex-baseline behavior the visual editor's
 * canvas already shows.
 *
 * Mount once near the top of the layout (alongside `<GlobalStyles />`).
 * The component takes no props — the rules are renderer-static.
 *
 * When `setBreakpoints()` moved the breakpoints off the defaults, it also
 * prints the Flex Layout token rules at those widths
 * (`<style data-ve-flex-layout>`, #895), replacing the static
 * `flex-layout.css` the host would otherwise link.
 *
 * @since 1.0.0
 */

import type { ReactElement } from 'react';

import { flexLayoutCss } from './support/flexLayoutCss';
import { LAYOUT_BASELINE_CSS } from './support/layoutBaselineCss';

export function LayoutBaseline(): ReactElement {
    const baseline = (
        <style
            data-ve-layout-baseline=""
            dangerouslySetInnerHTML={{ __html: LAYOUT_BASELINE_CSS }}
        />
    );
    const flexCss = flexLayoutCss();

    if (flexCss === '') {
        return baseline;
    }

    return (
        <>
            {baseline}
            <style data-ve-flex-layout="" dangerouslySetInnerHTML={{ __html: flexCss }} />
        </>
    );
}
