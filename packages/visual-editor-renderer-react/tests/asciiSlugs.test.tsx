/**
 * ASCII-only lowercasing in slugs (R-19). PHP's `strtolower()` leaves
 * non-ASCII letters alone, so `İnk` slugs to `nk` in Blade; the React and
 * Vue twins must not fold `İ` to `i̇` (which slugs to `i-nk`).
 */

import { createSSRApp, h as vueH } from 'vue';
import { renderToString as vueRenderToString } from '@vue/server-renderer';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree as ReactBlockTree } from '../src/BlockTree';
import * as reactPresets from '../src/support/presetClasses';
import * as reactReference from '../src/support/presetReference';
import '../../visual-editor-renderer-vue/src/index';
import { BlockTree as VueBlockTree } from '../../visual-editor-renderer-vue/src/BlockTree';
import * as vuePresets from '../../visual-editor-renderer-vue/src/support/presetClasses';
import * as vueReference from '../../visual-editor-renderer-vue/src/support/presetReference';
import type { Block } from '../src/types';
import { makeBlock } from './helpers';

async function renderBoth(tree: Block[]): Promise<string[]> {
    return [
        renderToStaticMarkup(createElement(ReactBlockTree, { tree })),
        await vueRenderToString(createSSRApp({ render: () => vueH(VueBlockTree, { tree }) })),
    ];
}

describe.each([
    ['react', reactPresets, reactReference],
    ['vue', vuePresets, vueReference],
])('%s ASCII slugs', (_name, presets, reference) => {
    it('preset classes lowercase ASCII only, like BlockSupports::slugify()', () => {
        expect(presets.presetClasses({ backgroundColor: 'İnk', textColor: 'Ink' })).toEqual([
            'has-nk-background-color',
            'has-background',
            'has-ink-color',
            'has-text-color',
        ]);
    });

    it('preset references lowercase ASCII only, like PresetRegistry::presetSlug()', () => {
        expect(reference.presetSlug('İnk')).toBe('--nk');
        expect(reference.presetSlug('a_日😀')).toBe('a--------');
        expect(reference.presetSlug('bigGap')).toBe('big-gap');
        expect(reference.expandPresetReference('var:preset|color|İnk')).toBe('var(--wp--preset--color----nk)');
    });
});

describe('ASCII slugs in block renderers', () => {
    it('navigation overlay colors and tab ids lowercase ASCII only', async () => {
        const tree = [
            makeBlock('core/navigation', { overlayMenu: 'always', overlayBackgroundColor: 'İnk', overlayTextColor: 'İnk' }, [], 'nav'),
            makeBlock('artisanpack/tabs', {}, [makeBlock('artisanpack/tab-section', { tabId: 'İnk', label: 'L' }, [], 'tab')], 'tabs'),
        ];

        for (const html of await renderBoth(tree)) {
            expect(html).toContain('has-nk-background-color');
            expect(html).toContain('has-nk-color');
            expect(html).toContain('tabs-tab-nk');
            expect(html).not.toContain('i-nk');
        }
    });
});
