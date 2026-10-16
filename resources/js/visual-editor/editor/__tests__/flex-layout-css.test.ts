import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

import { BreakpointRegistry, DEVICE_DEFAULTS, LEGACY_DEFAULTS } from '../../responsive/registry';
import { buildFlexLayoutCss, flexLayoutCss } from '../flex-layout-css';

const flexLayoutStyles = readFileSync(resolve(__dirname, '../../../../css/flex-layout.css'), 'utf8');

/** Comments and whitespace stripped, as the Blade test compares them. */
function rules(css: string): string {
    return css.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\s+/g, ' ').trim();
}

describe('flexLayoutCss (#895)', () => {
    it('builds exactly the static stylesheet’s rules from the default breakpoints', () => {
        const legacy = LEGACY_DEFAULTS.map(({ key, minWidthPx }) => ({ key, minWidthPx: minWidthPx as number }));
        const devices = DEVICE_DEFAULTS.map(({ key, maxWidthPx }) => ({ key, maxWidthPx: maxWidthPx as number }));

        expect(rules(buildFlexLayoutCss(legacy, devices))).toBe(rules(flexLayoutStyles));
    });

    it('returns nothing for the default registry', () => {
        expect(flexLayoutCss(new BreakpointRegistry())).toBe('');
    });

    it('builds the rules at a host breakpoint width', () => {
        const css = flexLayoutCss(
            new BreakpointRegistry([
                { key: 'md', minWidthPx: 700, previewWidthPx: 768, label: 'Tablet' },
                ...DEVICE_DEFAULTS,
            ])
        );

        expect(css).toContain('@media (min-width: 700px) {\n\t:where(.md\\:ap-flex) > * + * { margin-block-start: 0; }');
        expect(css).not.toContain('(min-width: 640px)');
        expect(css).toContain('@media (max-width: 1023px) {');
    });
});
