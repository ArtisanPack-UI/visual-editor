/**
 * #895 — the React and Vue renderers build the Flex Layout token rules
 * at the breakpoints `setBreakpoints()` set, as Blade's
 * `FlexLayoutStyles` does from `BreakpointRegistry`.
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { render } from '@testing-library/react';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';

import { LayoutBaseline as ReactLayoutBaseline } from '../src/LayoutBaseline';
import * as reactFlex from '../src/support/flexLayoutCss';
import * as reactVisibility from '../src/visibility';
import { LayoutBaseline as VueLayoutBaseline } from '../../visual-editor-renderer-vue/src/LayoutBaseline';
import * as vueFlex from '../../visual-editor-renderer-vue/src/support/flexLayoutCss';
import * as vueVisibility from '../../visual-editor-renderer-vue/src/visibility';

const STATIC_CSS = readFileSync(
    resolve(__dirname, '../../visual-editor-renderer-blade/resources/assets/frontend/flex-layout.css'),
    'utf8'
);

/** Comments and whitespace stripped, as the Blade test compares them. */
function rules(css: string): string {
    return css.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\s+/g, ' ').trim();
}

describe.each([
    ['react', reactFlex, reactVisibility],
    ['vue', vueFlex, vueVisibility],
])('%s flexLayoutCss', (_name, flex, visibility) => {
    afterEach(() => {
        visibility.setBreakpoints({});
    });

    it('builds exactly the static stylesheet’s rules from the default breakpoints', () => {
        expect(rules(flex.buildFlexLayoutCss(visibility.DEFAULT_BREAKPOINTS, visibility.DEFAULT_DEVICE_BREAKPOINTS))).toBe(
            rules(STATIC_CSS)
        );
    });

    it('returns nothing while the breakpoints are the defaults', () => {
        expect(flex.flexLayoutCss()).toBe('');
    });

    it('moves each breakpoint block to the host width', () => {
        visibility.setBreakpoints({ md: 700, mobile: { maxWidthPx: 599, previewWidthPx: 375 } });

        const css = flex.flexLayoutCss();

        expect(css).toContain('@media (min-width: 700px) {\n\t:where(.md\\:ap-flex) > * + * { margin-block-start: 0; }');
        expect(css).toContain('@media (max-width: 599px) {\n\t:where(.mobile\\:ap-flex) > * + * { margin-block-start: 0; }');
        expect(css).not.toContain('(min-width: 768px)');
        expect(css).not.toContain('(max-width: 767px)');
    });

    it('escapes a leading digit in a breakpoint prefix', () => {
        expect(flex.escapeFlexPrefix('tablet')).toBe('tablet\\:');
        expect(flex.escapeFlexPrefix('2xl')).toBe('\\32xl\\:');
        expect(flex.escapeFlexPrefix('2a')).toBe('\\32 a\\:');
    });
});

describe('LayoutBaseline flex token rules', () => {
    afterEach(() => {
        reactVisibility.setBreakpoints({});
        vueVisibility.setBreakpoints({});
    });

    it('prints no flex token rules with the default breakpoints', () => {
        expect(render(<ReactLayoutBaseline />).container.querySelector('style[data-ve-flex-layout]')).toBeNull();
        expect(mount(VueLayoutBaseline).find('style[data-ve-flex-layout]').exists()).toBe(false);
    });

    it('prints them at a custom tablet width', () => {
        reactVisibility.setBreakpoints({ tablet: { maxWidthPx: 700, previewWidthPx: 700 } });
        vueVisibility.setBreakpoints({ tablet: { maxWidthPx: 700, previewWidthPx: 700 } });

        const reactCss = render(<ReactLayoutBaseline />).container.querySelector('style[data-ve-flex-layout]')?.innerHTML ?? '';
        const vueCss = mount(VueLayoutBaseline).find('style[data-ve-flex-layout]').element.innerHTML;

        expect(reactCss).toContain('@media (max-width: 700px) {');
        expect(reactCss).toContain('\t.tablet\\:ap-flex-col { flex-direction: column; }');
        expect(vueCss).toBe(reactCss);
    });
});
